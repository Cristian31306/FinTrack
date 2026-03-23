<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'franchise',
        'last_4_digits',
        'credit_limit',
        'annual_interest_ea',
        'minimum_payment_percent',
        'statement_day',
        'payment_day',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'annual_interest_ea' => 'decimal:4',
            'minimum_payment_percent' => 'decimal:2',
            'statement_day' => 'integer',
            'payment_day' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(Cut::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CardPayment::class, 'credit_card_id');
    }
}
