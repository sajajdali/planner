<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTaskProject extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'task_group_id',
        'sort_order',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function taskGroup()
    {
        return $this->belongsTo(TaskGroup::class);
    }

    public function items()
    {
        return $this->hasMany(GroupTaskItem::class)->orderBy('sort_order');
    }
}
