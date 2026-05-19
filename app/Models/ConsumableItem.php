<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumableItem extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'consumable_category_id',
        'name',
        'unit_id',
        'item_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ConsumableCategory::class, 'consumable_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Mirror entry in the general items table (for inventory_stocks tracking) */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(ConsumableOpeningBalance::class);
    }

    public function receivals(): HasMany
    {
        return $this->hasMany(ConsumableReceival::class);
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(ConsumableIssuance::class);
    }
}
