<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomAsset extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'asset_code',
        'item_id',
        'location_id',
        'supplier_id',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'purchase_document_no',
        'status',
        'condition',
        'notes',
        'department_id',
        'acquired_by',
    ];

    protected $casts = [
        'purchase_date'  => 'date',
        'purchase_price' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(RoomLocation::class, 'location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function acquiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquired_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RoomAssetDocument::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(RoomAssetTransfer::class);
    }
}
