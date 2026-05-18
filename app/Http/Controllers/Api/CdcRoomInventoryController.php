<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CdcRoomFurnitureDisposal;
use App\Models\CdcRoomFurnitureLog;
use App\Models\CdcRoomFurnitureStock;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureItemVariant;
use App\Models\RoomLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CdcRoomInventoryController extends Controller
{
    use ApiResponse;

    private function cdcRoomIds()
    {
        return Room::whereHas('location', fn ($q) => $q->where('location_type', 'cdc'))->pluck('id');
    }

    /** GET /api/cdc-room-inventory/matrix */
    public function matrix(): JsonResponse
    {
        $cdcRoomIds = $this->cdcRoomIds();

        $itemIds = CdcRoomFurnitureStock::whereNull('sub_item_id')->pluck('item_id');

        $items = Item::whereIn('id', $itemIds)
            ->with(['roomFurnitureVariants'])
            ->orderBy('name')
            ->get(['id', 'name']);

        $deployed = RoomFurniture::whereIn('item_id', $itemIds)
            ->whereIn('room_id', $cdcRoomIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $variantStockTotals = CdcRoomFurnitureStock::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(total_quantity) as total')
            ->pluck('total', 'item_id');

        $baseStocks = CdcRoomFurnitureStock::whereIn('item_id', $itemIds)
            ->whereNull('sub_item_id')
            ->pluck('total_quantity', 'item_id');

        $disposalBase = CdcRoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $disposalVariant = CdcRoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $itemsData = $items->map(function (Item $item) use ($deployed, $variantStockTotals, $baseStocks, $disposalBase, $disposalVariant) {
            $dep      = (int) ($deployed[$item->id] ?? 0);
            $variants = $item->roomFurnitureVariants->map(fn ($v) => [
                'id'   => $v->id,
                'name' => $v->name,
            ])->values();

            $hasVariants = $variants->isNotEmpty();
            $rawTotal    = $hasVariants
                ? (int) ($variantStockTotals[$item->id] ?? 0)
                : (int) ($baseStocks[$item->id] ?? 0);

            $forDisposal = $hasVariants
                ? (int) ($disposalVariant[$item->id] ?? 0)
                : (int) ($disposalBase[$item->id] ?? 0);

            $total = max(0, $rawTotal - $forDisposal);

            return [
                'id'             => $item->id,
                'name'           => $item->name,
                'total_quantity' => $total,
                'deployed'       => $dep,
                'for_disposal'   => $forDisposal,
                'available'      => max(0, $total - $dep),
                'has_variants'   => $hasVariants,
                'variants'       => $variants,
            ];
        });

        $variantItemIds = $itemsData->where('has_variants', true)->pluck('id')->all();

        $locations = RoomLocation::where('location_type', 'cdc')
            ->with([
                'rooms'           => fn ($q) => $q->orderBy('room_number'),
                'rooms.furniture' => fn ($q) => $q->whereIn('item_id', $itemIds->all()),
                'rooms.furniture.subItem',
            ])
            ->orderBy('name')
            ->get();

        $grandTotals = [];

        $locationsData = $locations->map(function (RoomLocation $loc) use (&$grandTotals, $variantItemIds) {
            $subtotals = [];

            $roomsData = $loc->rooms->map(function (Room $room) use (&$subtotals, $variantItemIds) {
                $byItem     = $room->furniture->groupBy('item_id');
                $quantities = [];

                foreach ($byItem as $itemId => $records) {
                    $itemId = (int) $itemId;

                    if (in_array($itemId, $variantItemIds, true)) {
                        $variants = $records->map(fn ($rf) => [
                            'sub_item_id' => $rf->sub_item_id,
                            'name'        => $rf->subItem?->name ?? '?',
                            'qty'         => $rf->quantity,
                        ])->values();

                        $total = $records->sum('quantity');
                        $quantities[$itemId] = [
                            'type'     => 'variant',
                            'variants' => $variants,
                            'total'    => $total,
                        ];
                        $subtotals[$itemId] = ($subtotals[$itemId] ?? 0) + $total;
                    } else {
                        $qty = $records->first()->quantity;
                        $quantities[$itemId] = [
                            'type' => 'simple',
                            'qty'  => $qty,
                        ];
                        $subtotals[$itemId] = ($subtotals[$itemId] ?? 0) + $qty;
                    }
                }

                return [
                    'id'          => $room->id,
                    'room_number' => $room->room_number,
                    'notes'       => $room->notes,
                    'quantities'  => $quantities,
                ];
            });

            foreach ($subtotals as $itemId => $qty) {
                $grandTotals[$itemId] = ($grandTotals[$itemId] ?? 0) + $qty;
            }

            return [
                'id'            => $loc->id,
                'name'          => $loc->name,
                'floor'         => $loc->floor,
                'location_type' => $loc->location_type,
                'description'   => $loc->description,
                'rooms'         => $roomsData,
                'subtotals'     => $subtotals,
            ];
        });

        return $this->success([
            'items'        => $itemsData,
            'locations'    => $locationsData,
            'grand_totals' => $grandTotals,
        ]);
    }

    /** PUT /api/cdc-room-inventory/cell/{roomId}/{itemId} */
    public function updateCell(Request $request, int $roomId, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'notes'    => 'nullable|string',
        ]);

        Room::findOrFail($roomId);
        Item::findOrFail($itemId);

        $existing  = RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->whereNull('sub_item_id')->first();
        $qtyBefore = $existing?->quantity ?? 0;
        $qtyAfter  = $validated['quantity'];

        if ($qtyAfter > 0) {
            $stock = CdcRoomFurnitureStock::where('item_id', $itemId)->whereNull('sub_item_id')->first();
            if ($stock) {
                $cdcRoomIds      = $this->cdcRoomIds();
                $forDisposal     = (int) CdcRoomFurnitureDisposal::where('item_id', $itemId)->whereNull('sub_item_id')->sum('quantity');
                $currentDeployed = (int) RoomFurniture::where('item_id', $itemId)->whereIn('room_id', $cdcRoomIds)->sum('quantity');
                $newDeployed     = $currentDeployed - $qtyBefore + $qtyAfter;
                $netTotal        = max(0, $stock->total_quantity - $forDisposal);
                if ($newDeployed > $netTotal) {
                    $available = max(0, $netTotal - $currentDeployed + $qtyBefore);
                    return $this->error("Insufficient stock. Only {$available} unit(s) available for this item.", 422);
                }
            }
        }

        if ($qtyAfter === 0) {
            RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->whereNull('sub_item_id')->delete();
        } else {
            RoomFurniture::updateOrCreate(
                ['room_id' => $roomId, 'item_id' => $itemId, 'sub_item_id' => null],
                ['quantity' => $qtyAfter, 'notes' => $validated['notes'] ?? null]
            );
        }

        if ($qtyBefore !== $qtyAfter) {
            CdcRoomFurnitureLog::record(
                roomId:     $roomId,
                itemId:     $itemId,
                actionType: 'adjustment',
                qtyBefore:  $qtyBefore,
                qtyAfter:   $qtyAfter,
                userId:     $request->user()?->id,
                notes:      $validated['notes'] ?? null,
            );
        }

        return $this->success(null, 'Quantity updated');
    }

    /** PUT /api/cdc-room-inventory/cell/{roomId}/{itemId}/{subItemId} */
    public function updateVariantCell(Request $request, int $roomId, int $itemId, int $subItemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'notes'    => 'nullable|string',
        ]);

        Room::findOrFail($roomId);
        $variant = RoomFurnitureItemVariant::findOrFail($subItemId);

        $existing  = RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->where('sub_item_id', $subItemId)->first();
        $qtyBefore = $existing?->quantity ?? 0;
        $qtyAfter  = $validated['quantity'];

        if ($qtyAfter > 0) {
            $stock = CdcRoomFurnitureStock::where('item_id', $itemId)->where('sub_item_id', $subItemId)->first();
            if ($stock) {
                $cdcRoomIds      = $this->cdcRoomIds();
                $forDisposal     = (int) CdcRoomFurnitureDisposal::where('item_id', $itemId)->where('sub_item_id', $subItemId)->sum('quantity');
                $currentDeployed = (int) RoomFurniture::where('item_id', $itemId)->where('sub_item_id', $subItemId)->whereIn('room_id', $cdcRoomIds)->sum('quantity');
                $newDeployed     = $currentDeployed - $qtyBefore + $qtyAfter;
                $netTotal        = max(0, $stock->total_quantity - $forDisposal);
                if ($newDeployed > $netTotal) {
                    $available = max(0, $netTotal - $currentDeployed + $qtyBefore);
                    return $this->error("Insufficient stock. Only {$available} unit(s) available for \"{$variant->name}\".", 422);
                }
            }
        }

        if ($qtyAfter === 0) {
            RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->where('sub_item_id', $subItemId)->delete();
        } else {
            RoomFurniture::updateOrCreate(
                ['room_id' => $roomId, 'item_id' => $itemId, 'sub_item_id' => $subItemId],
                ['quantity' => $qtyAfter, 'notes' => $validated['notes'] ?? null]
            );
        }

        if ($qtyBefore !== $qtyAfter) {
            CdcRoomFurnitureLog::record(
                roomId:     $roomId,
                itemId:     $itemId,
                actionType: 'adjustment',
                qtyBefore:  $qtyBefore,
                qtyAfter:   $qtyAfter,
                userId:     $request->user()?->id,
                notes:      $variant->name . (($validated['notes'] ?? null) ? ': ' . $validated['notes'] : ''),
            );
        }

        return $this->success(null, 'Quantity updated');
    }

    /** PUT /api/cdc-room-inventory/room/{room} */
    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'quantities'   => 'required|array',
            'quantities.*' => 'integer|min:0',
        ]);

        $cdcRoomIds = $this->cdcRoomIds();

        $itemIds      = array_keys($validated['quantities']);
        $stocks       = CdcRoomFurnitureStock::whereIn('item_id', $itemIds)->whereNull('sub_item_id')->get()->keyBy('item_id');
        $deployedSums = RoomFurniture::whereIn('item_id', $itemIds)->whereIn('room_id', $cdcRoomIds)->groupBy('item_id')->selectRaw('item_id, SUM(quantity) as total')->pluck('total', 'item_id');
        $disposalSums = CdcRoomFurnitureDisposal::whereIn('item_id', $itemIds)->whereNull('sub_item_id')->groupBy('item_id')->selectRaw('item_id, SUM(quantity) as total')->pluck('total', 'item_id');

        $currentRoom = RoomFurniture::where('room_id', $room->id)->whereIn('item_id', $itemIds)->whereNull('sub_item_id')->get()->keyBy('item_id');

        foreach ($validated['quantities'] as $itemId => $qty) {
            $itemId   = (int) $itemId;
            $qtyAfter = max(0, (int) $qty);
            $stock    = $stocks[$itemId] ?? null;

            if ($stock && $qtyAfter > 0) {
                $current     = (int) ($currentRoom[$itemId]?->quantity ?? 0);
                $forDisposal = (int) ($disposalSums[$itemId] ?? 0);
                $netTotal    = max(0, $stock->total_quantity - $forDisposal);
                $newDeployed = (int) ($deployedSums[$itemId] ?? 0) - $current + $qtyAfter;
                if ($newDeployed > $netTotal) {
                    $item      = Item::find($itemId);
                    $available = max(0, $netTotal - (int) ($deployedSums[$itemId] ?? 0) + $current);
                    return $this->error("Insufficient stock for \"{$item?->name}\". Only {$available} unit(s) available.", 422);
                }
            }
        }

        $userId = $request->user()?->id;

        DB::transaction(function () use ($room, $validated, $userId, $currentRoom) {
            foreach ($validated['quantities'] as $itemId => $qty) {
                $itemId    = (int) $itemId;
                $qtyBefore = (int) ($currentRoom[$itemId]?->quantity ?? 0);
                $qtyAfter  = max(0, (int) $qty);

                if ($qtyAfter <= 0) {
                    RoomFurniture::where('room_id', $room->id)->where('item_id', $itemId)->whereNull('sub_item_id')->delete();
                } else {
                    RoomFurniture::updateOrCreate(
                        ['room_id' => $room->id, 'item_id' => $itemId, 'sub_item_id' => null],
                        ['quantity' => $qtyAfter]
                    );
                }

                if ($qtyBefore !== $qtyAfter) {
                    CdcRoomFurnitureLog::record(
                        roomId:     $room->id,
                        itemId:     $itemId,
                        actionType: 'adjustment',
                        qtyBefore:  $qtyBefore,
                        qtyAfter:   $qtyAfter,
                        userId:     $userId,
                    );
                }
            }
        });

        return $this->success(null, 'CDC room inventory updated');
    }
}
