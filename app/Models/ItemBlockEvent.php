<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBlockEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'event',
        'reason',
        'blocked_by_item_id',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function blockedByItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'blocked_by_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
