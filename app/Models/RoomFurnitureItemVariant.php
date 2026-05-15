<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomFurnitureItemVariant extends Model
{
    protected $fillable = ['item_id', 'name', 'notes'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomFurniture::class, 'sub_item_id');
    }
}
