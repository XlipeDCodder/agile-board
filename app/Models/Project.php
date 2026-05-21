<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'due_date', 'status'];

    // due_date sem cast: o frontend (Projects/Index.vue, Reports/Project.vue)
    // espera string YYYY-MM-DD direto do DB. Casting como 'date' fazia Carbon
    // serializar como ISO completo no Inertia e quebrava o input date e o
    // formatDate do Vue (NaN → Invalid Date).

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function timeEntries()
    {
        return $this->hasManyThrough(TimeEntry::class, Item::class);
    }
}
