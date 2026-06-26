<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentGroupSetting;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class MatchGenerator
{
    public function generateForTournament(Tournament $tournament): void
    {
        $tournament->load('groupSetting');

        if (! $tournament->groupSetting instanceof TournamentGroupSetting) {
            return;
        }

        $bracketSetting = $this->getBracketSetting($tournament);
        $bracketValue = $bracketSetting->value ?? [];

        $competitionType = $bracketValue['competition_type'] ?? 'tournament';
        $playoffOptions = $bracketValue['playoff_options'] ?? [];

        $matches = [];

        if ($competitionType === 'tournament') {
            // Mode turnamen = babak gugur murni tanpa fase grup.
            // Bracket dibangun langsung dari tim yang lolos verifikasi.
            $matches = array_merge(
                $matches,
                $this->buildKnockoutMatchesFromTeams($tournament, $bracketSetting)
            );
        } elseif ($competitionType === 'group_knockout') {
            // Mode Grup → Gugur (gaya Euro/UCL/Piala Dunia): fase grup
            // round-robin per grup, lalu bracket gugur berisi placeholder
            // (mis. A1, B2) yang akan diisi tim asli setelah fase grup selesai.
            $matches = array_merge(
                $matches,
                $this->buildGroupStageMatches($tournament),
                $this->buildGroupKnockoutBracket($tournament, $bracketSetting)
            );
        } elseif ($competitionType === 'league') {
            $matches = array_merge(
                $matches,
                $this->buildLeagueStageMatches($tournament)
            );
        } elseif ($competitionType === 'league_playoff') {
            $matches = array_merge(
                $matches,
                $this->buildLeagueStageMatches($tournament)
            );

            if (in_array('promotion', $playoffOptions, true)) {
                $matches = array_merge(
                    $matches,
                    $this->buildPlayoffMatches($tournament, $bracketValue, 'promotion')
                );
            }

            if (in_array('relegation', $playoffOptions, true)) {
                $matches = array_merge(
                    $matches,
                    $this->buildPlayoffMatches($tournament, $bracketValue, 'relegation')
                );
            }
        }

        Log::info('Generating tournament matches', [
            'tournament_id' => $tournament->id,
            'competition_type' => $competitionType,
            'playoff_options' => $playoffOptions,
            'matches_generated' => count($matches),
        ]);

        DB::transaction(function () use ($tournament, $matches) {
            TournamentMatch::where('tournament_id', $tournament->id)->delete();

            if (! empty($matches)) {
                TournamentMatch::insert($matches);
            }

            $this->attachBracketNextMatchIds($tournament);
        });
    }

    private function getBracketSetting(Tournament $tournament): AppSetting
    {
        $key = $this->bracketSettingsKey($tournament);
        $setting = AppSetting::firstOrCreate(
            ['key' => $key],
            ['value' => []]
        );

        return $setting;
    }

    private function bracketSettingsKey(Tournament $tournament): string
    {
        return 'tournament_' . $tournament->id . '_bracket_settings';
    }

    /**
     * Bangun pertandingan knockout langsung dari tim yang lolos verifikasi
     * (mode turnamen tanpa fase grup). Struktur bracket juga disinkronkan ke
     * pengaturan bracket agar halaman Pengaturan Slot Bracket dan Bracket
     * Gugur menampilkan bagan yang sama.
     */
    private function buildKnockoutMatchesFromTeams(Tournament $tournament, AppSetting $bracketSetting): array
    {
        $bracketValue = $bracketSetting->value ?? [];
        $teams = $this->approvedTeamDescriptors($tournament);

        $structure = count($teams) >= 2
            ? $this->buildBracketStructure(
                array_column($teams, 'key'),
                (bool) ($bracketValue['third_place'] ?? false)
            )
            : [];

        if (($bracketValue['matches'] ?? []) !== $structure) {
            $bracketValue['matches'] = $structure;
            $bracketSetting->update(['value' => $bracketValue]);
        }

        if (empty($structure)) {
            return [];
        }

        $rows = $this->buildBracketMatchesFromArray(
            $tournament,
            $structure,
            'knockout',
            null,
            $bracketValue['match_type'] ?? 'single'
        );

        $teamIdByName = [];
        foreach ($teams as $team) {
            $teamIdByName[$team['key']] = $team['id'];
        }

        foreach ($rows as &$row) {
            $row['home_team_id'] = $teamIdByName[$row['home_team_key']] ?? null;
            $row['away_team_id'] = $teamIdByName[$row['away_team_key']] ?? null;
        }
        unset($row);

        return $rows;
    }

    public function generateBracketStructureForTournament(Tournament $tournament): void
    {
        $tournament->load('groupSetting');

        $bracketSetting = $this->getBracketSetting($tournament);
        $bracketValue = $bracketSetting->value ?? [];
        $competitionType = $bracketValue['competition_type'] ?? 'tournament';

        // Untuk group_knockout, bracket (placeholder A1/B2/...) sudah dibangun
        // saat generateForTournament(); di sini tinggal di-skip karena sudah ada.
        if (! in_array($competitionType, ['tournament', 'group_knockout'], true)) {
            return;
        }

        $existingBracketMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('stage_type', 'knockout')
            ->exists();

        if ($existingBracketMatches) {
            return;
        }

        $matches = $this->buildBracketMatchesFromArray(
            $tournament,
            $bracketValue['matches'] ?? [],
            'knockout',
            null,
            $bracketValue['match_type'] ?? 'single'
        );

        if (empty($matches)) {
            return;
        }

        DB::transaction(function () use ($tournament, $matches) {
            TournamentMatch::insert($matches);
            $this->attachBracketNextMatchIds($tournament);
        });
    }

    /**
     * Daftar tim yang lolos verifikasi, diurutkan berdasarkan seed.
     */
    private function approvedTeamDescriptors(Tournament $tournament): array
    {
        $tournament->load(['tournamentTeams.team']);

        return $tournament->tournamentTeams
            ->filter(fn ($team) => ($team->team?->verification_status ?? 'pending') === 'approved')
            ->sortBy(fn ($team) => $team->seed ?? 0)
            ->map(function ($tournamentTeam) {
                return [
                    'id' => $tournamentTeam->id,
                    'key' => $tournamentTeam->team?->name ?? 'TBD',
                ];
            })
            ->values()
            ->all();
    }

    private function buildLeagueStageMatches(Tournament $tournament): array
    {
        $teamDescriptors = $this->approvedTeamDescriptors($tournament);

        if (count($teamDescriptors) < 2) {
            return [];
        }

        $rows = $this->buildRoundRobinMatchRows(
            $tournament,
            $teamDescriptors,
            'league',
            'League',
            'Matchday'
        );

        // R11 — Kompetisi penuh (double round robin / kandang-tandang):
        // mainkan setiap pasangan dua kali, putaran kedua membalik home/away.
        // Penomoran Matchday melanjutkan dari putaran pertama.
        $roundType = $tournament->groupSetting->league_round_type ?? 'single';
        if ($roundType === 'double') {
            $firstLegRoundCount = $this->countMatchdays($rows);
            $rows = array_merge($rows, $this->buildReversedLeg($rows, $firstLegRoundCount));
        }

        return $rows;
    }

    /**
     * Mode Grup → Gugur: round-robin per grup (stage_type 'group'). Tim
     * dikelompokkan berdasarkan group_label yang sudah ditetapkan (manual /
     * undian) pada tournament_teams. Tiap grup memakai prefix Matchday sendiri,
     * dan menghormati league_round_type (single/double) seperti mode liga.
     */
    private function buildGroupStageMatches(Tournament $tournament): array
    {
        $tournament->load(['tournamentTeams.team']);

        $teamsByGroup = $tournament->tournamentTeams
            ->filter(fn ($team) => ($team->team?->verification_status ?? 'pending') === 'approved')
            ->filter(fn ($team) => ! empty($team->group_label))
            ->sortBy(fn ($team) => $team->seed ?? 0)
            ->groupBy('group_label');

        $roundType = $tournament->groupSetting->league_round_type ?? 'single';
        $rows = [];

        foreach ($teamsByGroup->sortKeys() as $groupLabel => $groupTeams) {
            $teamDescriptors = $groupTeams->map(fn ($tt) => [
                'id' => $tt->id,
                'key' => $tt->team?->name ?? ('Tim ' . $tt->id),
            ])->values()->all();

            if (count($teamDescriptors) < 2) {
                continue;
            }

            $groupRows = $this->buildRoundRobinMatchRows(
                $tournament,
                $teamDescriptors,
                'group',
                (string) $groupLabel,
                'Matchday'
            );

            if ($roundType === 'double') {
                $firstLegRoundCount = $this->countMatchdays($groupRows);
                $groupRows = array_merge($groupRows, $this->buildReversedLeg($groupRows, $firstLegRoundCount));
            }

            $rows = array_merge($rows, $groupRows);
        }

        return $rows;
    }

    /**
     * Mode Grup → Gugur: bangun bracket gugur berisi placeholder posisi grup
     * (A1, B2, ...) memakai seeding silang juara × runner-up antar grup —
     * sehingga Juara Grup A bertemu Runner-up Grup B, dst. Struktur disimpan ke
     * pengaturan bracket agar halaman bracket menampilkan bagan yang sama.
     */
    private function buildGroupKnockoutBracket(Tournament $tournament, AppSetting $bracketSetting): array
    {
        $bracketValue = $bracketSetting->value ?? [];
        $groupCount = (int) ($tournament->groupSetting->group_count ?? 0);
        $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];

        $structure = $this->buildGroupKnockoutStructure(
            $groupCount,
            $qualifiedRanks,
            (bool) ($bracketValue['third_place'] ?? false)
        );

        if (($bracketValue['matches'] ?? []) !== $structure) {
            $bracketValue['matches'] = $structure;
            $bracketSetting->update(['value' => $bracketValue]);
        }

        if (empty($structure)) {
            return [];
        }

        return $this->buildBracketMatchesFromArray(
            $tournament,
            $structure,
            'knockout',
            null,
            $bracketValue['match_type'] ?? 'single'
        );
    }

    /**
     * Urutan placeholder posisi grup untuk bracket gugur dengan seeding silang
     * juara × runner-up (gaya Euro/Piala Dunia). Saat tiap grup meloloskan 2
     * tim (juara peringkat 1 & runner-up peringkat 2), juara grup ditempatkan
     * berurutan lalu disilangkan dengan runner-up grup pasangannya:
     * A1, B2, C1, D2, B1, A2, D1, C2, ... sehingga pasangan ronde pertama tidak
     * mempertemukan dua tim dari grup yang sama. Untuk jumlah lolos selain 2 per
     * grup, kembali ke urutan ranking standar (A1,A2,B1,B2,...).
     */
    private function crossGroupSeedPositions(int $groupCount, array $qualifiedRanks): array
    {
        $groupLabels = array_slice(
            ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'],
            0,
            max(0, $groupCount)
        );

        $ranks = array_values(array_unique(array_map('intval', $qualifiedRanks)));
        sort($ranks);

        // Pola silang hanya berlaku rapi untuk tepat 2 tim lolos per grup dan
        // jumlah grup genap. Selain itu pakai urutan ranking standar.
        if ($ranks !== [1, 2] || $groupCount < 2 || $groupCount % 2 !== 0) {
            $positions = [];
            foreach ($groupLabels as $group) {
                foreach ($ranks as $rank) {
                    $positions[] = $group . $rank;
                }
            }

            return $positions;
        }

        // Pasangkan grup berurutan: (A,B), (C,D), ... lalu silangkan.
        $positions = [];
        for ($i = 0; $i < $groupCount; $i += 2) {
            $first = $groupLabels[$i];
            $second = $groupLabels[$i + 1];

            $positions[] = $first . '1';   // Juara grup pertama
            $positions[] = $second . '2';  // Runner-up grup kedua
            $positions[] = $second . '1';  // Juara grup kedua
            $positions[] = $first . '2';   // Runner-up grup pertama
        }

        return $positions;
    }

    /**
     * Struktur bracket Grup → Gugur (placeholder posisi grup A1/B2/...) dengan
     * seeding silang juara × runner-up. Dipakai controller saat menyimpan
     * pengaturan bracket maupun saat (re)generasi match.
     */
    public function buildGroupKnockoutStructure(int $groupCount, array $qualifiedRanks, bool $includeThirdPlace = false): array
    {
        $positions = $this->crossGroupSeedPositions($groupCount, $qualifiedRanks);

        if (count($positions) < 2) {
            return [];
        }

        return $this->buildBracketStructure(
            $positions,
            $includeThirdPlace,
            true // preseeded: jaga urutan silang juara × runner-up
        );
    }

    /**
     * Hitung jumlah Matchday unik pada putaran pertama agar penomoran putaran
     * kedua menyambung (Matchday N+1, N+2, ...).
     */
    private function countMatchdays(array $rows): int
    {
        $names = [];
        foreach ($rows as $row) {
            $names[$row['round_name']] = true;
        }

        return count($names);
    }

    /**
     * Bangun putaran kedua double round robin: home/away dibalik dan Matchday
     * dilanjutkan dari putaran pertama.
     */
    private function buildReversedLeg(array $firstLegRows, int $offset): array
    {
        $reversed = [];

        foreach ($firstLegRows as $row) {
            // Geser nomor Matchday: "Matchday 1" -> "Matchday (offset+1)".
            $newRoundName = $row['round_name'];
            if (preg_match('/^(.*?)(\d+)$/', $row['round_name'], $m)) {
                $newRoundName = $m[1] . ((int) $m[2] + $offset);
            }

            $reversed[] = array_merge($row, [
                'round_name' => $newRoundName,
                'home_team_id' => $row['away_team_id'],
                'away_team_id' => $row['home_team_id'],
                'home_team_key' => $row['away_team_key'],
                'away_team_key' => $row['home_team_key'],
                'source_home' => $row['source_away'],
                'source_away' => $row['source_home'],
            ]);
        }

        return $reversed;
    }

    private function buildPlayoffMatches(Tournament $tournament, array $bracketValue, string $playoffType): array
    {
        $stageType = $playoffType === 'promotion' ? 'promotion_playoff' : 'relegation_playoff';
        $matchType = $bracketValue['match_type'] ?? 'single';
        $matches = [];

        if ($playoffType === 'promotion' && isset($bracketValue['matches_promotion'])) {
            $matches = $this->buildBracketMatchesFromArray(
                $tournament,
                $bracketValue['matches_promotion'],
                $stageType,
                'promotion',
                $matchType
            );
        } elseif ($playoffType === 'relegation' && isset($bracketValue['matches_relegation'])) {
            $matches = $this->buildBracketMatchesFromArray(
                $tournament,
                $bracketValue['matches_relegation'],
                $stageType,
                'relegation',
                $matchType
            );
        } elseif (isset($bracketValue['matches'])) {
            $matches = $this->buildBracketMatchesFromArray(
                $tournament,
                $bracketValue['matches'],
                $stageType,
                $playoffType,
                $matchType
            );
        }

        return $matches;
    }

    private function buildBracketMatchesFromArray(Tournament $tournament, array $bracketMatches, string $stageType, ?string $playoffType, string $matchType = 'single', array $extra = []): array
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($bracketMatches as $match) {
            $isBye = isset($match['is_bye']) ? (bool) $match['is_bye'] : false;
            $isThirdPlace = isset($match['is_third_place']) ? (bool) $match['is_third_place'] : false;
            $round = $match['round'] ?? 'Bracket';

            $baseRow = [
                'tournament_id' => $tournament->id,
                'bracket_match_id' => isset($match['id']) ? (int) $match['id'] : null,
                'next_bracket_match_id' => isset($match['next_match_id']) ? $match['next_match_id'] : null,
                'stage_type' => $stageType,
                'playoff_type' => $playoffType,
                'group_label' => null,
                'round_name' => $round,
                'home_team_id' => null,
                'away_team_id' => null,
                'home_team_key' => $match['left'] ?? null,
                'away_team_key' => $match['right'] ?? null,
                'source_home' => $match['left'] ?? null,
                'source_away' => $match['right'] ?? null,
                'is_bye' => $isBye,
                'is_third_place' => $isThirdPlace,
                'leg' => null,
                'status' => 'scheduled',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Mode Home & Away: dua leg per tie, kecuali Final dan Third Place
            // yang tetap satu pertandingan. Leg 2 dimainkan dengan home/away
            // dibalik.
            $isTwoLeg = $matchType === 'home_away'
                && $round !== 'Final'
                && ! $isThirdPlace
                && ! $isBye;

            if (! $isTwoLeg) {
                $rows[] = $baseRow;
                continue;
            }

            $rows[] = array_merge($baseRow, ['leg' => 1]);
            $rows[] = array_merge($baseRow, [
                'leg' => 2,
                'home_team_key' => $match['right'] ?? null,
                'away_team_key' => $match['left'] ?? null,
                'source_home' => $match['right'] ?? null,
                'source_away' => $match['left'] ?? null,
            ]);
        }

        return $rows;
    }

    private function buildRoundRobinMatchRows(Tournament $tournament, array $teamDescriptors, string $stageType, string $groupLabel, string $roundLabelPrefix): array
    {
        $rows = [];
        $rounds = $this->generateRoundRobinSchedule($teamDescriptors);
        $now = Carbon::now();

        foreach ($rounds as $roundIndex => $round) {
            $roundName = $roundLabelPrefix . ' ' . ($roundIndex + 1);

            foreach ($round as $match) {
                $home = $match['home'];
                $away = $match['away'];

                $homeTeamId = is_array($home) ? ($home['id'] ?? null) : null;
                $awayTeamId = is_array($away) ? ($away['id'] ?? null) : null;
                $homeTeamKey = is_array($home) ? ($home['key'] ?? ($home['name'] ?? null)) : $home;
                $awayTeamKey = is_array($away) ? ($away['key'] ?? ($away['name'] ?? null)) : $away;

                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'bracket_match_id' => null,
                    'next_bracket_match_id' => null,
                    'stage_type' => $stageType,
                    'playoff_type' => null,
                    'group_label' => $groupLabel,
                    'round_name' => $roundName,
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'home_team_key' => $homeTeamKey,
                    'away_team_key' => $awayTeamKey,
                    'source_home' => $homeTeamKey,
                    'source_away' => $awayTeamKey,
                    'is_bye' => $match['is_bye'],
                    'is_third_place' => false,
                    'leg' => null,
                    'status' => 'scheduled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    private function generateRoundRobinSchedule(array $teams): array
    {
        $teams = array_values($teams);

        if (count($teams) % 2 !== 0) {
            $teams[] = 'Bye';
        }

        $count = count($teams);
        $fixed = array_shift($teams);
        $rounds = [];
        $roundCount = $count - 1;

        for ($round = 0; $round < $roundCount; $round++) {
            $pairings = [];
            $teamList = array_merge([$fixed], $teams);
            $teamCount = count($teamList);

            for ($i = 0; $i < $teamCount / 2; $i++) {
                $home = $teamList[$i];
                $away = $teamList[$teamCount - 1 - $i];

                $pairings[] = [
                    'home' => $home,
                    'away' => $away,
                    'is_bye' => $home === 'Bye' || $away === 'Bye',
                ];
            }

            $rounds[] = $pairings;
            array_unshift($teams, array_pop($teams));
        }

        return $rounds;
    }

    /**
     * Bangun struktur bracket gugur dari daftar posisi/nama tim ($positions),
     * memakai seeding bracket standar. Posisi bisa berupa placeholder ("A1")
     * maupun nama tim asli (mode turnamen tanpa grup).
     *
     * Bila $preseeded = true, $positions dianggap sudah dalam urutan slot final
     * (pasangan ronde pertama = posisi 0×1, 2×3, ...) dan reorder seeding standar
     * dilewati. Dipakai mode Grup → Gugur agar pasangan silang juara × runner-up
     * antar grup tidak diacak ulang.
     */
    public function buildBracketStructure(array $positions, bool $includeThirdPlace = false, bool $preseeded = false): array
    {
        $positions = array_values($positions);
        $teamCount = count($positions);
        if ($teamCount === 0) {
            return [];
        }

        $slotCount = 1;
        while ($slotCount < $teamCount) {
            $slotCount *= 2;
        }

        // Susun tim ke slot bracket dengan urutan seeding standar, lalu isi
        // sisa slot dengan "Bye". Karena seed teratas menempati slot awal,
        // bye akan jatuh pada lawan seed teratas — sehingga seed teratas yang
        // melewati ronde pertama (mis. 3 tim: seed 1 langsung ke Final,
        // seed 2 vs seed 3 di Semifinal).
        $slots = array_fill(0, $slotCount, 'Bye');
        if ($preseeded) {
            // Pertahankan urutan apa adanya: slot[i] = positions[i].
            foreach ($positions as $slotIndex => $position) {
                $slots[$slotIndex] = $position;
            }
        } else {
            $seedOrder = $this->bracketSeedOrder($slotCount);
            foreach ($seedOrder as $slotIndex => $seedNumber) {
                if ($seedNumber <= $teamCount) {
                    $slots[$slotIndex] = $positions[$seedNumber - 1];
                }
            }
        }

        $matchId = 1;
        $matchesById = [];

        // Ronde pertama: satu card per pasangan slot. Slot yang berisi
        // (tim, Bye) tidak menghasilkan card — tim tersebut langsung
        // dipromosikan sebagai peserta pada card ronde berikutnya.
        $previousRound = [];
        $currentTeamCount = $slotCount;
        for ($i = 0; $i < $slotCount; $i += 2) {
            $left = $slots[$i];
            $right = $slots[$i + 1];

            // Pasangan dua-bye tidak mungkin (slot terisi seed teratas dulu),
            // tetapi tetap ditangani agar aman.
            if ($left === 'Bye' && $right === 'Bye') {
                $previousRound[] = ['advance' => 'Bye'];
                continue;
            }

            // Tim bye: lewati ronde ini, bawa tim nyata ke ronde berikutnya.
            if ($left === 'Bye' || $right === 'Bye') {
                $previousRound[] = ['advance' => $left === 'Bye' ? $right : $left];
                continue;
            }

            $match = [
                'id' => $matchId++,
                'round' => $this->roundLabel($currentTeamCount),
                'left' => $left,
                'right' => $right,
                'next_match_id' => null,
                'is_bye' => false,
                'is_third_place' => false,
            ];
            $matchesById[$match['id']] = $match;
            $previousRound[] = ['match_id' => $match['id']];
        }

        // Ronde-ronde berikutnya. Setiap entri $previousRound adalah salah satu
        // dari: ['match_id' => N] (peserta = pemenang match N) atau
        // ['advance' => 'A1'] (peserta = tim yang dipromosikan langsung).
        while (count($previousRound) > 1) {
            $currentTeamCount /= 2;
            $currentRound = [];

            for ($i = 0; $i < count($previousRound); $i += 2) {
                $leftSource = $previousRound[$i];
                $rightSource = $previousRound[$i + 1] ?? null;

                $resolveLabel = fn ($source) => isset($source['match_id'])
                    ? 'Pemenang M' . $source['match_id']
                    : ($source['advance'] ?? 'Bye');

                $match = [
                    'id' => $matchId++,
                    'round' => $this->roundLabel($currentTeamCount),
                    'left' => $resolveLabel($leftSource),
                    'right' => $rightSource ? $resolveLabel($rightSource) : 'Bye',
                    'next_match_id' => null,
                    'is_bye' => false,
                    'is_third_place' => false,
                ];
                $matchesById[$match['id']] = $match;

                if (isset($leftSource['match_id'])) {
                    $matchesById[$leftSource['match_id']]['next_match_id'] = $match['id'];
                }
                if ($rightSource && isset($rightSource['match_id'])) {
                    $matchesById[$rightSource['match_id']]['next_match_id'] = $match['id'];
                }

                $currentRound[] = ['match_id' => $match['id']];
            }

            $previousRound = $currentRound;
        }

        if ($includeThirdPlace) {
            $semifinalMatches = array_filter($matchesById, fn ($match) => $match['round'] === 'Semifinal');
            if (count($semifinalMatches) === 2) {
                $semiIds = array_values(array_map(fn ($match) => $match['id'], $semifinalMatches));
                $thirdPlaceMatch = [
                    'id' => $matchId++,
                    'round' => 'Third Place',
                    'left' => 'Runner-up M' . $semiIds[0],
                    'right' => 'Runner-up M' . $semiIds[1],
                    'next_match_id' => null,
                    'is_bye' => false,
                    'is_third_place' => true,
                ];
                $matchesById[$thirdPlaceMatch['id']] = $thirdPlaceMatch;
            }
        }

        return array_values($matchesById);
    }

    /**
     * Hitung posisi vertikal (px) tiap card bracket per kolom berdasarkan graf
     * feeder→next_match_id: card diletakkan di tengah antara card pengumpannya.
     * Dengan ini ronde pertama yang "jarang" (tim bye tidak punya card) tetap
     * tersusun rapi. Mengembalikan [indexKolom][indexMatch] => posisi top.
     */
    public static function computeBracketCardTops(array $bracketColumns, float $rowUnit): array
    {
        $topsByColumn = [];
        $topsById = [];

        foreach ($bracketColumns as $columnIndex => $column) {
            $cursor = 0;

            foreach (array_values($column['matches'] ?? []) as $matchIndex => $match) {
                $id = $match['id'] ?? null;
                $feederTops = [];

                if ($id !== null) {
                    for ($prev = 0; $prev < $columnIndex; $prev++) {
                        foreach ($bracketColumns[$prev]['matches'] ?? [] as $prevMatch) {
                            if (($prevMatch['next_match_id'] ?? null) == $id && isset($topsById[$prevMatch['id']])) {
                                $feederTops[] = $topsById[$prevMatch['id']];
                            }
                        }
                    }
                }

                $top = $feederTops !== [] ? (min($feederTops) + max($feederTops)) / 2 : $cursor;
                $top = max($top, $cursor); // hindari tumpang tindih dalam satu kolom

                if ($id !== null) {
                    $topsById[$id] = $top;
                }

                $topsByColumn[$columnIndex][$matchIndex] = $top;
                $cursor = $top + $rowUnit;
            }
        }

        return $topsByColumn;
    }

    /**
     * N14 — Pecah kolom bracket (urut ronde awal → Final) menjadi model
     * dua sisi (mirror) ala Piala Dunia: separuh match tiap ronde mengisi sisi
     * KIRI (mengerucut ke kanan), separuh lagi sisi KANAN (mengerucut ke kiri),
     * bertemu di FINAL yang berada di tengah.
     *
     * Strategi pembelahan: per-ronde 50/50. Untuk tiap ronde feeder, match
     * dibelah dua — paruh pertama ke kiri, paruh kedua ke kanan (kiri dapat
     * satu lebih banyak bila jumlahnya ganjil). Final (kolom terakhir, 1 match)
     * ditaruh di zona tengah.
     *
     * Tiap entri match diperkaya dengan:
     *   - 'column_index' : indeks kolom asli (untuk lookup $cardTops)
     *   - 'match_index'  : indeks match dalam kolom asli (untuk lookup $cardTops)
     *   - 'side'         : 'left' | 'right' | 'final'
     *
     * Return:
     *   [
     *     'enabled' => bool,            // false bila tak layak mirror (fallback)
     *     'left'    => [ kolom... ],    // urut ronde awal → mendekati final (L→R)
     *     'final'   => kolom|null,      // kolom final (1 match) di tengah
     *     'right'   => [ kolom... ],    // urut mendekati final → ronde awal (L→R)
     *   ]
     *
     * Struktur tiap "kolom" sama seperti input ($column['label'], 'teams',
     * 'matches'), dengan 'matches' yang sudah diperkaya field di atas.
     */
    public static function splitBracketColumnsMirror(array $bracketColumns): array
    {
        $disabled = ['enabled' => false, 'left' => [], 'final' => null, 'right' => []];

        $columnCount = count($bracketColumns);

        // Perlu minimal: 1 ronde feeder + Final (≥2 kolom) agar mirror bermakna.
        if ($columnCount < 2) {
            return $disabled;
        }

        $columns = array_values($bracketColumns);
        $finalColumn = $columns[$columnCount - 1];

        // Final harus tepat satu match agar bisa di tengah.
        if (count($finalColumn['matches'] ?? []) !== 1) {
            return $disabled;
        }

        $feederColumns = array_slice($columns, 0, $columnCount - 1);

        $leftColumns = [];
        $rightColumns = [];

        foreach ($feederColumns as $columnIndex => $column) {
            $matches = array_values($column['matches'] ?? []);
            $total = count($matches);

            if ($total === 0) {
                continue;
            }

            // Kiri dapat ceil(total/2), kanan sisanya.
            $leftCount = (int) ceil($total / 2);

            $leftMatches = [];
            $rightMatches = [];

            foreach ($matches as $matchIndex => $match) {
                $match['column_index'] = $columnIndex;
                $match['match_index'] = $matchIndex;

                if ($matchIndex < $leftCount) {
                    $match['side'] = 'left';
                    $leftMatches[] = $match;
                } else {
                    $match['side'] = 'right';
                    $rightMatches[] = $match;
                }
            }

            if ($leftMatches !== []) {
                $leftColumns[] = [
                    'label' => $column['label'],
                    'teams' => $column['teams'] ?? ($total * 2),
                    'matches' => $leftMatches,
                ];
            }

            if ($rightMatches !== []) {
                $rightColumns[] = [
                    'label' => $column['label'],
                    'teams' => $column['teams'] ?? ($total * 2),
                    'matches' => $rightMatches,
                ];
            }
        }

        // Butuh kedua sisi terisi; jika salah satu kosong (mis. hanya 1 match
        // di ronde pertama), mirror tak seimbang → fallback ke satu arah.
        if ($leftColumns === [] || $rightColumns === []) {
            return $disabled;
        }

        // Sisi kanan dirender dari ronde yang paling dekat Final (paling kanan
        // dalam urutan feeder) menuju ronde awal — supaya mengerucut ke tengah.
        $rightColumns = array_reverse($rightColumns);

        $finalMatches = [];
        foreach (array_values($finalColumn['matches'] ?? []) as $matchIndex => $match) {
            $match['column_index'] = $columnCount - 1;
            $match['match_index'] = $matchIndex;
            $match['side'] = 'final';
            $finalMatches[] = $match;
        }

        return [
            'enabled' => true,
            'left' => $leftColumns,
            'final' => [
                'label' => $finalColumn['label'],
                'teams' => $finalColumn['teams'] ?? 2,
                'matches' => $finalMatches,
            ],
            'right' => $rightColumns,
        ];
    }

    /**
     * N14 — Hitung posisi vertikal (top px) kartu untuk layout mirror, sehingga
     * bagan benar-benar mengerucut ke PUSAT (tengah horizontal & vertikal) ala
     * Piala Dunia: ronde terluar menyebar penuh atas→bawah, tiap ronde lebih
     * dalam berada di tengah dua pengumpangnya, dan FINAL tepat di tengah kanvas.
     *
     * Input $mirror = hasil splitBracketColumnsMirror (enabled=true).
     *
     * Return:
     *   [
     *     'left'   => [ localColumnIndex => [ localMatchIndex => top ] ],
     *     'right'  => [ localColumnIndex => [ localMatchIndex => top ] ],
     *     'final'  => top,            // posisi top kartu final (tengah)
     *     'height' => canvasHeight,   // tinggi kanvas total
     *   ]
     *
     * Catatan: kolom sisi KIRI urut ronde awal→dalam (index 0 = terluar).
     * Kolom sisi KANAN sudah ter-reverse di split (index 0 = paling dekat final,
     * paling dalam), jadi ronde terluar kanan ada di index terakhir.
     */
    public static function computeMirrorCardTops(array $mirror, float $rowUnit, float $cardHeight): array
    {
        // Hitung tops satu sisi. $columnsOutwardFirst: kolom urut dari ronde
        // TERLUAR (paling banyak match) ke ronde TERDALAM (mendekati final).
        $computeSide = function (array $columnsOutwardFirst) use ($rowUnit): array {
            $tops = []; // [colIdx => [matchIdx => top]]

            // Ronde terluar: sebar merata berjarak rowUnit.
            $outer = $columnsOutwardFirst[0]['matches'] ?? [];
            foreach (array_values($outer) as $i => $_) {
                $tops[0][$i] = $i * $rowUnit;
            }

            // Ronde berikutnya: tiap kartu = tengah dua pengumpannya (berurutan
            // berpasangan dari ronde sebelumnya). Bila ganjil, sisa kartu mengikuti
            // pengumpan tunggalnya.
            for ($c = 1; $c < count($columnsOutwardFirst); $c++) {
                $prevTops = array_values($tops[$c - 1] ?? []);
                $curMatches = array_values($columnsOutwardFirst[$c]['matches'] ?? []);

                foreach ($curMatches as $i => $_) {
                    $a = $prevTops[$i * 2] ?? null;
                    $b = $prevTops[$i * 2 + 1] ?? $a;

                    if ($a === null) {
                        // fallback: lanjut menumpuk
                        $a = ($i > 0 ? $tops[$c][$i - 1] + $rowUnit : 0);
                        $b = $a;
                    }

                    $tops[$c][$i] = ($a + $b) / 2;
                }
            }

            return $tops;
        };

        // Sisi kiri: index 0 sudah terluar → langsung.
        $leftTops = $computeSide($mirror['left']);

        // Sisi kanan: index 0 = terdalam (sudah reverse di split). Untuk
        // perhitungan, balik dulu agar terluar di depan, lalu petakan kembali.
        $rightOutwardFirst = array_reverse($mirror['right']);
        $rightTopsOutward = $computeSide($rightOutwardFirst);
        // Kembalikan ke indeks asli sisi kanan (0 = terdalam).
        $rightCount = count($mirror['right']);
        $rightTops = [];
        foreach ($rightTopsOutward as $outIdx => $matchTops) {
            $rightTops[$rightCount - 1 - $outIdx] = $matchTops;
        }

        // Final = tengah antara kartu terdalam kiri & kanan (keduanya simetris).
        $leftDeepest = end($leftTops);                 // ronde terdalam kiri
        $rightDeepest = $rightTops[0] ?? [];           // ronde terdalam kanan (idx 0)
        $leftMid = $leftDeepest ? array_sum($leftDeepest) / count($leftDeepest) : 0;
        $rightMid = $rightDeepest ? array_sum($rightDeepest) / count($rightDeepest) : 0;
        $finalTop = ($leftMid + $rightMid) / 2;

        // Tinggi kanvas: dari semua tops + tinggi kartu.
        $maxTop = $finalTop;
        foreach ([$leftTops, $rightTops] as $sideTops) {
            foreach ($sideTops as $matchTops) {
                foreach ($matchTops as $t) {
                    $maxTop = max($maxTop, $t);
                }
            }
        }
        $height = $maxTop + $cardHeight;

        return [
            'left' => $leftTops,
            'right' => $rightTops,
            'final' => $finalTop,
            'height' => $height,
        ];
    }

    /**
     * Urutan seed standar untuk bracket berukuran $slotCount (pangkat 2).
     * Mengembalikan array nomor seed (1 = unggulan teratas) sesuai posisi slot,
     * sehingga seed teratas bertemu seed terlemah dan bye selalu jatuh pada
     * lawan seed teratas. Contoh slotCount=4 → [1, 4, 3, 2].
     */
    private function bracketSeedOrder(int $slotCount): array
    {
        $order = [1];
        while (count($order) < $slotCount) {
            $rounds = count($order) * 2;
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $rounds + 1 - $seed;
            }
            $order = $next;
        }

        return $order;
    }

    private function roundLabel(int $teamCount): string
    {
        return match ($teamCount) {
            2 => 'Final',
            4 => 'Semifinal',
            8 => 'Quarterfinal',
            16 => 'Round of 16',
            32 => 'Round of 32',
            default => "Round of {$teamCount}",
        };
    }

    private function attachBracketNextMatchIds(Tournament $tournament): void
    {
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('next_bracket_match_id')
            ->get();

        // Mapping per stage_type, dan hanya row "entry point" tie (single
        // match atau leg 1) — pada mode Home & Away satu bracket_match_id
        // punya dua row sehingga pluck biasa akan bentrok. next_match_id
        // dipakai untuk navigasi bracket; advancement pemenang memakai
        // next_bracket_match_id agar bisa mengisi kedua leg tie berikutnya.
        $mapping = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
            ->where(function ($query) {
                $query->whereNull('leg')->orWhere('leg', 1);
            })
            ->get()
            ->mapWithKeys(fn ($match) => [
                $match->stage_type . '#' . $match->bracket_match_id => $match->id,
            ])
            ->toArray();

        foreach ($matches as $match) {
            $nextBracketId = $match->next_bracket_match_id;
            $mappingKey = $match->stage_type . '#' . $nextBracketId;
            if ($nextBracketId !== null && isset($mapping[$mappingKey])) {
                $match->next_match_id = $mapping[$mappingKey];
                $match->save();
            }
        }
    }

}
