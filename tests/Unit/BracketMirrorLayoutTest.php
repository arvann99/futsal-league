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
     * Sisi kanan harus punya kolom Round of 16 KOSONG (spacer) agar Quarterfinal
     * kiri & kanan sejajar. Ini inti regresi konektor menggantung.
     */
    public function test_nine_teams_right_side_has_empty_playin_spacer(): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns(9));

        $this->assertTrue($mirror['enabled'], 'Bracket 9 tim harus di-mirror.');

        // Sisi kanan ter-reverse (index 0 = mendekati final). Kolom terluar
        // kanan = elemen terakhir = ronde play-in, harus KOSONG.
        $outermostRight = end($mirror['right']);
        $this->assertSame(
            [],
            $outermostRight['matches'],
            'Kolom terluar sisi kanan (play-in) harus kosong sebagai spacer penyejajar.'
        );

        // Verifikasi tops simetris PER RONDE yang sama-sama ada di kedua sisi.
        // (Ronde play-in hanya di kiri — wajar untuk bracket ganjil; yang
        // penting Quarterfinal & Semifinal SEJAJAR agar konektor tak melenceng.)
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
        $this->assertSame(120.0, (float) $tops['final'], 'Final harus di tengah kedua Semifinal.');
        $this->assertArrayHasKey('Semifinal', $leftByLabel);
        $this->assertSame([120.0], $leftByLabel['Semifinal']);
    }

    /**
     * 12 tim: tiap match Quarterfinal punya SATU pengumpan dari Round of 16
     * (slot satunya diisi tim bye). Match pengumpan tunggal harus SEJAJAR
     * (top sama) dengan match tujuannya — regresi "zigzag" kolom terluar
     * akibat pemasangan posisi (i*2, i*2+1) yang mengira selalu ada dua
     * pengumpan.
     */
    #[DataProvider('singleFeederTeamCountProvider')]
    public function test_single_feeder_match_aligns_with_its_target(int $n): void
    {
        $mirror = MatchGenerator::splitBracketColumnsMirror($this->columns($n));
        $this->assertTrue($mirror['enabled'], "Bracket {$n} tim harus di-mirror.");

        $tops = MatchGenerator::computeMirrorCardTops($mirror, 240, 120, 0);

        // Petakan id match → top & daftar pengumpan per target, di kedua sisi.
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

        $checked = 0;
        foreach ($feedersByTarget as $targetId => $feederIds) {
            if (count($feederIds) !== 1 || ! isset($topById[$targetId])) {
                continue; // hanya target berpengumpan tunggal di sisi yang sama
            }
            $this->assertSame(
                $topById[$targetId],
                $topById[$feederIds[0]],
                "Match {$feederIds[0]} (pengumpan tunggal) harus sejajar dengan match {$targetId} ({$n} tim)."
            );
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, "Bracket {$n} tim harus punya match berpengumpan tunggal.");
    }

    public static function singleFeederTeamCountProvider(): array
    {
        return [
            '9 tim' => [9],
            '10 tim' => [10],
            '12 tim' => [12],
        ];
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
            '16 tim' => [16],
        ];
    }
}
