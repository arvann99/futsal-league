<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_plan',
        'payment_proof',
        'amount',
        'status',
        'reviewed_by',
        'reviewed_at',
        'note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // R22 — harga paket (Rupiah) untuk ditampilkan & dicatat di request.
    const PRICES = [
        'pro' => 50000,
        'ultimate' => 150000,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
