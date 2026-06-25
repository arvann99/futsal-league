<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        // 'plan' & 'is_root' SENGAJA tidak mass-assignable.
        // 'plan' hanya dinaikkan server-side di RootController::approve() (setelah ACC pembayaran);
        // 'is_root' hanya via migration/command. Mencegah privilege escalation lewat mass-assignment.
    ];

    /**
     * R22 — limit per paket. null = unlimited.
     */
    const PLAN_LIMITS = [
        'free'     => ['tournaments' => 1,    'teams' => 8],
        'pro'      => ['tournaments' => 3,    'teams' => 32],
        'ultimate' => ['tournaments' => null, 'teams' => null],
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_root' => 'boolean',
        ];
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class, 'created_by');
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'created_by');
    }

    public function subscriptionRequests()
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    // ---- R22: paket & limit ----

    public function isRoot(): bool
    {
        return (bool) $this->is_root;
    }

    public function planLimits(): array
    {
        // Root selalu unlimited.
        if ($this->isRoot()) {
            return self::PLAN_LIMITS['ultimate'];
        }

        return self::PLAN_LIMITS[$this->plan] ?? self::PLAN_LIMITS['free'];
    }

    public function tournamentLimit(): ?int
    {
        return $this->planLimits()['tournaments'];
    }

    public function teamLimit(): ?int
    {
        return $this->planLimits()['teams'];
    }

    public function canCreateTournament(): bool
    {
        $limit = $this->tournamentLimit();
        if ($limit === null) {
            return true; // unlimited
        }

        return $this->tournaments()->count() < $limit;
    }

    public function canAddTeamTo(Tournament $tournament): bool
    {
        $limit = $this->teamLimit();
        if ($limit === null) {
            return true; // unlimited
        }

        return TournamentTeam::where('tournament_id', $tournament->id)->count() < $limit;
    }

    public function hasPendingSubscriptionRequest(): bool
    {
        return $this->subscriptionRequests()->where('status', 'pending')->exists();
    }
}
