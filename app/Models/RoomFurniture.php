<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomFurniture extends Model
{
    use HasModifiedBy;

    protected $table = 'room_furniture';

    protected $fillable = [
        'room_id',
        'item_id',
        'quantity',
        'model',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
