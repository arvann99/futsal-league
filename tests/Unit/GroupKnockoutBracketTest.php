<?php

namespace Tests\Unit;

use App\Services\MatchGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Menguji App\Services\MatchGenerator::buildGroupKnockoutStructure() — bracket
 * mode Grup → Gugur (placeholder posisi grup A1/B2/...).
 *
 * Regresi yang dijaga: jalur preseeded dulu mengisi slot urut dari atas apa
 * adanya, sehingga saat jumlah tim lolos BUKAN pangkat 2 seluruh bye menumpuk
 * di dasar bracket: muncul match "Bye vs Bye", ada tim yang melaju berronde-
 * ronde tanpa bertanding, dan layout mirror miring dengan konektor menyilang
 * antar sisi (keluhan bagan 3 grup × 3 lolos). Kini bracket ber-bye memakai
 * jalur kumpul-bye dengan prioritas per PERINGKAT: semua juara grup mendapat
 * bye lebih dulu, lalu runner-up, dst. — tim peringkat terbawah yang main di
 * ronde play-in.
 */
class GroupKnockoutBracketTest extends TestCase
{
    private function structure(int $groupCount, array $ranks): array
    {
        return (new MatchGenerator)->buildGroupKnockoutStructure($groupCount, $ranks);
    }

    /**
     * Semua posisi grup yang diharapkan (A1, A2, ..., per grup × per rank).
     */
    private function expectedPositions(int $groupCount, array $ranks): array
    {
        $labels = array_slice(range('A', 'P'), 0, $groupCount);
        $positions = [];
        foreach ($labels as $group) {
            foreach ($ranks as $rank) {
                $positions[] = $group . $rank;
            }
        }

        return $positions;
    }

    /**
     * Ambil semua slot (left/right) berbentuk posisi grup dari struktur.
     */
    private function positionSlots(array $structure): array
    {
        $slots = [];
        foreach ($structure as $match) {
            foreach (['left', 'right'] as $side) {
                if (preg_match('/^[A-P]\d+$/', (string) $match[$side])) {
                    $slots[] = $match[$side];
                }
            }
        }

        return $slots;
    }

    /**
     * Kelompokkan match per label ronde, urutan kemunculan dipertahankan.
     */
    private function rounds(array $structure): array
    {
        $rounds = [];
        foreach ($structure as $match) {
            $rounds[$match['round']][] = $match;
        }

        return $rounds;
    }

    /**
     * "Bye" hanya boleh muncul sebagai slot KANAN pada card ronde pertama yang
     * ber-flag is_bye (tim vs Bye) — tim itu otomatis lolos. Tidak boleh ada
     * "Bye vs Bye", tidak boleh "Bye" di ronde mana pun setelah ronde pertama.
     */
    #[DataProvider('configProvider')]
    public function test_bye_only_as_first_round_card(int $groupCount, array $ranks): void
    {
        $structure = $this->structure($groupCount, $ranks);
        $firstRoundLabel = array_key_first($this->rounds($structure));

        foreach ($structure as $match) {
            $this->assertNotSame('Bye', $match['left'], "Match {$match['id']}: sisi kiri tak boleh Bye (tim selalu di kiri).");

            if ($match['right'] === 'Bye') {
                $this->assertTrue((bool) ($match['is_bye'] ?? false), "Match {$match['id']}: slot Bye harus pada card is_bye.");
                $this->assertSame($firstRoundLabel, $match['round'], "Match {$match['id']}: card bye hanya di ronde pertama.");
                $this->assertMatchesRegularExpression('/^[A-P]\d+$/', (string) $match['left'], "Match {$match['id']}: card bye harus (posisi grup vs Bye).");
            }
        }
    }

    /**
     * Setiap posisi grup muncul TEPAT satu kali di RONDE PERTAMA (baik di card
     * main maupun card bye) — tidak hilang, tidak ganda, tak ada posisi asing.
     * Kemunculan di ronde berikutnya adalah propagasi (tim bye melaju), bukan
     * penempatan awal, jadi tak dihitung di sini.
     */
    #[DataProvider('configProvider')]
    public function test_every_position_appears_exactly_once(int $groupCount, array $ranks): void
    {
        $expected = $this->expectedPositions($groupCount, $ranks);
        $structure = $this->structure($groupCount, $ranks);
        $firstRoundLabel = array_key_first($this->rounds($structure));
        $firstRound = array_filter($structure, fn ($m) => $m['round'] === $firstRoundLabel);
        $actual = $this->positionSlots(array_values($firstRound));

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual, "Posisi grup harus muncul tepat sekali ({$groupCount} grup × ranks " . implode(',', $ranks) . ').');
    }

    /**
     * Bye dibagikan per PERINGKAT: tidak boleh ada tim peringkat lebih baik
     * yang dipaksa main play-in sementara tim peringkat lebih buruk dapat bye.
     * (Dalam peringkat yang sama, urutan abjad grup yang memutuskan — wajar.)
     */
    #[DataProvider('nonPowerOfTwoConfigProvider')]
    public function test_byes_prioritize_best_ranks(int $groupCount, array $ranks): void
    {
        $structure = $this->structure($groupCount, $ranks);
        $rounds = $this->rounds($structure);
        $firstRoundLabel = array_key_first($rounds);

        $rankOf = fn (string $position) => (int) substr($position, 1);

        // Ronde pertama kini penuh: card is_bye = penerima bye (tim di kiri),
        // card non-bye = dua tim yang bermain (play-in).
        $playRanks = [];
        $byeRanks = [];
        foreach ($rounds[$firstRoundLabel] as $match) {
            if (! empty($match['is_bye'])) {
                if (preg_match('/^[A-P]\d+$/', (string) $match['left'])) {
                    $byeRanks[] = $rankOf($match['left']);
                }

                continue;
            }
            foreach (['left', 'right'] as $side) {
                if (preg_match('/^[A-P]\d+$/', (string) $match[$side])) {
                    $playRanks[] = $rankOf($match[$side]);
                }
            }
        }

        $this->assertNotEmpty($playRanks, 'Bracket ber-bye harus punya match play-in.');
        $this->assertNotEmpty($byeRanks, 'Bracket ber-bye harus punya penerima bye.');

        $this->assertGreaterThanOrEqual(
            max($byeRanks),
            min($playRanks),
            "Tim peringkat lebih baik tak boleh play-in sementara peringkat lebih buruk bye ({$groupCount} grup × ranks " . implode(',', $ranks) . ').'
        );
    }

    /**
     * Tidak ada match yang PASTI mempertemukan dua posisi dari grup yang sama
     * (mis. C1 vs C2) — baik di play-in maupun antar penerima bye.
     */
    #[DataProvider('configProvider')]
    public function test_no_certain_same_group_pairing(int $groupCount, array $ranks): void
    {
        foreach ($this->structure($groupCount, $ranks) as $match) {
            if (preg_match('/^([A-P])\d+$/', (string) $match['left'], $left)
                && preg_match('/^([A-P])\d+$/', (string) $match['right'], $right)) {
                $this->assertNotSame(
                    $left[1],
                    $right[1],
                    "Match {$match['id']} ({$match['left']} vs {$match['right']}) mempertemukan grup yang sama ({$groupCount} grup)."
                );
            }
        }
    }

    /**
     * Jumlah tim lolos pangkat 2: pola silang juara × runner-up (A1vB2, B1vA2,
     * ...) harus dipertahankan persis — jalur preseeded tidak boleh berubah.
     */
    public function test_power_of_two_keeps_cross_seeding(): void
    {
        $expectations = [
            2 => [['A1', 'B2'], ['B1', 'A2']],
            4 => [['A1', 'B2'], ['B1', 'A2'], ['C1', 'D2'], ['D1', 'C2']],
            8 => [
                ['A1', 'B2'], ['B1', 'A2'], ['C1', 'D2'], ['D1', 'C2'],
                ['E1', 'F2'], ['F1', 'E2'], ['G1', 'H2'], ['H1', 'G2'],
            ],
        ];

        foreach ($expectations as $groupCount => $expectedPairs) {
            $structure = $this->structure($groupCount, [1, 2]);
            $rounds = $this->rounds($structure);
            $firstRound = $rounds[array_key_first($rounds)];

            $actualPairs = array_map(
                fn ($match) => [$match['left'], $match['right']],
                array_values($firstRound)
            );

            $this->assertSame(
                $expectedPairs,
                $actualPairs,
                "Seeding silang {$groupCount} grup × [1,2] harus dipertahankan persis."
            );
        }
    }

    /**
     * Konfigurasi persis keluhan client: 3 grup × 3 lolos = 9 tim. Ronde pertama
     * PENUH (8 card, padding ke 16 slot): satu card play-in dua tim peringkat 3
     * beda grup, tujuh card bye (tim vs Bye). Setiap slot Quarterfinal terisi
     * (pemenang play-in atau tim bye) — bagan simetris tanpa card menggantung.
     */
    public function test_three_groups_three_qualifiers_shape(): void
    {
        $structure = $this->structure(3, [1, 2, 3]);
        $rounds = $this->rounds($structure);

        $this->assertSame(
            ['Round of 16', 'Quarterfinal', 'Semifinal', 'Final'],
            array_keys($rounds)
        );
        $this->assertCount(8, $rounds['Round of 16'], 'Ronde pertama penuh: 8 card (1 play-in + 7 bye).');
        $this->assertCount(4, $rounds['Quarterfinal']);
        $this->assertCount(2, $rounds['Semifinal']);
        $this->assertCount(1, $rounds['Final']);

        $byeCards = array_filter($rounds['Round of 16'], fn ($m) => ! empty($m['is_bye']));
        $playCards = array_filter($rounds['Round of 16'], fn ($m) => empty($m['is_bye']));
        $this->assertCount(7, $byeCards, 'Tujuh card bye untuk 9 tim (16 slot − 9 tim = 7 bye).');
        $this->assertCount(1, $playCards, 'Satu card play-in.');

        // Play-in: dua tim peringkat 3 dari grup berbeda.
        $playIn = array_values($playCards)[0];
        $this->assertMatchesRegularExpression('/^[A-C]3$/', $playIn['left']);
        $this->assertMatchesRegularExpression('/^[A-C]3$/', $playIn['right']);

        // Tepat satu slot Quarterfinal menunggu pemenang play-in; slot QF lain
        // terisi tim bye yang melaju langsung (tak ada slot kosong/Bye).
        $winnerSlots = 0;
        foreach ($rounds['Quarterfinal'] as $match) {
            foreach (['left', 'right'] as $side) {
                if ($match[$side] === 'Pemenang M' . $playIn['id']) {
                    $winnerSlots++;
                }
                $this->assertNotSame('Bye', $match[$side], "Quarterfinal {$match['id']} tak boleh punya slot Bye.");
            }
        }
        $this->assertSame(1, $winnerSlots);
    }

    /**
     * Layout mirror: konektor tidak boleh menyilang antar sisi — setiap match
     * harus mengumpan ke match di SISI YANG SAMA atau ke Final. Regresi bagan
     * client: match sisi kanan mengumpan ke Quarterfinal sisi kiri sehingga
     * garisnya melintasi seluruh bagan di bawah kartu Final.
     */
    #[DataProvider('nonPowerOfTwoConfigProvider')]
    public function test_mirror_connectors_stay_on_their_side(int $groupCount, array $ranks): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns($groupCount, $ranks));

        $this->assertTrue($mirror['enabled'], "Bracket {$groupCount} grup harus layak mirror.");
        $this->assertCount(count($mirror['left']), $mirror['right'], 'Jumlah kolom kiri & kanan harus sama.');

        $finalId = $mirror['final']['matches'][0]['id'];

        $sideById = [];
        foreach (['left', 'right'] as $side) {
            foreach ($mirror[$side] as $column) {
                foreach ($column['matches'] as $match) {
                    $sideById[$match['id']] = $side;
                }
            }
        }

        foreach ($sideById as $id => $side) {
            $target = null;
            foreach (['left', 'right'] as $s) {
                foreach ($mirror[$s] as $column) {
                    foreach ($column['matches'] as $match) {
                        if ($match['id'] === $id) {
                            $target = $match['next_match_id'];
                        }
                    }
                }
            }

            if ($target === null || $target === $finalId) {
                continue;
            }

            $this->assertArrayHasKey($target, $sideById, "Match {$id} mengumpan ke match {$target} yang tak ada di sisi mana pun.");
            $this->assertSame(
                $side,
                $sideById[$target],
                "Match {$id} (sisi {$side}) mengumpan ke match {$target} di sisi {$sideById[$target]} — konektor menyilang."
            );
        }
    }

    /**
     * Layout mirror: ronde yang muncul di kedua sisi harus sejajar (kumpulan
     * posisi top identik) dan Final tepat di tengah — regresi kartu kanan yang
     * melorot tidak sejajar dengan kiri.
     */
    public function test_mirror_tops_symmetric_for_client_configuration(): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns(3, [1, 2, 3]));
        $this->assertTrue($mirror['enabled']);

        $tops = MatchGenerator::computeMirrorCardTops($mirror, 240, 120, 0);

        $byLabel = function (array $sideColumns, array $sideTops): array {
            $out = [];
            foreach ($sideColumns as $ci => $column) {
                if (($column['matches'] ?? []) === []) {
                    continue;
                }
                $values = array_values($sideTops[$ci] ?? []);
                sort($values);
                $out[$column['label']] = $values;
            }

            return $out;
        };

        $left = $byLabel($mirror['left'], $tops['left']);
        $right = $byLabel($mirror['right'], $tops['right']);

        $shared = array_intersect(array_keys($left), array_keys($right));
        $this->assertNotEmpty($shared);

        foreach ($shared as $label) {
            $this->assertSame($left[$label], $right[$label], "Ronde '{$label}' harus sejajar di kiri & kanan.");
        }

        // Final di tengah antara Semifinal kiri & kanan.
        $this->assertSame(
            (array_sum($left['Semifinal']) / count($left['Semifinal'])
                + array_sum($right['Semifinal']) / count($right['Semifinal'])) / 2,
            (float) $tops['final']
        );
    }

    /**
     * Bangun $columns seperti BracketViewService::columns(): kelompokkan per
     * ronde dengan urutan asli, Final di kolom terakhir.
     */
    private function columns(int $groupCount, array $ranks): array
    {
        $rounds = [];
        foreach ($this->rounds($this->structure($groupCount, $ranks)) as $label => $matches) {
            $rounds[] = ['label' => $label, 'matches' => $matches, 'teams' => count($matches) * 2];
        }

        return $rounds;
    }

    public static function configProvider(): array
    {
        return [
            '2 grup × [1,2,3,4] (8 tim, pangkat 2)' => [2, [1, 2, 3, 4]],
            '3 grup × [1,2] (6 tim)' => [3, [1, 2]],
            '3 grup × [1,2,3] (9 tim, kasus client)' => [3, [1, 2, 3]],
            '4 grup × [1] (4 tim, pangkat 2)' => [4, [1]],
            '4 grup × [1,2] (8 tim, pangkat 2)' => [4, [1, 2]],
            '4 grup × [1,2,3] (12 tim)' => [4, [1, 2, 3]],
            '4 grup × [1,2,3,4] (16 tim, pangkat 2)' => [4, [1, 2, 3, 4]],
            '5 grup × [1] (5 tim)' => [5, [1]],
            '5 grup × [1,2] (10 tim)' => [5, [1, 2]],
            '5 grup × [1,2,3] (15 tim)' => [5, [1, 2, 3]],
            '6 grup × [1,2] (12 tim)' => [6, [1, 2]],
            '7 grup × [1,2] (14 tim)' => [7, [1, 2]],
            '9 grup × [1,2] (18 tim)' => [9, [1, 2]],
        ];
    }

    public static function nonPowerOfTwoConfigProvider(): array
    {
        return array_filter(
            self::configProvider(),
            function (array $config) {
                $teamCount = $config[0] * count($config[1]);

                return ($teamCount & ($teamCount - 1)) !== 0;
            }
        );
    }
}
