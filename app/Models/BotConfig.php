<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'is_active',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['api_key'];

    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }
}
