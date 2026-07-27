<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealEntry extends Model
{
    protected $fillable = [
        'user_id',
        'meal_date',
        'title',
        'meal_time',
        'meal_type',
        'note',
        'status',
        'sort_order',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }
}
