<?php

namespace Tests\Unit;

use App\Models\TournamentGroupSetting;
use PHPUnit\Framework\TestCase;

/**
 * Menguji TournamentGroupSetting::groupLabels() — sumber tunggal penamaan grup.
 *
 * Regresi: array huruf statis A..H dulu memutus di grup ke-9 (label 'I' hilang)
 * sehingga di Bagan Klasemen tim grup ke-9 tercecer ke Grup A dan muncul "Grup
 * 9" berlabel angka. Label harus selalu huruf: A..Z lalu AA, AB, ...
 */
class GroupLabelsTest extends TestCase
{
    public function test_nine_groups_go_up_to_letter_i(): void
    {
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            TournamentGroupSetting::groupLabels(9)
        );
    }

    public function test_labels_never_use_numbers(): void
    {
        foreach ([9, 11, 16, 20] as $count) {
            $labels = TournamentGroupSetting::groupLabels($count);
            $this->assertCount($count, $labels);
            foreach ($labels as $label) {
                $this->assertMatchesRegularExpression(
                    '/^[A-Z]+$/',
                    $label,
                    "Label grup harus huruf, bukan angka (jumlah grup {$count})."
                );
            }
        }
    }

    public function test_wraps_past_z_into_double_letters(): void
    {
        $labels = TournamentGroupSetting::groupLabels(28);

        $this->assertSame('Z', $labels[25]);
        $this->assertSame('AA', $labels[26]);
        $this->assertSame('AB', $labels[27]);
    }

    public function test_zero_or_negative_returns_empty(): void
    {
        $this->assertSame([], TournamentGroupSetting::groupLabels(0));
        $this->assertSame([], TournamentGroupSetting::groupLabels(-3));
    }
}
