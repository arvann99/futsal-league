<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamPlayer;
use App\Services\BracketViewService;
use App\Services\TournamentStatisticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Portal Publik per-turnamen (view-only, tanpa login). Menjadi cermin dari
 * portal Manager/Official namun:
 *   - dianchor ke satu TURNAMEN (bukan satu tim yang login), menampilkan
 *     SELURUH tim turnamen itu;
 *   - sepenuhnya hanya-baca (tanpa tambah/ubah/hapus, tanpa logger/edit skor);
 *   - tanpa halaman Ofisial tim (tidak ditampilkan ke publik).
 *
 * Kebijakan akses (sama dengan statistik/bracket publik, N13): turnamen wajib
 * punya minimal satu pertandingan agar dapat dilihat publik.
 */
class PublicTournamentController extends Controller
{
    public function __construct(private BracketViewService $bracketView)
    {
    }

    /**
     * Daftar turnamen yang portal publiknya dapat dilihat.
     */
    public function index()
    {
        $tournaments = Tournament::whereHas('matches')
            ->withCount(['matches as finished_matches_count' => function ($query) {
                $query->where('status', 'full_time');
            }])
            ->latest()
            ->get();

        return view('public.tournaments.index', [
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * Beranda portal satu turnamen: ringkasan tim, jumlah laga, juara (bila ada).
     */
    public function overview(Tournament $tournament)
    {
        $this->ensureVisible($tournament);

        $teams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get();

        $matchCounts = [
            'total' => TournamentMatch::where('tournament_id', $tournament->id)->count(),
            'finished' => TournamentMatch::where('tournament_id', $tournament->id)->where('status', 'full_time')->count(),
            'live' => TournamentMatch::where('tournament_id', $tournament->id)->whereIn('status', ['live_match', 'penalty_shootout'])->count(),
        ];

        return view('public.tournaments.overview', [
            'tournament' => $tournament,
            'teams' => $teams,
            'matchCounts' => $matchCounts,
            'hasBracket' => $this->bracketView->hasRenderableBracket($tournament),
        ]);
    }

    /**
     * Jadwal: seluruh laga turnamen, read-only. Terjadwal diurutkan menurut
     * tanggal, yang TBD ditaruh di akhir. Filter all/upcoming/finished/tbd.
     */
    public function schedule(Request $request, Tournament $tournament)
    {
        $this->ensureVisible($tournament);

        $matches = TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->orderByRaw('match_date IS NULL, match_date ASC')
            ->get();

        $now = now();
        $filter = $request->query('filter', 'all');
        $filter = in_array($filter, ['all', 'upcoming', 'finished', 'tbd'], true) ? $filter : 'all';

        $filtered = $matches->filter(function ($match) use ($filter, $now) {
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

        return view('public.tournaments.schedule', [
            'tournament' => $tournament,
            'matches' => $filtered,
            'totalMatches' => $matches->count(),
            'filter' => $filter,
        ]);
    }

    /**
     * Klasemen grup/liga turnamen, read-only.
     */
    public function standings(Tournament $tournament)
    {
        $this->ensureVisible($tournament);

        $tournament->loadMissing('groupSetting');

        return view('public.tournaments.standings', [
            'tournament' => $tournament,
            'groups' => $this->buildStandings($tournament),
        ]);
    }

    /**
     * Statistik turnamen (top skor/kartu/tim), read-only — memakai service yang
     * sama dengan portal lain.
     */
    public function statistics(Tournament $tournament, TournamentStatisticsService $statistics)
    {
        $this->ensureVisible($tournament);

        return view('public.tournaments.statistics', array_merge(
            ['tournament' => $tournament],
            $statistics->forTournament($tournament),
        ));
    }

    /**
     * Roster pemain seluruh tim turnamen, read-only (tanpa ofisial).
     */
    public function roster(Tournament $tournament)
    {
        $this->ensureVisible($tournament);

        $tournamentTeams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get();

        $players = TournamentTeamPlayer::with('tournamentTeam.team')
            ->whereIn('tournament_team_id', $tournamentTeams->pluck('id'))
            ->orderBy('player_name')
            ->get();

        // Kelompokkan pemain per tim untuk tampilan roster per-tim.
        $rosters = $tournamentTeams
            ->map(function (TournamentTeam $tt) use ($players) {
                $teamPlayers = $players->where('tournament_team_id', $tt->id)->values();

                return [
                    'team_name' => $tt->team?->name ?? 'Tim ' . $tt->id,
                    'logo' => $tt->team?->logo,
                    'group_label' => $tt->group_label,
                    'players' => $teamPlayers,
                ];
            })
            ->filter(fn ($roster) => $roster['players']->isNotEmpty())
            ->sortBy('team_name')
            ->values();

        return view('public.tournaments.roster', [
            'tournament' => $tournament,
            'rosters' => $rosters,
            'totalPlayers' => $players->count(),
        ]);
    }

    /**
     * 404 bila turnamen belum punya match (belum boleh dilihat publik).
     */
    private function ensureVisible(Tournament $tournament): void
    {
        if (! $tournament->matches()->exists()) {
            throw new NotFoundHttpException('Turnamen ini belum tersedia untuk publik.');
        }
    }

    /**
     * Bangun klasemen per grup. Diadaptasi dari OfficialStandingsController namun
     * tanpa penanda tim aktif (publik tidak punya konteks tim).
     */
    private function buildStandings(Tournament $tournament): array
    {
        $tournamentTeams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get();

        if ($tournamentTeams->isEmpty()) {
            return [];
        }

        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'full_time')
            ->whereIn('stage_type', ['group', 'league'])
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $pointSystem = $this->pointSystem($tournament);

        $teams = [];
        foreach ($tournamentTeams as $tournamentTeam) {
            $teams[$tournamentTeam->id] = [
                'team_id' => $tournamentTeam->team_id,
                'name' => $tournamentTeam->team?->name ?? 'Tim ' . $tournamentTeam->id,
                'logo' => $tournamentTeam->team?->logo,
                'group_label' => $tournamentTeam->group_label,
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
            unset($home, $away);
        }

        foreach ($teams as &$teamStats) {
            $teamStats['goal_difference'] = $teamStats['goals_for'] - $teamStats['goals_against'];
        }
        unset($teamStats);

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
            unset($row);

            $groups[] = [
                'label' => $hasGroups ? "Grup {$groupLabel}" : 'Klasemen Umum',
                'rows' => $rows,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $groups;
    }

    private function pointSystem(Tournament $tournament): array
    {
        $setting = AppSetting::where('key', 'tournament_' . $tournament->id . '_score_system')->first();

        if (! $setting || ! is_array($setting->value)) {
            return ['win' => 3, 'draw' => 1, 'loss' => 0];
        }

        return [
            'win' => $setting->value['win'] ?? 3,
            'draw' => $setting->value['draw'] ?? 1,
            'loss' => $setting->value['loss'] ?? 0,
        ];
    }
}
