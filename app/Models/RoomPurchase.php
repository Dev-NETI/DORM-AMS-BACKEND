<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomPurchase extends Model
{

    protected $fillable = [
        'item_id',
        'sub_item_id',
        'room_id',
        'quantity',
        'unit_cost',
        'supplier_id',
        'purchase_date',
        'purchase_document_no',
        'notes',
        'purchased_by',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'unit_cost'     => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function subItem(): BelongsTo
    {
        return $this->belongsTo(RoomFurnitureItemVariant::class, 'sub_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RoomPurchaseDocument::class, 'room_purchase_id');
    }
}
