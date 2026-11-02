<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamDailyChecklistItem extends Model
{
    protected $fillable = ['user_id', 'title', 'checklist_date', 'is_done'];

    protected function casts(): array
    {
        return ['checklist_date' => 'date', 'is_done' => 'boolean'];
    }
}
