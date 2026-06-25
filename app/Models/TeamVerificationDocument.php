<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamVerificationDocument extends Model
{
    protected $fillable = [
        'team_id',
        'document_name',
        'document_path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
