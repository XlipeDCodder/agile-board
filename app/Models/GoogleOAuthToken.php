<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleOAuthToken extends Model
{
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'google_email',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
