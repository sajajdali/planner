<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceObligation extends Model
{
    protected $fillable = [
        'user_id',
        'financial_account_id',
        'type',
        'title',
        'party_name',
        'total_amount',
        'installment_amount',
        'installments_total',
        'due_day',
        'start_date',
        'due_date',
        'status',
        'color',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'installment_amount' => 'integer',
            'installments_total' => 'integer',
            'due_day' => 'integer',
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function account()
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function payments()
    {
        return $this->hasMany(FinanceObligationPayment::class);
    }
}
