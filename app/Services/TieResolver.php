<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

/**
 * Logika hasil tie babak gugur: mendukung single leg maupun Home & Away
 * (2 leg). Semua perhitungan dinormalisasi dari perspektif "deciding match"
 * (leg 2 pada tie 2 leg, atau match itu sendiri pada single leg) — ingat
 * bahwa tim home pada leg 2 adalah tim away pada leg 1.
 */
class TieResolver
{
    public const MODE_AGGREGATE = 'aggregate';
    public const MODE_WINS = 'wins';

    public function isKnockoutStage(TournamentMatch $match): bool
    {
        return in_array($match->stage_type, ['knockout', 'promotion_playoff', 'relegation_playoff'], true);
    }

    /**
     * Deciding match = match yang menutup tie: leg 2 pada tie 2 leg, atau
     * match knockout single leg (leg = null). Leg 1 tidak pernah menentukan.
     */
    public function isDecidingMatch(TournamentMatch $match): bool
    {
        return $this->isKnockoutStage($match) && ($match->leg === null || $match->leg === 2);
    }

    /**
     * Pasangan leg pada tie yang sama: [leg1, deciding]. Single leg → [null, $match].
     * $preloaded (koleksi match satu turnamen) menghindari query N+1.
     */
    public function getLegs(TournamentMatch $match, ?Collection $preloaded = null): array
    {
        if ($match->leg === null) {
            return [null, $match];
        }

        $sibling = $this->siblingLeg($match, $preloaded);

        return $match->leg === 1 ? [$match, $sibling] : [$sibling, $match];
    }

    public function siblingLeg(TournamentMatch $match, ?Collection $preloaded = null): ?TournamentMatch
    {
        if ($match->leg === null || $match->bracket_match_id === null) {
            return null;
        }

        if ($preloaded !== null) {
            return $preloaded->first(fn ($candidate) => $candidate->id !== $match->id
                && $candidate->stage_type === $match->stage_type
                && $candidate->bracket_match_id === $match->bracket_match_id
                && $candidate->leg !== null);
        }

        return TournamentMatch::where('tournament_id', $match->tournament_id)
            ->where('stage_type', $match->stage_type)
            ->where('bracket_match_id', $match->bracket_match_id)
            ->whereNotNull('leg')
            ->where('id', '!=', $match->id)
            ->first();
    }

    /**
     * Mode kalkulasi pemenang tie Home & Away dari pengaturan bracket.
     */
    public function calculationMode(Tournament $tournament): string
    {
        $setting = AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $mode = $setting->value['home_away_calculation'] ?? self::MODE_AGGREGATE;

        return in_array($mode, [self::MODE_AGGREGATE, self::MODE_WINS], true) ? $mode : self::MODE_AGGREGATE;
    }

    /**
     * Hitung hasil tie dari perspektif deciding match.
     *
     * - both_played: leg 1 (jika ada) sudah full_time DAN skor deciding terisi.
     * - agg_home/agg_away: agregat gol; pada leg 2, gol tim home = skor home
     *   leg 2 + skor away leg 1 (tim yang sama, sisi berbeda).
     * - wins_home/wins_away: jumlah leg yang dimenangkan (seri = 0).
     * - is_level: seri menurut $mode.
     * - winner_side: 'home'/'away' milik deciding match, null jika belum ada
     *   pemenang (seri tanpa penalti penentu).
     */
    public function tieOutcome(TournamentMatch $deciding, string $mode, ?Collection $preloaded = null): array
    {
        [$leg1] = $this->getLegs($deciding, $preloaded);

        $decidingHome = $deciding->home_score;
        $decidingAway = $deciding->away_score;

        $aggHome = (int) ($decidingHome ?? 0);
        $aggAway = (int) ($decidingAway ?? 0);
        $winsHome = 0;
        $winsAway = 0;

        if ($decidingHome !== null && $decidingAway !== null) {
            if ($decidingHome > $decidingAway) {
                $winsHome++;
            } elseif ($decidingAway > $decidingHome) {
                $winsAway++;
            }
        }

        $leg1Completed = $leg1 === null || $leg1->status === 'full_time';

        if ($leg1 !== null && $leg1->home_score !== null && $leg1->away_score !== null) {
            // Tim home deciding match = tim away leg 1, dan sebaliknya.
            $aggHome += (int) $leg1->away_score;
            $aggAway += (int) $leg1->home_score;

            if ($leg1->home_score > $leg1->away_score) {
                $winsAway++;
            } elseif ($leg1->away_score > $leg1->home_score) {
                $winsHome++;
            }
        }

        $isLevel = $mode === self::MODE_WINS
            ? $winsHome === $winsAway
            : $aggHome === $aggAway;

        $penHome = $deciding->home_penalty_score;
        $penAway = $deciding->away_penalty_score;
        $penDecides = $isLevel && $penHome !== null && $penAway !== null && $penHome !== $penAway;

        $winnerSide = null;
        if (! $isLevel) {
            if ($mode === self::MODE_WINS) {
                $winnerSide = $winsHome > $winsAway ? 'home' : 'away';
            } else {
                $winnerSide = $aggHome > $aggAway ? 'home' : 'away';
            }
        } elseif ($penDecides) {
            $winnerSide = $penHome > $penAway ? 'home' : 'away';
        }

        return [
            'both_played' => $leg1Completed && $decidingHome !== null && $decidingAway !== null,
            'agg_home' => $aggHome,
            'agg_away' => $aggAway,
            'wins_home' => $winsHome,
            'wins_away' => $winsAway,
            'is_level' => $isLevel,
            'winner_side' => $winnerSide,
            'pen_home' => $penHome,
            'pen_away' => $penAway,
            'pen_decides' => $penDecides,
        ];
    }

    /**
     * Pemenang tie sebagai {team_id, name} dari sisi deciding match.
     */
    public function winnerDescriptor(TournamentMatch $deciding, array $outcome): ?array
    {
        if ($outcome['winner_side'] === 'home') {
            $teamId = $deciding->home_team_id;
            $name = $deciding->homeTeam?->team?->name ?? $deciding->home_team_key ?? $deciding->source_home;
        } elseif ($outcome['winner_side'] === 'away') {
            $teamId = $deciding->away_team_id;
            $name = $deciding->awayTeam?->team?->name ?? $deciding->away_team_key ?? $deciding->source_away;
        } else {
            return null;
        }

        if ($teamId === null || $name === null) {
            return null;
        }

        return ['team_id' => $teamId, 'name' => $name];
    }

    public function needsPenaltyShootout(TournamentMatch $deciding, string $mode, ?Collection $preloaded = null): bool
    {
        if (! $this->isDecidingMatch($deciding)) {
            return false;
        }

        $outcome = $this->tieOutcome($deciding, $mode, $preloaded);

        return $outcome['both_played'] && $outcome['is_level'] && ! $outcome['pen_decides'];
    }
}
