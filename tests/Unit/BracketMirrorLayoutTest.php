<?php

namespace Tests\Unit;

use App\Services\MatchGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Menguji App\Services\MatchGenerator::splitBracketColumnsMirror() dan
 * computeMirrorCardTops() — layout bracket dua sisi (mirror) ala Piala Dunia.
 *
 * Regresi yang dijaga: pada bracket ganjil (mis. 9 tim) ronde play-in cuma
 * berisi 1 match sehingga seluruhnya jatuh ke sisi KIRI. Dulu sisi kanan tak
 * mendapat kolom ronde itu, jadi jumlah kolom kiri ≠ kanan; akibatnya ronde di
 * kanan bergeser dan konektornya "menggantung" ke ruang kosong (bug garis
 * kanan-bawah). Sekarang tiap ronde feeder selalu memunculkan kolom di KEDUA
 * sisi (kolom kosong sebagai spacer) sehingga ronde tetap sejajar & simetris.
 */
class BracketMirrorLayoutTest extends TestCase
{
    private function teams(int $n): array
    {
        $teams = [];
        for ($i = 1; $i <= $n; $i++) {
            $teams[] = 'Tim' . $i;
        }

        return $teams;
    }

    /**
     * Bangun $columns seperti BracketViewService::columns(): kelompokkan per
     * ronde dengan urutan asli, Final di kolom terakhir.
     */
    private function columns(int $n): array
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));

        $roundIndex = [];
        foreach ($structure as $match) {
            $roundIndex[$match['round']][] = $match;
        }

        $rounds = [];
        foreach ($roundIndex as $label => $group) {
            $rounds[] = ['label' => $label, 'matches' => $group, 'teams' => count($group) * 2];
        }

        $final = array_pop($rounds);
        $columns = $rounds;
        $columns[] = $final;

        return $columns;
    }

    /**
     * Kedua sisi mirror harus punya jumlah kolom SAMA agar ronde sejajar.
     */
    #[DataProvider('teamCountProvider')]
    public function test_both_sides_have_equal_column_count(int $n): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns($n));

        if (! $mirror['enabled']) {
            $this->markTestSkipped("Bracket {$n} tim tak layak mirror (fallback satu arah).");
        }

        $this->assertCount(
            count($mirror['left']),
            $mirror['right'],
            "Jumlah kolom kiri & kanan harus sama untuk {$n} tim (kesejajaran ronde)."
        );
    }

    /**
     * Tiap match nyata muncul TEPAT sekali di seluruh sisi (tak hilang/ganda
     * akibat pemisahan sisi atau kolom kosong).
     */
    #[DataProvider('teamCountProvider')]
    public function test_every_match_appears_exactly_once(int $n): void
    {
        $columns = $this->columns($n);
        $mirror = MatchGenerator::splitBracketColumnsMirror($columns);

        if (! $mirror['enabled']) {
            $this->markTestSkipped("Bracket {$n} tim tak layak mirror.");
        }

        $expectedIds = [];
        foreach ($columns as $col) {
            foreach ($col['matches'] as $m) {
                $expectedIds[] = $m['id'];
            }
        }
        sort($expectedIds);

        $seenIds = [];
        foreach (['left', 'right'] as $side) {
            foreach ($mirror[$side] as $col) {
                foreach ($col['matches'] as $m) {
                    $seenIds[] = $m['id'];
                }
            }
        }
        foreach ($mirror['final']['matches'] as $m) {
            $seenIds[] = $m['id'];
        }
        sort($seenIds);

        $this->assertSame(
            $expectedIds,
            $seenIds,
            "Setiap match harus muncul tepat sekali di layout mirror {$n} tim."
        );
    }

    /**
     * 9 tim: ronde play-in (Round of 16) hanya 1 match → seluruhnya di KIRI.
     * Ronde pertama kini PENUH (card bye ditampilkan), jadi 9 tim → Round of 16
     * berisi 8 card yang terbelah rata 4 kiri + 4 kanan — tiap ronde muncul di
     * KEDUA sisi dan sejajar, tak ada lagi kolom spacer kosong / konektor
     * menggantung.
     */
    public function test_nine_teams_both_sides_symmetric(): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns(9));

        $this->assertTrue($mirror['enabled'], 'Bracket 9 tim harus di-mirror.');

        // Tak ada kolom kosong: setiap kolom kedua sisi berisi match.
        foreach (['left', 'right'] as $side) {
            foreach ($mirror[$side] as $col) {
                $this->assertNotSame([], $col['matches'], "Kolom {$col['label']} sisi {$side} tak boleh kosong (ronde pertama penuh).");
            }
        }

        // Verifikasi tops simetris PER RONDE yang sama-sama ada di kedua sisi.
        $tops = MatchGenerator::computeMirrorCardTops($mirror, 240, 120, 0);

        // Petakan label ronde → tops, untuk tiap sisi.
        $byLabel = function (array $sideColumns, array $sideTops): array {
            $out = [];
            foreach ($sideColumns as $ci => $col) {
                if (($col['matches'] ?? []) === []) {
                    continue; // lewati kolom spacer kosong
                }
                $vals = array_values($sideTops[$ci] ?? []);
                sort($vals);
                $out[$col['label']] = $vals;
            }

            return $out;
        };

        $leftByLabel = $byLabel($mirror['left'], $tops['left']);
        $rightByLabel = $byLabel($mirror['right'], $tops['right']);

        // Ronde yang muncul di KEDUA sisi harus punya tops identik.
        $sharedRounds = array_intersect(array_keys($leftByLabel), array_keys($rightByLabel));
        $this->assertNotEmpty($sharedRounds, 'Harus ada ronde yang muncul di kedua sisi.');

        foreach ($sharedRounds as $label) {
            $this->assertSame(
                $leftByLabel[$label],
                $rightByLabel[$label],
                "Ronde '{$label}' harus sejajar (tops identik) di kiri & kanan."
            );
        }

        // Final tepat di tengah antara Semifinal kiri & kanan.
        $this->assertArrayHasKey('Semifinal', $leftByLabel);
        $this->assertArrayHasKey('Semifinal', $rightByLabel);
        $expectedFinal = (
            array_sum($leftByLabel['Semifinal']) / count($leftByLabel['Semifinal'])
            + array_sum($rightByLabel['Semifinal']) / count($rightByLabel['Semifinal'])
        ) / 2;
        $this->assertSame($expectedFinal, (float) $tops['final'], 'Final harus di tengah kedua Semifinal.');
    }

    /**
     * Ronde pertama kini ditampilkan PENUH (card bye), sehingga SETIAP card
     * ronde-dalam (bukan ronde pertama) punya TEPAT DUA pengumpan — inilah yang
     * membuat bagan simetris & jarak antar-card seragam (rapi). Regresi utama
     * kerapian: dulu card bye di-skip → sebagian QF/SF cuma 1 pengumpan → siku
     * konektor panjang tak simetris.
     */
    #[DataProvider('teamCountProvider')]
    public function test_inner_round_matches_have_two_feeders(int $n): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns($n));
        $this->assertTrue($mirror['enabled'], "Bracket {$n} tim harus di-mirror.");

        // Kumpulkan feeder per target dari SELURUH bagan (kolom penuh + final).
        $columns = $this->columns($n);
        $feedersByTarget = [];
        $firstRoundLabel = $columns[0]['label'];
        $roundOf = [];
        foreach ($columns as $col) {
            foreach ($col['matches'] as $m) {
                $roundOf[$m['id']] = $col['label'];
                if (($m['next_match_id'] ?? null) !== null) {
                    $feedersByTarget[$m['next_match_id']][] = $m['id'];
                }
            }
        }

        foreach ($feedersByTarget as $targetId => $feederIds) {
            // Target selalu di ronde-dalam (ronde pertama tak pernah jadi target).
            $this->assertCount(
                2,
                $feederIds,
                "Match {$targetId} ({$roundOf[$targetId]}) harus punya tepat 2 pengumpan ({$n} tim)."
            );
        }
    }

    /**
     * Regresi garis konektor melenceng (laporan 21/40/45/44/... tim): SETIAP
     * kartu yang punya pengumpan harus berada tepat di TENGAH (mid) para
     * pengumpannya di sisi yang sama, agar semua garis lurus/simetris. Dulu
     * kartu ber-pengumpan-tunggal yang berderet setelah kartu tanpa-pengumpan
     * "terdorong" kursor sejauh satu rowUnit dari pengumpannya.
     *
     * Diuji lintas seluruh N 2..40 (bracket tim langsung) sekaligus — pipeline
     * layout yang sama dipakai Grup → Gugur.
     */
    public function test_every_fed_match_is_centered_on_its_feeders(): void
    {
        $rowUnit = 240.0;

        for ($n = 4; $n <= 40; $n++) {
            $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns($n));
            if (! $mirror['enabled']) {
                continue; // N=3 dsb. fallback satu-arah, di luar cakupan test ini
            }

            $tops = MatchGenerator::computeMirrorCardTops($mirror, $rowUnit, 120, 0);

            $topById = [];
            $feedersByTarget = [];
            foreach (['left', 'right'] as $side) {
                foreach ($mirror[$side] as $ci => $col) {
                    foreach (array_values($col['matches']) as $i => $m) {
                        $topById[$m['id']] = (float) $tops[$side][$ci][$i];
                        if ($m['next_match_id'] !== null) {
                            $feedersByTarget[$m['next_match_id']][] = $m['id'];
                        }
                    }
                }
            }

            foreach ($feedersByTarget as $targetId => $feederIds) {
                if (! isset($topById[$targetId])) {
                    continue; // target di sisi seberang (final) — dicek terpisah
                }
                $feederTops = array_map(fn ($id) => $topById[$id], $feederIds);
                $expected = (min($feederTops) + max($feederTops)) / 2;
                $this->assertEqualsWithDelta(
                    $expected,
                    $topById[$targetId],
                    0.5,
                    "Match {$targetId} harus di tengah pengumpannya (" . implode(',', $feederIds) . ") pada {$n} tim."
                );
            }
        }
    }

    public static function teamCountProvider(): array
    {
        return [
            '5 tim' => [5],
            '6 tim' => [6],
            '7 tim' => [7],
            '9 tim' => [9],
            '10 tim' => [10],
            '11 tim' => [11],
            '12 tim' => [12],
            '15 tim' => [15],
            '16 tim' => [16],
            '21 tim' => [21],
            '40 tim' => [40],
            '45 tim' => [45],
        ];
    }
}
