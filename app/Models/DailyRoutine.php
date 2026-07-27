<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyRoutine extends Model
{
    protected $fillable = [
        'user_id',
        'routine_date',
        'wake_time',
        'sleep_time',
    ];

    protected function casts(): array
    {
        return [
            'routine_date' => 'date:Y-m-d',
        ];
    }

    public function checks()
    {
        return $this->hasMany(DailyRoutineCheck::class);
    }
}
