<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomFurnitureStock extends Model
{
    protected $table = 'room_furniture_stock';

    protected $fillable = [
        'item_id',
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

    /**
     * How many units are currently deployed across all rooms.
     */
    public function deployedQuantity(): int
    {
        return RoomFurniture::where('item_id', $this->item_id)->sum('quantity');
    }

    /**
     * How many units are currently in storage (not yet assigned to a room).
     */
    public function availableQuantity(): int
    {
        return max(0, $this->total_quantity - $this->deployedQuantity());
    }
}
