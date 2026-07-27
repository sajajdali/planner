<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceObligationPayment extends Model
{
    protected $fillable = [
        'finance_obligation_id',
        'expense_id',
        'paid_date',
        'amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'paid_date' => 'date:Y-m-d',
            'amount' => 'integer',
        ];
    }

    public function obligation()
    {
        return $this->belongsTo(FinanceObligation::class, 'finance_obligation_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
