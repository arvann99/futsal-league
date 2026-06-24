<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'city',
        'country',
        'notes',
        'manager_token',
        'verification_status',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tournamentTeams()
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function verificationDocuments()
    {
        return $this->hasMany(TeamVerificationDocument::class);
    }
}
