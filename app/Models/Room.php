<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'room_number',
        'room_location_id',
        'notes',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(RoomLocation::class, 'room_location_id');
    }

    public function furniture(): HasMany
    {
        return $this->hasMany(RoomFurniture::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(RoomPurchase::class);
    }
}
