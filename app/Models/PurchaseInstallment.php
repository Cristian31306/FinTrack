<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInstallment extends Model
{
    protected $fillable = [
        'purchase_id',
        'cut_id',
        'installment_number',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'statement_close_date',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'statement_close_date' => 'date',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function cut(): BelongsTo
    {
        return $this->belongsTo(Cut::class, 'cut_id');
    }
}
