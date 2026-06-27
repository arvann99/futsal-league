<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Generate token manager unik berbasis nama tim (mis. "GARUDA-1234").
     * Sumber tunggal pembuatan token agar dipakai konsisten di TeamController
     * (store/reset) maupun saat menambah peserta lewat Manajemen Peserta (N3).
     */
    public static function generateUniqueManagerToken(?string $name = null): string
    {
        $base = $name ?: 'TEAM';
        $prefix = strtoupper(Str::slug($base, '')) ?: 'TEAM';

        do {
            $token = $prefix . '-' . random_int(1000, 9999);
        } while (static::where('manager_token', $token)->exists());

        return $token;
    }

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
