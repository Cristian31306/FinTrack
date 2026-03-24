<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'credit_card_id',
        'category_id',
        'name',
        'total_amount',
        'installments_count',
        'purchase_date',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'purchase_date' => 'date',
            'category_id' => 'integer',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PurchaseInstallment::class)->orderBy('installment_number');
    }

    public function purchaseResponsibles(): HasMany
    {
        return $this->hasMany(PurchaseResponsible::class);
    }
}
