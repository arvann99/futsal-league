<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use Illuminate\Http\Request;

class OfficialStandingsController extends Controller
{
    public function index(Request $request)
    {
        $team = $this->getOfficialTeam($request);

        if (! $team) {
            return redirect()->route('official.login');
        }

        $tournamentIds = $team->tournamentTeams()->pluck('tournament_id')->unique();

        $standings = $tournamentIds->map(function ($tournamentId) use ($team) {
            $tournament = Tournament::with('groupSetting')->find($tournamentId);

            if (! $tournament) {
                return null;
            }

            return $this->buildTournamentStandings($tournament, $team);
        })->filter()->values();

        return view('official.standings.index', [
            'team' => $team,
            'standings' => $standings,
        ]);
    }

    private function getOfficialTeam(Request $request): ?Team
    {
        return Team::find($request->session()->get('official_team_id'));
    }

    private function buildTournamentStandings(Tournament $tournament, Team $officialTeam): array
    {
        $tournamentTeams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get();

        if ($tournamentTeams->isEmpty()) {
            return [
                'tournament' => $tournament,
                'groups' => [],
            ];
        }

        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'full_time')
            ->whereIn('stage_type', ['group', 'league'])
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get();

        if ($matches->isEmpty()) {
            return [
                'tournament' => $tournament,
                'groups' => [],
            ];
        }

        $pointSystem = $this->getPointSystem($tournament);

        $teams = [];
        foreach ($tournamentTeams as $tournamentTeam) {
            $teams[$tournamentTeam->id] = [
                'tournament_team_id' => $tournamentTeam->id,
                'team_id' => $tournamentTeam->team_id,
                'name' => $tournamentTeam->team?->name ?? 'Tim ' . $tournamentTeam->id,
                'logo' => $tournamentTeam->team?->logo,
                'group_label' => $tournamentTeam->group_label,
                'is_current' => $tournamentTeam->team_id === $officialTeam->id,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
                'points' => 0,
            ];
        }

        foreach ($matches as $match) {
            if (! isset($teams[$match->home_team_id]) || ! isset($teams[$match->away_team_id])) {
                continue;
            }

            $home = &$teams[$match->home_team_id];
            $away = &$teams[$match->away_team_id];
            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $home['played']++;
            $away['played']++;
            $home['goals_for'] += $homeScore;
            $home['goals_against'] += $awayScore;
            $away['goals_for'] += $awayScore;
            $away['goals_against'] += $homeScore;

            if ($homeScore > $awayScore) {
                $home['wins']++;
                $away['losses']++;
                $home['points'] += $pointSystem['win'];
                $away['points'] += $pointSystem['loss'];
            } elseif ($homeScore < $awayScore) {
                $away['wins']++;
                $home['losses']++;
                $away['points'] += $pointSystem['win'];
                $home['points'] += $pointSystem['loss'];
            } else {
                $home['draws']++;
                $away['draws']++;
                $home['points'] += $pointSystem['draw'];
                $away['points'] += $pointSystem['draw'];
            }
        }

        foreach ($teams as &$teamStats) {
            $teamStats['goal_difference'] = $teamStats['goals_for'] - $teamStats['goals_against'];
        }

        $hasGroups = $tournamentTeams->pluck('group_label')->filter()->isNotEmpty();
        $grouped = [];

        foreach ($teams as $teamStats) {
            $label = $hasGroups ? ($teamStats['group_label'] ?? 'Tanpa Grup') : 'Umum';
            $grouped[$label][] = $teamStats;
        }

        $groups = [];
        foreach ($grouped as $groupLabel => $rows) {
            usort($rows, function ($a, $b) {
                return $b['points'] <=> $a['points']
                    ?: $b['goal_difference'] <=> $a['goal_difference']
                    ?: $b['goals_for'] <=> $a['goals_for']
                    ?: strcmp($a['name'], $b['name']);
            });

            foreach ($rows as $index => &$row) {
                $row['position'] = $index + 1;
            }

            $groups[] = [
                'label' => $hasGroups ? "Grup {$groupLabel}" : 'Klasemen Umum',
                'rows' => $rows,
            ];
        }

        usort($groups, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return [
            'tournament' => $tournament,
            'groups' => $groups,
        ];
    }

    private function getPointSystem(Tournament $tournament): array
    {
        $default = ['win' => 3, 'draw' => 1, 'loss' => 0];
        $setting = AppSetting::where('key', 'tournament_' . $tournament->id . '_score_system')->first();

        if (! $setting || ! is_array($setting->value)) {
            return $default;
        }

        return [
            'win' => $setting->value['win'] ?? 3,
            'draw' => $setting->value['draw'] ?? 1,
            'loss' => $setting->value['loss'] ?? 0,
        ];
    }
}
