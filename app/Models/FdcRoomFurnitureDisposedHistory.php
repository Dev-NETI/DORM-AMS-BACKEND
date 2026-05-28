<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdcRoomFurnitureDisposedHistory extends Model
{
    protected $table = 'fdc_room_furniture_disposed_history';

    protected $fillable = [
        'item_id',
        'sub_item_id',
        'quantity',
        'additional_notes',
        'disposed_by',
        'disposed_at',
    ];

    protected $casts = [
        'disposed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function subItem(): BelongsTo
    {
        return $this->belongsTo(RoomFurnitureItemVariant::class, 'sub_item_id');
    }

    public function disposedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FdcRoomFurnitureDisposedDocument::class, 'disposed_history_id');
    }
}
