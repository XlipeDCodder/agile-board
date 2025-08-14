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
}
