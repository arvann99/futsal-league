<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Tournament;
use App\Models\TournamentMatch;

/**
 * N4 — sumber tunggal data tampilan bracket (read-only) agar bisa dipakai
 * baik oleh panel Admin maupun portal Official tanpa menduplikasi logika
 * skor/agregat/penalti. Ringkasan skor per kartu bracket dibangun via
 * TieResolver supaya konsisten dengan penentuan pemenang.
 */
class BracketViewService
{
    public function __construct(private TieResolver $tieResolver)
    {
    }

    public function bracketSettingsKey(Tournament $tournament): string
    {
        return 'tournament_' . $tournament->id . '_bracket_settings';
    }

    public function bracketSetting(Tournament $tournament): ?AppSetting
    {
        return AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
    }

    /**
     * Match yang sudah ter-assign (punya bracket_match_id), dikunci per
     * bracket_match_id memakai row leg 1 / single agar orientasi home/away
     * kartu tidak terbalik. $playoffMode mempersempit ke stage promosi/degradasi.
     */
    public function assignedMatches(Tournament $tournament, ?string $playoffMode = null)
    {
        return TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
            ->where(function ($query) {
                $query->whereNull('leg')->orWhere('leg', 1);
            })
            ->when($playoffMode !== null, function ($query) use ($playoffMode) {
                $query->where('stage_type', $playoffMode === 'relegation' ? 'relegation_playoff' : 'promotion_playoff');
            })
            ->get()
            ->keyBy('bracket_match_id');
    }

    /**
     * Ringkasan skor per kartu bracket (single / 2-leg / adu penalti).
     * Dipindahkan dari TournamentController agar reusable.
     */
    public function scoreSummaries(Tournament $tournament, ?string $playoffMode = null): array
    {
        $stageTypes = $playoffMode === null
            ? ['knockout', 'promotion_playoff', 'relegation_playoff']
            : [$playoffMode === 'relegation' ? 'relegation_playoff' : 'promotion_playoff'];

        $matches = TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage_type', $stageTypes)
            ->whereNotNull('bracket_match_id')
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $mode = $this->tieResolver->calculationMode($tournament);
        $summaries = [];

        // Kelompokkan per (stage_type, bracket_match_id); satu tie bisa 1 row
        // (single) atau 2 row (Home & Away). Pakai deciding match sebagai dasar.
        foreach ($matches->groupBy(fn ($m) => $m->stage_type . '#' . $m->bracket_match_id) as $rows) {
            $deciding = $rows->firstWhere('leg', 2) ?? $rows->firstWhere('leg', null) ?? $rows->first();
            if (! $deciding) {
                continue;
            }

            $bracketId = $deciding->bracket_match_id;
            $isTwoLeg = $rows->contains(fn ($m) => $m->leg !== null);
            $leg1 = $isTwoLeg ? $rows->firstWhere('leg', 1) : null;

            $outcome = $this->tieResolver->tieOutcome($deciding, $mode, $matches);

            $legs = [];
            if ($isTwoLeg) {
                if ($leg1) {
                    $legs[] = ['home' => $leg1->home_score, 'away' => $leg1->away_score];
                }
                $legs[] = ['home' => $deciding->away_score, 'away' => $deciding->home_score];
            }

            $summaries[$bracketId] = [
                'played' => (bool) $outcome['both_played'],
                'two_leg' => $isTwoLeg,
                'home' => [
                    'score' => $isTwoLeg ? $outcome['agg_home'] : $deciding->home_score,
                    'pen' => $outcome['pen_home'],
                ],
                'away' => [
                    'score' => $isTwoLeg ? $outcome['agg_away'] : $deciding->away_score,
                    'pen' => $outcome['pen_away'],
                ],
                'legs' => $legs,
                'pen_decides' => (bool) $outcome['pen_decides'],
                'winner_side' => $outcome['winner_side'],
            ];
        }

        return $summaries;
    }

    /**
     * Susun kolom-kolom bracket (ronde) dari struktur `matches` pada bracket
     * settings, plus posisi vertikal tiap kartu via computeBracketCardTops.
     * Mengembalikan: columns, thirdPlaceRound, cardTops, canvasHeight, rowUnit,
     * headerHeight. Dipakai view read-only agar tidak mengulang kalkulasi.
     */
    public function columns(array $settingValue, ?string $playoffMode = null): array
    {
        // Saat both, view admin memetakan matches_{mode} → matches. Lakukan hal
        // sama di sini agar Official konsisten.
        $rawMatches = $settingValue['matches'] ?? [];
        if ($playoffMode !== null) {
            $modeKey = $playoffMode === 'relegation' ? 'matches_relegation' : 'matches_promotion';
            $rawMatches = $settingValue[$modeKey] ?? $rawMatches;
        }

        $matches = [];
        foreach ($rawMatches as $index => $match) {
            $match['index'] = $index;
            $matches[] = $match;
        }

        $roundIndex = [];
        foreach ($matches as $match) {
            $label = $match['round'] ?? 'Unknown Round';
            $roundIndex[$label][] = $match;
        }

        $thirdPlaceRound = null;
        $rounds = [];
        foreach ($roundIndex as $label => $matchGroup) {
            if ($label === 'Third Place') {
                $thirdPlaceRound = [
                    'label' => 'Third Place',
                    'matches' => $matchGroup,
                    'teams' => count($matchGroup) * 2,
                ];
                continue;
            }

            $rounds[] = [
                'label' => $label,
                'matches' => $matchGroup,
                'teams' => count($matchGroup) * 2,
            ];
        }

        $finalRound = [];
        if (! empty($rounds)) {
            $finalRound = array_pop($rounds);
        }

        $columns = $rounds;
        if (! empty($finalRound)) {
            $columns[] = $finalRound;
        }

        $cardHeight = 120;
        $cardGap = 120;
        $rowUnit = $cardHeight + $cardGap;
        $headerHeight = 38;

        $cardTops = MatchGenerator::computeBracketCardTops($columns, $rowUnit);
        $canvasHeight = $headerHeight;
        foreach ($cardTops as $columnTops) {
            foreach ($columnTops as $topValue) {
                $canvasHeight = max($canvasHeight, $topValue + $rowUnit + $headerHeight);
            }
        }

        return [
            'columns' => $columns,
            'thirdPlaceRound' => $thirdPlaceRound,
            'cardTops' => $cardTops,
            'canvasHeight' => $canvasHeight,
            'rowUnit' => $rowUnit,
            'headerHeight' => $headerHeight,
        ];
    }

    /**
     * Tentukan playoffMode efektif (atau null) untuk satu turnamen berdasarkan
     * tipe kompetisi & opsi playoff, ATAU false bila turnamen tidak memakai
     * babak gugur sama sekali (liga murni / league_playoff tanpa opsi aktif).
     *
     * @return string|null|false  string mode | null (tournament) | false (no bracket)
     */
    private function resolvePlayoffMode(array $settingValue)
    {
        $competitionType = $settingValue['competition_type'] ?? 'tournament';
        $playoffOptions = $settingValue['playoff_options'] ?? [];

        // Liga murni tidak punya bracket.
        if ($competitionType === 'league') {
            return false;
        }

        if ($competitionType === 'league_playoff') {
            $hasPromotion = in_array('promotion', $playoffOptions, true);
            $hasRelegation = in_array('relegation', $playoffOptions, true);

            // Tanpa opsi playoff → tak ada bracket.
            if (! $hasPromotion && ! $hasRelegation) {
                return false;
            }

            // Kedua opsi → default promosi.
            return $hasPromotion ? 'promotion' : 'relegation';
        }

        return null;
    }

    /**
     * Apakah turnamen memiliki bagan gugur yang bisa dirender? Murah — untuk
     * menentukan tampil/tidaknya menu/tab Bracket. Konsisten dengan buildBracket().
     */
    public function hasRenderableBracket(Tournament $tournament): bool
    {
        return $this->buildBracket($tournament) !== null;
    }

    /**
     * Susun seluruh data tampilan bracket satu turnamen (read-only), atau null
     * bila turnamen tidak memakai babak gugur / belum punya struktur. Sumber
     * tunggal yang dipakai portal Official & Public agar gate tab dan render
     * tidak pernah berbeda.
     */
    public function buildBracket(Tournament $tournament): ?array
    {
        $setting = $this->bracketSetting($tournament);
        $settingValue = $setting?->value ?? [];

        $playoffMode = $this->resolvePlayoffMode($settingValue);
        if ($playoffMode === false) {
            return null;
        }

        $built = $this->columns($settingValue, $playoffMode);
        if (empty($built['columns'])) {
            return null;
        }

        return [
            'tournament' => $tournament,
            'competition_type' => $settingValue['competition_type'] ?? 'tournament',
            'columns' => $built['columns'],
            'third_place_round' => $built['thirdPlaceRound'],
            'card_tops' => $built['cardTops'],
            'canvas_height' => $built['canvasHeight'],
            'row_unit' => $built['rowUnit'],
            'header_height' => $built['headerHeight'],
            'assigned_matches' => $this->assignedMatches($tournament, $playoffMode),
            'scores' => $this->scoreSummaries($tournament, $playoffMode),
        ];
    }
}
