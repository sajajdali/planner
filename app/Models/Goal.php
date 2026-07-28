<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'category',
        'color',
        'icon',
        'status',
        'start_value',
        'current_value',
        'target_value',
        'unit',
        'direction',
        'deadline',
        'why',
        'next_action',
        'last_activity_label',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'deadline' => 'date:Y-m-d',
            'metadata' => 'array',
        ];
    }

    public function milestones()
    {
        return $this->hasMany(GoalMilestone::class)->orderBy('sort_order');
    }

    public function planItems()
    {
        return $this->hasMany(GoalPlanItem::class)->orderBy('sort_order');
    }

    public function progressLogs()
    {
        return $this->hasMany(GoalProgressLog::class)->latest('logged_at');
    }
}
