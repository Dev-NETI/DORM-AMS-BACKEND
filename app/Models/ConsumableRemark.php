<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableRemark extends Model
{
    protected $fillable = ['consumable_item_id', 'date', 'remarks'];

    protected $casts = ['date' => 'date'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConsumableItem::class, 'consumable_item_id');
    }
}
