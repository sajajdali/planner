<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTaskArchive extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'task_count',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'task_count' => 'integer',
        ];
    }
}
