<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FourHourReportItem extends Model
{
    protected $fillable = ['four_hour_report_id', 'four_hour_report_category_id', 'description', 'duration_minutes', 'sort_order'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer'];
    }

    public function category()
    {
        return $this->belongsTo(FourHourReportCategory::class, 'four_hour_report_category_id');
    }
}
