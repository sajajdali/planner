<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyRoutineCheck extends Model
{
    protected $fillable = [
        'daily_routine_id',
        'routine_item_id',
        'is_done',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
