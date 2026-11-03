<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FourHourReport extends Model
{
    protected $fillable = [
        'user_id',
        'four_hour_report_category_id',
        'report_date',
        'start_time',
        'end_time',
        'ends_next_day',
        'duration_minutes',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'duration_minutes' => 'integer',
            'ends_next_day' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(FourHourReportCategory::class, 'four_hour_report_category_id');
    }

    public function items()
    {
        return $this->hasMany(FourHourReportItem::class)->orderBy('sort_order');
    }
}
