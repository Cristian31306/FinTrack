<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseResponsible extends Model
{
    protected $table = 'purchase_responsibles';

    protected $fillable = [
        'purchase_id',
        'responsible_person_id',
        'split_type',
        'split_value',
        'owed_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'split_value' => 'decimal:4',
            'owed_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(ResponsiblePerson::class, 'responsible_person_id');
    }
}
