<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPayment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'credit_card_id',
        'cut_id',
        'amount',
        'type',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }

    public function cut(): BelongsTo
    {
        return $this->belongsTo(Cut::class, 'cut_id');
    }
}
