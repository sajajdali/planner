<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalMilestone extends Model
{
    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'weight',
        'starts_on',
        'ends_on',
        'status',
        'progress',
        'dependency',
        'is_done',
        'date_label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'weight' => 'decimal:2',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'progress' => 'integer',
        ];
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
