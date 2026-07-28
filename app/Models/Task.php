<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'task_group_id',
        'parent_id',
        'title',
        'description',
        'task_date',
        'planned_start_time',
        'planned_end_time',
        'estimated_minutes',
        'manual_actual_minutes',
        'priority',
        'status',
        'progress',
        'sort_order',
        'completed_at',
        'started_at',
        'is_recurring',
        'recurrence_rule',
        'reminder_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'started_at' => 'datetime',
            'reminder_at' => 'datetime',
            'is_recurring' => 'boolean',
            'recurrence_rule' => 'array',
            'metadata' => 'array',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function group()
    {
        return $this->belongsTo(TaskGroup::class, 'task_group_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order');
    }

    public function timeSessions()
    {
        return $this->hasMany(TaskTimeSession::class);
    }
}
