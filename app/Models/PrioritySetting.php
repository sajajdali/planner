<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioritySetting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'label',
        'color',
        'soft_color',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
