<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cut extends Model
{
    protected $fillable = [
        'credit_card_id',
        'period_start',
        'period_end',
        'total_accrued',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_accrued' => 'decimal:2',
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PurchaseInstallment::class, 'cut_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CardPayment::class, 'cut_id');
    }
}
