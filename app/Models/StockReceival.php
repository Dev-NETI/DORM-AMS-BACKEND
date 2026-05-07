<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceival extends Model
{
    use HasModifiedBy;
    protected $fillable = [
        'item_id',
        'department_id',
        'quantity',
        'unit_cost',
        'supplier_id',
        'delivery_receipt_no',
        'delivery_receipt_file',
        'received_by',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'quantity'    => 'decimal:2',
        'unit_cost'   => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReceivalDocument::class);
    }
}
