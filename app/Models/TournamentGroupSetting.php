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
