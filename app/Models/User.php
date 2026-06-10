<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'must_change_password',
        'avatar_path',
    ];

    /**
     * avatar_url entra em TODA serialização de User (cards, comentários,
     * dashboard) — o frontend decide entre foto e iniciais por esse campo.
     *
     * @var array<int, string>
     */
    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? '/storage/'.$this->avatar_path : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Define o relacionamento onde um usuário pode ter criado muitos itens.
     */
    public function createdItems(): HasMany
    {
        return $this->hasMany(Item::class, 'creator_id');
    }

    /**
     * Define o relacionamento onde um usuário pode ser o responsável por muitos itens.
     */
    public function assignedItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_user');
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function googleToken(): HasOne
    {
        return $this->hasOne(GoogleOAuthToken::class);
    }
}
