<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTimeSession extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
        'paused_at',
        'ended_at',
        'duration_seconds',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
