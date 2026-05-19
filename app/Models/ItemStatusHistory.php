<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemStatusHistory extends Model
{
    use HasFactory;

    /**
     * Define que a tabela não tem a coluna 'updated_at'.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_id',
        'column_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ItemStatusHistory $history) {
            if (! $history->created_at) {
                $history->created_at = now();
            }
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function column()
    {
        return $this->belongsTo(Column::class);
    }
}
