<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FourHourReportCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'color', 'sort_order', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
