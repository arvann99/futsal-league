<?php

namespace Tests\Unit;

use App\Services\MatchGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Menguji App\Services\MatchGenerator::buildBracketStructure() untuk babak
 * gugur dengan jumlah tim BUKAN pangkat 2 (3,5,6,7,9,10,11).
 *
 * Regresi yang dijaga: dulu bye tersebar tidak merata sehingga sebagian seed
 * teratas MELOMPATI dua ronde dan muncul ronde awal yang nyaris kosong (mis. 9
 * tim → satu card "Round of 16" gantung). Sekarang semua bye terkumpul di ronde
 * pertama dan bagan seimbang.
 */
class BracketStructureTest extends TestCase
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
     * Kelompokkan match hasil buildBracketStructure per label ronde.
     */
    private function byRound(array $structure): array
    {
        $byRound = [];
        foreach ($structure as $match) {
            $byRound[$match['round']][] = $match;
        }

        return $byRound;
    }

    /**
     * Single-elimination dengan ronde pertama PENUH menghasilkan tepat
     * (slotCount − 1) card, di mana slotCount = pangkat 2 terkecil ≥ N. Card bye
     * (tim vs Bye) ikut dihitung karena kini ditampilkan, bukan di-skip. Ini
     * menangkap card yang hilang, ganda, atau tim yang tak pernah tampil.
     */
    #[DataProvider('teamCountProvider')]
    public function test_total_match_count_is_slotcount_minus_one(int $n): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));

        $slotCount = 1;
        while ($slotCount < $n) {
            $slotCount *= 2;
        }

        $this->assertCount(
            $slotCount - 1,
            $structure,
            "Bagan {$n} tim (slot {$slotCount}) harus punya tepat " . ($slotCount - 1) . ' card.'
        );
    }

    /**
     * Tidak boleh ada tim yang melompati dua ronde: setiap tim NYATA (bukan
     * placeholder "Pemenang M..") hanya boleh muncul sebagai peserta di ronde
     * PERTAMA yang match-nya benar-benar dimainkan. Bila sebuah tim nyata muncul
     * di ronde selain ronde-main-pertama, berarti ia melompat lebih dari yang
     * seharusnya (bug lama pada 9 tim).
     */
    #[DataProvider('teamCountProvider')]
    public function test_no_team_skips_more_than_one_round(int $n): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));
        $teamNames = $this->teams($n);

        // Urutan ronde dari yang paling awal.
        $roundOrder = ['Round of 32', 'Round of 16', 'Quarterfinal', 'Semifinal', 'Final'];
        $byRound = $this->byRound($structure);

        // Ronde main paling awal yang ada.
        $firstRound = null;
        foreach ($roundOrder as $round) {
            if (! empty($byRound[$round])) {
                $firstRound = $round;
                break;
            }
        }
        $this->assertNotNull($firstRound);

        // Ronde KEDUA (tempat tim bye pertama kali muncul).
        $secondRound = null;
        $seenFirst = false;
        foreach ($roundOrder as $round) {
            if ($round === $firstRound) {
                $seenFirst = true;
                continue;
            }
            if ($seenFirst && ! empty($byRound[$round])) {
                $secondRound = $round;
                break;
            }
        }

        // Tim nyata hanya boleh muncul di ronde pertama ATAU ronde kedua (tim
        // bye). Tidak boleh ada tim nyata pertama kali muncul di ronde ketiga+.
        $allowedRounds = array_filter([$firstRound, $secondRound]);

        foreach ($structure as $match) {
            foreach (['left', 'right'] as $side) {
                $participant = $match[$side];
                $isRealTeam = in_array($participant, $teamNames, true);
                if ($isRealTeam) {
                    $this->assertContains(
                        $match['round'],
                        $allowedRounds,
                        "Tim nyata '{$participant}' muncul di ronde '{$match['round']}' — " .
                        'seharusnya hanya di ' . implode(' atau ', $allowedRounds) . '.'
                    );
                }
            }
        }
    }

    /**
     * Ronde pertama PENUH (slotCount/2 card) berisi seluruh bye + play-in tanpa
     * card gantung: tepat (slotCount − n) card bye (tim vs Bye) dan (n − slotCount/2)
     * card play-in (tim vs tim). Untuk 9 tim: Round of 16 berisi 8 card (7 bye +
     * 1 play-in), sehingga Quarterfinal penuh 4 match.
     */
    #[DataProvider('teamCountProvider')]
    public function test_first_round_holds_all_byes(int $n): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));
        $teamNames = $this->teams($n);

        $slotCount = 1;
        while ($slotCount < $n) {
            $slotCount *= 2;
        }

        $expectedFirstRound = intdiv($slotCount, 2);   // ronde pertama penuh
        $expectedByes = $slotCount - $n;               // card (tim vs Bye)
        $expectedPlayIn = $n - intdiv($slotCount, 2);  // card (tim vs tim)

        $roundOrder = ['Round of 32', 'Round of 16', 'Quarterfinal', 'Semifinal', 'Final'];
        $byRound = $this->byRound($structure);

        $firstRound = null;
        foreach ($roundOrder as $round) {
            if (! empty($byRound[$round])) {
                $firstRound = $round;
                break;
            }
        }

        $this->assertCount(
            $expectedFirstRound,
            $byRound[$firstRound] ?? [],
            "Ronde pertama ({$firstRound}) untuk {$n} tim harus penuh: {$expectedFirstRound} card."
        );

        $byes = 0;
        $playIns = 0;
        foreach ($byRound[$firstRound] as $match) {
            if (! empty($match['is_bye'])) {
                $byes++;
                $this->assertContains($match['left'], $teamNames, 'Sisi kiri card bye harus tim nyata.');
                $this->assertSame('Bye', $match['right'], 'Sisi kanan card bye harus "Bye".');
            } else {
                $playIns++;
                $this->assertContains($match['left'], $teamNames, 'Peserta kiri play-in harus tim nyata.');
                $this->assertContains($match['right'], $teamNames, 'Peserta kanan play-in harus tim nyata.');
            }
        }

        $this->assertSame($expectedByes, $byes, "Jumlah card bye {$n} tim harus {$expectedByes}.");
        $this->assertSame($expectedPlayIn, $playIns, "Jumlah card play-in {$n} tim harus {$expectedPlayIn}.");
    }

    /**
     * Snapshot struktur untuk 9 tim — kasus yang dilaporkan client. Ronde pertama
     * PENUH: Round of 16 = 8 card (7 bye + 1 play-in), Quarterfinal (4),
     * Semifinal (2), Final (1); total 15 card. Seed teratas (Tim1) dapat card
     * bye di Round of 16 lalu melaju ke Quarterfinal (bye satu ronde saja).
     */
    public function test_nine_teams_bracket_shape(): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams(9));
        $byRound = $this->byRound($structure);

        $this->assertCount(15, $structure);
        $this->assertCount(8, $byRound['Round of 16'] ?? []);
        $this->assertCount(4, $byRound['Quarterfinal'] ?? []);
        $this->assertCount(2, $byRound['Semifinal'] ?? []);
        $this->assertCount(1, $byRound['Final'] ?? []);

        // Tim1 (seed teratas) dapat card bye di Round of 16, lalu melaju ke QF.
        $tim1Rounds = [];
        foreach ($structure as $match) {
            if ($match['left'] === 'Tim1' || $match['right'] === 'Tim1') {
                $tim1Rounds[] = $match['round'];
            }
        }
        $this->assertSame(['Round of 16', 'Quarterfinal'], $tim1Rounds, 'Tim1 dapat card bye (R16) lalu melaju ke Quarterfinal.');

        // Card bye Tim1: (Tim1 vs Bye), ber-flag is_bye.
        $tim1Bye = null;
        foreach ($byRound['Round of 16'] as $match) {
            if ($match['left'] === 'Tim1') {
                $tim1Bye = $match;
                break;
            }
        }
        $this->assertNotNull($tim1Bye, 'Tim1 harus punya card bye di Round of 16.');
        $this->assertTrue((bool) $tim1Bye['is_bye']);
        $this->assertSame('Bye', $tim1Bye['right']);

        // Tepat satu card play-in tim-vs-tim di Round of 16.
        $playIns = array_filter($byRound['Round of 16'], fn ($m) => empty($m['is_bye']));
        $this->assertCount(1, $playIns);
        $r16 = array_values($playIns)[0];
        $this->assertStringStartsWith('Tim', $r16['left']);
        $this->assertStringStartsWith('Tim', $r16['right']);
    }

    /**
     * Setiap match punya rantai next_match_id yang valid kecuali Final.
     */
    #[DataProvider('teamCountProvider')]
    public function test_next_match_links_form_single_final(int $n): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));

        $finals = array_filter($structure, fn ($m) => $m['round'] === 'Final');
        $this->assertCount(1, $finals, 'Harus ada tepat satu Final.');

        foreach ($structure as $match) {
            if ($match['round'] === 'Final') {
                $this->assertNull($match['next_match_id'], 'Final tidak boleh punya next_match_id.');
            } else {
                $this->assertNotNull(
                    $match['next_match_id'],
                    "Match M{$match['id']} ({$match['round']}) harus mengarah ke match berikutnya."
                );
            }
        }
    }

    public static function teamCountProvider(): array
    {
        return [
            '3 tim' => [3],
            '5 tim' => [5],
            '6 tim' => [6],
            '7 tim' => [7],
            '9 tim' => [9],
            '10 tim' => [10],
            '11 tim' => [11],
        ];
    }
}
