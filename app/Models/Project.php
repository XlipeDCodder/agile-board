<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'due_date'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
