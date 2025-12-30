<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'due_date', 'status'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function timeEntries()
    {
        return $this->hasManyThrough(TimeEntry::class, Item::class);
    }
}
