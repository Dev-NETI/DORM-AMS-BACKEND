<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CdcRoomPurchase extends Model
{
    protected $table = 'cdc_room_purchases';

    protected $fillable = [
        'item_id',
        'sub_item_id',
        'room_id',
        'quantity',
        'purchase_date',
        'notes',
        'purchased_by',
    ];

    protected $casts = [
        'quantity'      => 'integer',
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

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CdcRoomPurchaseDocument::class, 'cdc_room_purchase_id');
    }
}
