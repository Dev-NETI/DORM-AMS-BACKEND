<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableReceival extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'consumable_item_id',
        'quantity',
        'received_date',
        'po_number',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'quantity'      => 'decimal:2',
        'received_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConsumableItem::class, 'consumable_item_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
