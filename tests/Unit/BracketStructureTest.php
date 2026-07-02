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
     * Single-elimination dengan N tim SELALU menghasilkan tepat N-1 match.
     * Ini menangkap match yang hilang, ganda, atau tim yang tak pernah main.
     */
    #[DataProvider('teamCountProvider')]
    public function test_total_match_count_is_teams_minus_one(int $n): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams($n));

        $this->assertCount(
            $n - 1,
            $structure,
            "Bagan {$n} tim harus punya tepat " . ($n - 1) . ' match.'
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
     * Ronde kedua (ronde utama, mis. Quarterfinal untuk bracket 16) harus PENUH:
     * jumlah match ronde utama = slotCount/4, dan seluruh tim bye + pemenang
     * ronde pertama mengisinya tanpa card gantung. Untuk 9 tim: Quarterfinal
     * harus berisi 4 match penuh (bukan timpang).
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

        // Jumlah play-in match (ronde pertama) yang benar = n - slotCount/2.
        $expectedPlayIn = $n - intdiv($slotCount, 2);

        $roundOrder = ['Round of 32', 'Round of 16', 'Quarterfinal', 'Semifinal', 'Final'];
        $byRound = $this->byRound($structure);

        // Ronde main paling awal.
        $firstRound = null;
        foreach ($roundOrder as $round) {
            if (! empty($byRound[$round])) {
                $firstRound = $round;
                break;
            }
        }

        if ($expectedPlayIn <= 0) {
            // Tidak perlu ronde play-in (n == slotCount/2, mis. tak terjadi untuk
            // daftar ini) — lewati.
            $this->assertTrue(true);

            return;
        }

        // Ronde pertama harus berisi TEPAT jumlah play-in yang dihitung, dan
        // setiap match ronde pertama mempertemukan dua tim nyata (tim vs tim).
        $this->assertCount(
            $expectedPlayIn,
            $byRound[$firstRound] ?? [],
            "Ronde pertama ({$firstRound}) untuk {$n} tim harus berisi {$expectedPlayIn} play-in match."
        );

        foreach ($byRound[$firstRound] as $match) {
            $this->assertContains($match['left'], $teamNames, 'Peserta kiri play-in harus tim nyata.');
            $this->assertContains($match['right'], $teamNames, 'Peserta kanan play-in harus tim nyata.');
        }
    }

    /**
     * Snapshot struktur untuk 9 tim — kasus yang dilaporkan client.
     * Verifikasi eksplisit: 1 play-in di Round of 16, Quarterfinal penuh (4),
     * Semifinal (2), Final (1); total 8 match; dan seed teratas (Tim1) hanya
     * bye SATU ronde (muncul di Quarterfinal, bukan melompat ke Semifinal).
     */
    public function test_nine_teams_bracket_shape(): void
    {
        $structure = (new MatchGenerator)->buildBracketStructure($this->teams(9));
        $byRound = $this->byRound($structure);

        $this->assertCount(8, $structure);
        $this->assertCount(1, $byRound['Round of 16'] ?? []);
        $this->assertCount(4, $byRound['Quarterfinal'] ?? []);
        $this->assertCount(2, $byRound['Semifinal'] ?? []);
        $this->assertCount(1, $byRound['Final'] ?? []);

        // Tim1 (seed teratas) bye satu ronde → muncul di Quarterfinal.
        $tim1Rounds = [];
        foreach ($structure as $match) {
            if ($match['left'] === 'Tim1' || $match['right'] === 'Tim1') {
                $tim1Rounds[] = $match['round'];
            }
        }
        $this->assertSame(['Quarterfinal'], $tim1Rounds, 'Tim1 harus main pertama kali di Quarterfinal (bye satu ronde saja).');

        // Ronde pertama: tepat satu play-in tim-vs-tim.
        $r16 = $byRound['Round of 16'][0];
        $this->assertNotSame('Bye', $r16['left']);
        $this->assertNotSame('Bye', $r16['right']);
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
