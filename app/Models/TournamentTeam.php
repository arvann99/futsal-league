<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentTeam extends Model
{
    protected $fillable = [
        'tournament_id',
        'team_id',
        // 'manager_token', // consolidated to teams.manager_token
        'registration_status',
        'group_label',
        'group_assigned_manually',
        'seed',
        'bracket_position',
        'is_promoted',
        'is_relegated',
    ];

    protected $casts = [
        'group_assigned_manually' => 'boolean',
        'is_promoted' => 'boolean',
        'is_relegated' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function players()
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function officials()
    {
        return $this->hasMany(TournamentTeamOfficial::class);
    }
}
