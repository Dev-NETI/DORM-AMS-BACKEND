<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssetTransfer extends Model
{
    protected $fillable = [
        'room_asset_id',
        'from_location_id',
        'to_location_id',
        'transferred_by',
        'transferred_at',
        'reason',
        'notes',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function roomAsset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(RoomLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(RoomLocation::class, 'to_location_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
