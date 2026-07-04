<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentGroupSetting extends Model
{
    protected $fillable = [
        'tournament_id',
        'teams_per_group',
        'group_count',
        'qualified_teams',
        'relegated_teams',
        'locked',
        'league_round_type',
    ];

    protected $casts = [
        'qualified_teams' => 'array',
        'relegated_teams' => 'array',
        'locked' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Daftar label grup untuk $count grup: A..Z, lalu AA, AB, ... Sumber tunggal
     * agar penamaan grup konsisten di seluruh aplikasi (klasemen, penempatan
     * peserta, seeding bracket). Array huruf statis A..H dulu memutus di grup
     * ke-9 sehingga grup 'I' hilang dan timnya tercecer.
     */
    public static function groupLabels(int $count): array
    {
        $labels = [];
        $base = range('A', 'Z');

        for ($i = 0; $i < max(0, $count); $i++) {
            if ($i < count($base)) {
                $labels[] = $base[$i];
                continue;
            }

            $first = $base[intdiv($i, count($base)) - 1] ?? 'A';
            $second = $base[$i % count($base)];
            $labels[] = $first . $second;
        }

        return $labels;
    }

    /**
     * Cek apakah team ranking tertentu lolos
     */
    public function isQualified($teamRanking)
    {
        return in_array($teamRanking, $this->qualified_teams ?? []);
    }

    /**
     * Dapatkan daftar tim yang lolos dalam format readable
     */
    public function getQualifiedTeamsLabel()
    {
        $qualified = $this->qualified_teams ?? [];
        if (empty($qualified)) {
            return 'Belum ada pengaturan';
        }
        
        $labels = array_map(function($rank) {
            return 'Tim Ranking ' . $rank;
        }, $qualified);
        
        return implode(', ', $labels);
    }
}
