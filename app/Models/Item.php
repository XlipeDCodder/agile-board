<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'reopened_from_id',
        'column_id',
        'project_id',
        'creator_id',
        // 'assignee_id' foi removido daqui
        'title',
        'description',
        'justification',
        'type',
        'priority',
        'status',
        'is_blocked',
        'blocked_reason',
        'blocked_by_item_id',
        'blocked_at',
        'estimation',
        'predicted_value',
        'predicted_unit',
        'due_date',
        'order_in_column',
        'completed_at',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
        'completed_at' => 'datetime',
        'predicted_value' => 'integer',
        // due_date NÃO tem cast: o DB já guarda como DATE (string YYYY-MM-DD)
        // e o frontend faz split('-') esperando esse formato. Castar como
        // 'date' faria Carbon serializar como ISO completo no Inertia e
        // quebraria o parsing no Vue (NaN → Invalid Date).
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleting(function (Item $item) {
            $item->subtasks()->delete();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Item::class, 'parent_id')->orderBy('created_at');
    }

    /** Card original do qual este foi reaberto. */
    public function reopenedFrom(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'reopened_from_id');
    }

    /** Reaberturas que descendem deste card. */
    public function reopens(): HasMany
    {
        return $this->hasMany(Item::class, 'reopened_from_id');
    }

    /** Card que está bloqueando este (opcional). */
    public function blockedByItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'blocked_by_item_id');
    }

    /** Histórico de bloqueios/desbloqueios. */
    public function blockEvents(): HasMany
    {
        return $this->hasMany(ItemBlockEvent::class)->orderBy('created_at');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * O relacionamento antigo assignee() foi removido.
     */

    /**
     * Define o relacionamento onde um item pode ter muitos responsáveis (assignees).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'item_user');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
