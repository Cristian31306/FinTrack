<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponsiblePerson extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseResponsibles(): HasMany
    {
        return $this->hasMany(PurchaseResponsible::class, 'responsible_person_id');
    }
}
