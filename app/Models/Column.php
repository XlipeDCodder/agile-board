<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'order',
    ];

    /**
     * Define o relacionamento onde uma coluna pode ter muitos itens.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('order_in_column');
    }
}
