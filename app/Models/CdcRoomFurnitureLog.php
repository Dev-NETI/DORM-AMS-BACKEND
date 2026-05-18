<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdcRoomFurnitureLog extends Model
{
    protected $table = 'cdc_room_furniture_logs';

    protected $fillable = [
        'room_id',
        'item_id',
        'action_type',
        'qty_before',
        'qty_after',
        'qty_change',
        'reference_id',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'qty_before' => 'integer',
        'qty_after'  => 'integer',
        'qty_change' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CdcRoomPurchase::class, 'reference_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public static function record(
        int    $roomId,
        int    $itemId,
        string $actionType,
        int    $qtyBefore,
        int    $qtyAfter,
        ?int   $userId      = null,
        ?int   $referenceId = null,
        ?string $notes      = null,
    ): self {
        return self::create([
            'room_id'      => $roomId,
            'item_id'      => $itemId,
            'action_type'  => $actionType,
            'qty_before'   => $qtyBefore,
            'qty_after'    => $qtyAfter,
            'qty_change'   => $qtyAfter - $qtyBefore,
            'reference_id' => $referenceId,
            'notes'        => $notes,
            'performed_by' => $userId,
        ]);
    }
}
