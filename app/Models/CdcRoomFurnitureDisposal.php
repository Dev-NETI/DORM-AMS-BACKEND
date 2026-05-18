<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdcRoomFurnitureDisposal extends Model
{
    protected $table = 'cdc_room_furniture_disposals';

    protected $fillable = [
        'item_id',
        'sub_item_id',
        'quantity',
        'notes',
        'tagged_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function subItem(): BelongsTo
    {
        return $this->belongsTo(RoomFurnitureItemVariant::class, 'sub_item_id');
    }

    public function tagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_by');
    }
}
