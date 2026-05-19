<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableIssuance extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'consumable_item_id',
        'quantity',
        'issued_date',
        'usage_history',
        'issued_by',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'issued_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConsumableItem::class, 'consumable_item_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
