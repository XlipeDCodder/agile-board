<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deployment extends Model
{
    /** Environment values */
    public const ENV_STAGING = 'staging';
    public const ENV_PRODUCTION = 'production';

    /** Status values */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'item_id',
        'deployer_id',
        'environment',
        'status',
        'notes',
        'approver_id',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'linked_deployment_id',
        'is_urgent',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_urgent' => 'boolean',
    ];

    // Relações ---------------------------------------------------------------

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function deployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function linkedDeployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'linked_deployment_id');
    }

    public function productionDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class, 'linked_deployment_id')
            ->where('environment', self::ENV_PRODUCTION);
    }

    // Helpers ----------------------------------------------------------------

    public function isStaging(): bool
    {
        return $this->environment === self::ENV_STAGING;
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
