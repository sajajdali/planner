<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupDailyChecklistItem extends Model
{
    protected $fillable = ['user_id', 'checklist_date', 'title', 'is_done', 'sort_order'];

    protected function casts(): array
    {
        return ['checklist_date' => 'date', 'is_done' => 'boolean'];
    }
}
