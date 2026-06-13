<?php

namespace App\Models;

use App\Models\TournamentTeam;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'bracket_match_id',
        'next_bracket_match_id',
        'next_match_id',
        'stage_type',
        'playoff_type',
        'group_label',
        'round_name',
        'home_team_id',
        'away_team_id',
        'home_team_key',
        'away_team_key',
        'source_home',
        'source_away',
        'is_bye',
        'is_third_place',
        'leg',
        'match_date',
        'venue',
        'home_score',
        'away_score',
        'home_penalty_score',
        'away_penalty_score',
        'status',
    ];

    protected $casts = [
        'is_bye' => 'boolean',
        'is_third_place' => 'boolean',
        'leg' => 'integer',
        'home_penalty_score' => 'integer',
        'away_penalty_score' => 'integer',
        'match_date' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam()
    {
        return $this->belongsTo(TournamentTeam::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(TournamentTeam::class, 'away_team_id');
    }

    public function nextMatch()
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function events()
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }
}
