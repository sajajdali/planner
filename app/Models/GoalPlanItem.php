<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalPlanItem extends Model
{
    protected $fillable = [
        'goal_id',
        'title',
        'when_label',
        'sort_order',
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
