<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdcRoomFurnitureItemLog extends Model
{
    protected $table = 'fdc_room_furniture_item_logs';

    protected $fillable = [
        'item_name',
        'item_id',
        'action_type',
        'performed_by',
        'notes',
        'fdc_room_purchase_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(FdcRoomPurchase::class, 'fdc_room_purchase_id');
    }

    public static function record(
        string  $itemName,
        string  $actionType,
        ?int    $itemId     = null,
        ?int    $userId     = null,
        ?string $notes      = null,
        ?int    $purchaseId = null,
    ): self {
        return self::create([
            'item_name'            => $itemName,
            'item_id'              => $itemId,
            'action_type'          => $actionType,
            'performed_by'         => $userId,
            'notes'                => $notes,
            'fdc_room_purchase_id' => $purchaseId,
        ]);
    }
}
