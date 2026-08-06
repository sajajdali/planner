<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTaskItem extends Model
{
    protected $fillable = [
        'group_task_project_id',
        'title',
        'period_type',
        'is_done',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(GroupTaskProject::class, 'group_task_project_id');
    }
}
