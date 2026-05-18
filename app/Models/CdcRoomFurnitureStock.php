<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdcRoomFurnitureStock extends Model
{
    protected $table = 'cdc_room_furniture_stocks';

    protected $fillable = [
        'item_id',
        'sub_item_id',
        'total_quantity',
        'notes',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function subItem(): BelongsTo
    {
        return $this->belongsTo(RoomFurnitureItemVariant::class, 'sub_item_id');
    }
}
