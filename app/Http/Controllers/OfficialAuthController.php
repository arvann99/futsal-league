<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeamOfficial;
use App\Models\TournamentTeamPlayer;
use App\Services\TournamentStatisticsService;
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
        $tournamentIds = $team->tournamentTeams()->pluck('tournament_id')->unique();

        // N11 — scope tampilan jadwal: 'internal' (default) hanya laga tim sendiri,
        // 'tournament' menampilkan seluruh laga dari semua turnamen yang diikuti.
        $scope = $request->query('scope', 'internal');
        $scope = in_array($scope, ['internal', 'tournament'], true) ? $scope : 'internal';

        // R20 — tampilkan juga pertandingan yang belum dijadwalkan (match_date
        // NULL / TBD) supaya manager bisa melihat lawan & babak lebih awal.
        // Match terjadwal diurutkan menurut tanggal, yang TBD ditaruh di akhir.
        $matches = TournamentMatch::with(['tournament', 'homeTeam.team', 'awayTeam.team'])
            ->when($scope === 'tournament', function ($query) use ($tournamentIds) {
                // Jadwal Turnamen: seluruh laga dari turnamen yang diikuti tim.
                $query->whereIn('tournament_id', $tournamentIds);
            }, function ($query) use ($tournamentTeamIds) {
                // Jadwal Internal: hanya laga yang melibatkan tim manager.
                $query->where(function ($q) use ($tournamentTeamIds) {
                    $q->whereIn('home_team_id', $tournamentTeamIds)
                        ->orWhereIn('away_team_id', $tournamentTeamIds);
                });
            })
            ->orderByRaw('match_date IS NULL, match_date ASC')
            ->get();

        // N7 — scoreboard tim yang SEDANG bertanding (live) di seluruh turnamen
        // yang diikuti tim (bukan hanya laga tim sendiri), untuk ditampilkan
        // menggantikan "Riwayat Pertandingan".
        $liveMatches = TournamentMatch::with(['tournament', 'homeTeam.team', 'awayTeam.team'])
            ->whereIn('tournament_id', $tournamentIds)
            ->whereIn('status', ['live_match', 'penalty_shootout'])
            ->orderByDesc('match_date')
            ->get();

        $now = now();
        // "Pertandingan Berikutnya" selalu merujuk laga tim sendiri walau scope
        // sedang menampilkan seluruh turnamen.
        $nextMatch = $matches->first(function ($match) use ($now, $tournamentTeamIds) {
            $involvesTeam = $tournamentTeamIds->contains($match->home_team_id)
                || $tournamentTeamIds->contains($match->away_team_id);

            return $involvesTeam && $match->match_date && $match->match_date->gte($now);
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
            'scope' => $scope,
            'liveMatches' => $liveMatches,
            'teamTournamentTeamIds' => $tournamentTeamIds->toArray(),
        ]);
    }

    /**
     * N13 — Statistik view-only untuk Manager.
     * Menampilkan statistik (top skor, assist, kartu, statistik tim) untuk
     * setiap turnamen yang diikuti tim. Komputasi memakai service yang sama
     * dengan halaman Admin (N12) agar konsisten dan tanpa duplikasi.
     */
    public function statistics(Request $request, TournamentStatisticsService $statistics)
    {
        $team = Team::find($request->session()->get('official_team_id'));

        if (! $team) {
            return redirect()->route('official.login');
        }

        $tournamentIds = $team->tournamentTeams()->pluck('tournament_id')->unique();

        $reports = $tournamentIds
            ->map(function ($tournamentId) use ($statistics) {
                $tournament = Tournament::find($tournamentId);

                if (! $tournament) {
                    return null;
                }

                return [
                    'tournament' => $tournament,
                    'stats' => $statistics->forTournament($tournament),
                ];
            })
            ->filter()
            ->values();

        return view('official.statistics', [
            'team' => $team,
            'reports' => $reports,
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('official_team_id');
        $request->session()->regenerateToken();

        return redirect()->route('official.login');
    }
}
