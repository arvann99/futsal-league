<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchEvent extends Model
{
    protected $table = 'match_events';

    protected $fillable = [
        'match_id',
        'event_type',
        'team_side',
        'player_name',
        'player_id',
        'description',
        'minute',
    ];

    protected $casts = [
        'minute' => 'integer',
    ];

    public function match()
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function player()
    {
        return $this->belongsTo(TournamentTeamPlayer::class, 'player_id');
    }
}
