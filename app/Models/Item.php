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
        'column_id',
        'project_id',
        'creator_id',
        // 'assignee_id' foi removido daqui
        'title',
        'description',
        'type',
        'priority',
        'status',
        'estimation',
        'due_date',
        'order_in_column',
        'completed_at',
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
}
