<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\TournamentTeamOfficial;
use App\Models\TournamentTeamPlayer;
use Illuminate\Http\Request;

class OfficialAuthController extends Controller
{
    public function showLogin()
    {
        return view('official.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'manager_token' => 'required|string',
        ]);

        $team = Team::where('manager_token', $request->input('manager_token'))->first();

        if (! $team) {
            return back()->withErrors([
                'manager_token' => 'Token tidak ditemukan.',
            ])->onlyInput('manager_token');
        }

        $request->session()->regenerate();
        $request->session()->put('official_team_id', $team->id);

        return redirect()->intended(route('official.dashboard'));
    }

    public function dashboard(Request $request)
    {
        $team = Team::find($request->session()->get('official_team_id'));

        if (! $team) {
            return redirect()->route('official.login');
        }

        $tournamentTeamIds = $team->tournamentTeams()->pluck('id');
        $tournamentTeams = $team->tournamentTeams()->with('tournament')->get();
        $tournamentIds = $tournamentTeams->pluck('tournament_id')->unique();

        $totalPlayers = TournamentTeamPlayer::whereIn('tournament_team_id', $tournamentTeamIds)->count();
        $totalGoalkeepers = TournamentTeamPlayer::whereIn('tournament_team_id', $tournamentTeamIds)
            ->where('dominant_position', 'GK')
            ->count();
        $totalOfficials = TournamentTeamOfficial::whereIn('tournament_team_id', $tournamentTeamIds)->count();
        $officialManagerCount = TournamentTeamOfficial::whereIn('tournament_team_id', $tournamentTeamIds)
            ->where('role', 'Manager')
            ->count();
        $officialCoachCount = TournamentTeamOfficial::whereIn('tournament_team_id', $tournamentTeamIds)
            ->where('role', 'Coach')
            ->count();
        $officialAssistantCoachCount = TournamentTeamOfficial::whereIn('tournament_team_id', $tournamentTeamIds)
            ->where('role', 'Assistant Coach')
            ->count();
        $captain = TournamentTeamPlayer::whereIn('tournament_team_id', $tournamentTeamIds)
            ->where('is_captain', true)
            ->orderBy('tournament_team_id')
            ->first();

        $nextMatch = TournamentMatch::with(['tournament', 'homeTeam.team', 'awayTeam.team'])
            ->whereIn('tournament_id', $tournamentIds)
            ->whereNotNull('match_date')
            ->whereIn('status', ['scheduled', 'live_match'])
            ->orderBy('match_date')
            ->first();

        return view('official.dashboard', [
            'team' => $team,
            'totalPlayers' => $totalPlayers,
            'totalGoalkeepers' => $totalGoalkeepers,
            'totalOfficials' => $totalOfficials,
            'officialManagerCount' => $officialManagerCount,
            'officialCoachCount' => $officialCoachCount,
            'officialAssistantCoachCount' => $officialAssistantCoachCount,
            'tournamentTeams' => $tournamentTeams,
            'captain' => $captain,
            'nextMatch' => $nextMatch,
        ]);
    }

    public function schedule(Request $request)
    {
        $team = Team::find($request->session()->get('official_team_id'));

        if (! $team) {
            return redirect()->route('official.login');
        }

        $tournamentTeamIds = $team->tournamentTeams()->pluck('id');

        // R20 — tampilkan juga pertandingan yang belum dijadwalkan (match_date
        // NULL / TBD) supaya manager bisa melihat lawan & babak lebih awal.
        // Match terjadwal diurutkan menurut tanggal, yang TBD ditaruh di akhir.
        $matches = TournamentMatch::with(['tournament', 'homeTeam.team', 'awayTeam.team'])
            ->where(function ($query) use ($tournamentTeamIds) {
                $query->whereIn('home_team_id', $tournamentTeamIds)
                    ->orWhereIn('away_team_id', $tournamentTeamIds);
            })
            ->orderByRaw('match_date IS NULL, match_date ASC')
            ->get();

        $now = now();
        $nextMatch = $matches->first(function ($match) use ($now) {
            return $match->match_date && $match->match_date->gte($now);
        });

        $filter = $request->query('filter', 'all');
        $filteredMatches = $matches->filter(function ($match) use ($filter, $now) {
            if ($filter === 'upcoming') {
                return $match->match_date && $match->match_date->gte($now);
            }

            if ($filter === 'finished') {
                return $match->match_date && $match->match_date->lt($now);
            }

            if ($filter === 'tbd') {
                return ! $match->match_date;
            }

            return true;
        })->values();

        return view('official.schedule', [
            'team' => $team,
            'matches' => $filteredMatches,
            'nextMatch' => $nextMatch,
            'filter' => $filter,
            'teamTournamentTeamIds' => $tournamentTeamIds->toArray(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('official_team_id');
        $request->session()->regenerateToken();

        return redirect()->route('official.login');
    }
}
