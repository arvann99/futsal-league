<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * N12 — Layanan agregasi statistik turnamen (reusable).
 *
 * Menghitung seluruh metrik halaman "Manajemen Pemain" / Statistik:
 *   - Top Skor (pemain)        : event `goal` + `penalty_goal`
 *   - Top Assist (pemain)      : event `assist` (fitur input belum ada → biasanya kosong)
 *   - Kartu Kuning (pemain)    : event `yellow_card`
 *   - Kartu Merah (pemain)     : event `red_card`
 *   - Tim Paling Produktif     : total gol dicetak (dari skor akhir match)
 *   - Tim Paling Kebobolan     : total gol kemasukan (dari skor akhir match)
 *   - Tim Paling Fairplay      : akumulasi kartu (kuning+merah) paling sedikit
 *
 * Dirancang reusable untuk N13 (view-only Manager & Tamu/Visitor) —
 * tidak melakukan otorisasi; pemanggil yang bertanggung jawab atas akses.
 */
class TournamentStatisticsService
{
    /**
     * Hanya match yang skornya sudah final dihitung untuk statistik tim.
     */
    private const SCORED_STATUSES = ['full_time'];

    /**
     * Kembalikan seluruh metrik untuk satu turnamen.
     *
     * @return array{
     *   top_scorers: \Illuminate\Support\Collection,
     *   top_assists: \Illuminate\Support\Collection,
     *   top_yellow_cards: \Illuminate\Support\Collection,
     *   top_red_cards: \Illuminate\Support\Collection,
     *   most_productive_teams: \Illuminate\Support\Collection,
     *   most_conceded_teams: \Illuminate\Support\Collection,
     *   fairplay_teams: \Illuminate\Support\Collection,
     *   has_assist_data: bool,
     * }
     */
    public function forTournament(Tournament $tournament, int $limit = 10): array
    {
        $playerStats = $this->playerEventAggregates($tournament);

        return [
            'top_scorers'           => $this->rank($playerStats, 'goals', $limit),
            'top_assists'           => $this->rank($playerStats, 'assists', $limit),
            'top_yellow_cards'      => $this->rank($playerStats, 'yellow_cards', $limit),
            'top_red_cards'         => $this->rank($playerStats, 'red_cards', $limit),
            'most_productive_teams' => $this->teamGoals($tournament, 'scored', $limit),
            'most_conceded_teams'   => $this->teamGoals($tournament, 'conceded', $limit),
            'fairplay_teams'        => $this->teamFairplay($tournament, $limit),
            'has_assist_data'       => $playerStats->where('assists', '>', 0)->isNotEmpty(),
        ];
    }

    /**
     * Agregasi event per pemain (hanya event yang punya player_id terdaftar).
     * Nama pemain & tim diambil via join agar tampil di tabel ranking.
     */
    private function playerEventAggregates(Tournament $tournament): Collection
    {
        return DB::table('match_events as e')
            ->join('matches as m', 'm.id', '=', 'e.match_id')
            ->join('tournament_team_players as p', 'p.id', '=', 'e.player_id')
            ->join('tournament_teams as tt', 'tt.id', '=', 'p.tournament_team_id')
            ->join('teams as t', 't.id', '=', 'tt.team_id')
            ->where('m.tournament_id', $tournament->id)
            ->whereNotNull('e.player_id')
            ->groupBy('p.id', 'p.player_name', 'p.shirt_number', 't.name')
            ->select([
                'p.id as player_id',
                'p.player_name',
                'p.shirt_number',
                't.name as team_name',
                DB::raw("SUM(e.event_type = 'goal' OR e.event_type = 'penalty_goal') as goals"),
                DB::raw("SUM(e.event_type = 'assist') as assists"),
                DB::raw("SUM(e.event_type = 'yellow_card') as yellow_cards"),
                DB::raw("SUM(e.event_type = 'red_card') as red_cards"),
            ])
            ->get()
            ->map(function ($row) {
                $row->goals = (int) $row->goals;
                $row->assists = (int) $row->assists;
                $row->yellow_cards = (int) $row->yellow_cards;
                $row->red_cards = (int) $row->red_cards;

                return $row;
            });
    }

    /**
     * Urutkan koleksi pemain berdasar satu metrik (desc), buang nilai 0,
     * dan ambil $limit teratas.
     */
    private function rank(Collection $players, string $metric, int $limit): Collection
    {
        return $players
            ->filter(fn ($p) => $p->{$metric} > 0)
            ->sortByDesc($metric)
            ->take($limit)
            ->values();
    }

    /**
     * Gol dicetak / kebobolan per tim, dihitung dari skor akhir match
     * (sumber otoritatif; sudah mencakup gol bunuh diri pada skor tim lawan).
     *
     * @param 'scored'|'conceded' $mode
     */
    private function teamGoals(Tournament $tournament, string $mode, int $limit): Collection
    {
        $matches = DB::table('matches')
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', self::SCORED_STATUSES)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        $totals = []; // tournament_team_id => total gol

        foreach ($matches as $match) {
            $homeId = $match->home_team_id;
            $awayId = $match->away_team_id;

            if ($mode === 'scored') {
                $totals[$homeId] = ($totals[$homeId] ?? 0) + (int) $match->home_score;
                $totals[$awayId] = ($totals[$awayId] ?? 0) + (int) $match->away_score;
            } else { // conceded
                $totals[$homeId] = ($totals[$homeId] ?? 0) + (int) $match->away_score;
                $totals[$awayId] = ($totals[$awayId] ?? 0) + (int) $match->home_score;
            }
        }

        $names = $this->teamNames($tournament, array_keys($totals));

        return collect($totals)
            ->map(fn ($total, $teamId) => (object) [
                'tournament_team_id' => $teamId,
                'team_name'          => $names[$teamId] ?? 'Tim',
                'goals'              => $total,
            ])
            ->sortByDesc('goals')
            ->take($limit)
            ->values();
    }

    /**
     * Akumulasi kartu (kuning + merah) per tim → fairplay paling sedikit di atas.
     * Hanya tim yang benar-benar bertanding (punya match) yang ditampilkan.
     */
    private function teamFairplay(Tournament $tournament, int $limit): Collection
    {
        // Hitung kartu per tim dari event (team_side relatif ke match).
        $rows = DB::table('match_events as e')
            ->join('matches as m', 'm.id', '=', 'e.match_id')
            ->where('m.tournament_id', $tournament->id)
            ->whereIn('e.event_type', ['yellow_card', 'red_card'])
            ->whereIn('e.team_side', ['home', 'away'])
            ->select([
                'm.home_team_id',
                'm.away_team_id',
                'e.team_side',
                'e.event_type',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('m.home_team_id', 'm.away_team_id', 'e.team_side', 'e.event_type')
            ->get();

        $cards = []; // team_id => ['yellow' => x, 'red' => y]

        foreach ($rows as $row) {
            $teamId = $row->team_side === 'home' ? $row->home_team_id : $row->away_team_id;
            if (! $teamId) {
                continue;
            }
            $cards[$teamId]['yellow'] = $cards[$teamId]['yellow'] ?? 0;
            $cards[$teamId]['red'] = $cards[$teamId]['red'] ?? 0;
            $cards[$teamId][$row->event_type === 'yellow_card' ? 'yellow' : 'red'] += (int) $row->total;
        }

        // Sertakan semua tim yang ikut bertanding (status final) walau 0 kartu.
        $playedTeamIds = $this->playedTeamIds($tournament);
        foreach ($playedTeamIds as $teamId) {
            $cards[$teamId]['yellow'] = $cards[$teamId]['yellow'] ?? 0;
            $cards[$teamId]['red'] = $cards[$teamId]['red'] ?? 0;
        }

        $names = $this->teamNames($tournament, array_keys($cards));

        return collect($cards)
            ->map(fn ($c, $teamId) => (object) [
                'tournament_team_id' => $teamId,
                'team_name'          => $names[$teamId] ?? 'Tim',
                'yellow_cards'       => $c['yellow'],
                'red_cards'          => $c['red'],
                'total_cards'        => $c['yellow'] + $c['red'],
            ])
            ->sortBy('total_cards')
            ->take($limit)
            ->values();
    }

    /**
     * ID tournament_team yang sudah bertanding (match berstatus final).
     */
    private function playedTeamIds(Tournament $tournament): array
    {
        $matches = DB::table('matches')
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', self::SCORED_STATUSES)
            ->get(['home_team_id', 'away_team_id']);

        $ids = [];
        foreach ($matches as $m) {
            if ($m->home_team_id) {
                $ids[$m->home_team_id] = true;
            }
            if ($m->away_team_id) {
                $ids[$m->away_team_id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Peta tournament_team_id => nama tim (dari relasi teams).
     */
    private function teamNames(Tournament $tournament, array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        return DB::table('tournament_teams as tt')
            ->join('teams as t', 't.id', '=', 'tt.team_id')
            ->whereIn('tt.id', $teamIds)
            ->pluck('t.name', 'tt.id')
            ->all();
    }
}
