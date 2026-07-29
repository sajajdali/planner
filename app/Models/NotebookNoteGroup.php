<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotebookNoteGroup extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'color',
        'icon',
        'sort_order',
    ];

    public function notes(): HasMany
    {
        return $this->hasMany(NotebookNote::class)->orderBy('sort_order')->latest();
    }
}
