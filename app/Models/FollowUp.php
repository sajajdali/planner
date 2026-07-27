<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FollowUp extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'follow_up_date',
        'follow_up_time',
        'person_name',
        'phone',
        'url',
        'priority',
        'status',
        'result_note',
        'next_follow_up_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date:Y-m-d',
            'next_follow_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
