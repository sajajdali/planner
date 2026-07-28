<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalProgressLog extends Model
{
    protected $fillable = [
        'goal_id',
        'value',
        'energy',
        'note',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'energy' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
