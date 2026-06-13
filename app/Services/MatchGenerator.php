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

        if ($competitionType !== 'tournament') {
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

        return $this->buildRoundRobinMatchRows(
            $tournament,
            $teamDescriptors,
            'league',
            'League',
            'Matchday'
        );
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
     */
    public function buildBracketStructure(array $positions, bool $includeThirdPlace = false): array
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
        $seedOrder = $this->bracketSeedOrder($slotCount);
        $slots = array_fill(0, $slotCount, 'Bye');
        foreach ($seedOrder as $slotIndex => $seedNumber) {
            if ($seedNumber <= $teamCount) {
                $slots[$slotIndex] = $positions[$seedNumber - 1];
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
