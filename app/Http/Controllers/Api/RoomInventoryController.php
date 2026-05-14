<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureLog;
use App\Models\RoomFurnitureStock;
use App\Models\RoomLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomInventoryController extends Controller
{
    use ApiResponse;

    /**
     * Return the full matrix.
     * Only items that have a room_furniture_stock record are included (registered furniture items).
     * Each item also carries total_quantity, deployed, and available counts.
     */
    public function matrix(): JsonResponse
    {
        // Only furniture items that are registered in room_furniture_stock
        $items = Item::whereHas('roomFurnitureStock')
            ->with('roomFurnitureStock')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Preload deployed quantities per item in one query
        $itemIds  = $items->pluck('id');
        $deployed = RoomFurniture::whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        // Append stock info to each item
        $itemsData = $items->map(function (Item $item) use ($deployed) {
            $dep   = (int) ($deployed[$item->id] ?? 0);
            $total = $item->roomFurnitureStock?->total_quantity ?? 0;
            return [
                'id'             => $item->id,
                'name'           => $item->name,
                'total_quantity' => $total,
                'deployed'       => $dep,
                'available'      => max(0, $total - $dep),
            ];
        });

        // All locations with rooms and their furniture
        $locations = RoomLocation::with([
            'rooms' => fn ($q) => $q->orderBy('room_number'),
            'rooms.furniture',
        ])->orderBy('name')->get();

        $grandTotals = [];

        $locationsData = $locations->map(function (RoomLocation $loc) use (&$grandTotals) {
            $subtotals = [];

            $roomsData = $loc->rooms->map(function (Room $room) use (&$subtotals) {
                $quantities = [];

                foreach ($room->furniture as $rf) {
                    $quantities[$rf->item_id] = [
                        'qty'   => $rf->quantity,
                        'model' => $rf->model,
                    ];
                    $subtotals[$rf->item_id]  = ($subtotals[$rf->item_id] ?? 0) + $rf->quantity;
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

    /**
     * Update a single quantity cell: room × item.
     * PUT /api/room-inventory/cell/{roomId}/{itemId}
     */
    public function updateCell(Request $request, int $roomId, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'model'    => 'nullable|string|max:255',
            'notes'    => 'nullable|string',
        ]);

        Room::findOrFail($roomId);
        Item::findOrFail($itemId);

        $existing  = RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->first();
        $qtyBefore = $existing?->quantity ?? 0;
        $qtyAfter  = $validated['quantity'];

        // Stock validation
        if ($qtyAfter > 0) {
            $stock = RoomFurnitureStock::where('item_id', $itemId)->first();
            if ($stock) {
                $currentDeployed = (int) RoomFurniture::where('item_id', $itemId)->sum('quantity');
                $newDeployed     = $currentDeployed - $qtyBefore + $qtyAfter;
                if ($newDeployed > $stock->total_quantity) {
                    $available = max(0, $stock->total_quantity - $currentDeployed + $qtyBefore);
                    return $this->error(
                        "Insufficient stock. Only {$available} unit(s) available for this item.",
                        422
                    );
                }
            }
        }

        if ($qtyAfter === 0) {
            RoomFurniture::where('room_id', $roomId)->where('item_id', $itemId)->delete();
        } else {
            RoomFurniture::updateOrCreate(
                ['room_id' => $roomId, 'item_id' => $itemId],
                [
                    'quantity' => $qtyAfter,
                    'model'    => $validated['model'] ?? null,
                    'notes'    => $validated['notes'] ?? null,
                ]
            );
        }

        if ($qtyBefore !== $qtyAfter) {
            RoomFurnitureLog::record(
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

    /**
     * Bulk update all quantities for a single room.
     * PUT /api/room-inventory/room/{room}
     */
    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'quantities'   => 'required|array',
            'quantities.*' => 'integer|min:0',
        ]);

        // Pre-validate all stock constraints before writing anything
        $itemIds      = array_keys($validated['quantities']);
        $stocks       = RoomFurnitureStock::whereIn('item_id', $itemIds)->get()->keyBy('item_id');
        $deployedSums = RoomFurniture::whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $currentRoom = RoomFurniture::where('room_id', $room->id)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        foreach ($validated['quantities'] as $itemId => $qty) {
            $itemId   = (int) $itemId;
            $qtyAfter = max(0, (int) $qty);
            $stock    = $stocks[$itemId] ?? null;

            if ($stock && $qtyAfter > 0) {
                $current     = (int) ($currentRoom[$itemId]?->quantity ?? 0);
                $newDeployed = (int) ($deployedSums[$itemId] ?? 0) - $current + $qtyAfter;
                if ($newDeployed > $stock->total_quantity) {
                    $item = Item::find($itemId);
                    $available = max(0, $stock->total_quantity - (int) ($deployedSums[$itemId] ?? 0) + $current);
                    return $this->error(
                        "Insufficient stock for \"{$item?->name}\". Only {$available} unit(s) available.",
                        422
                    );
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
                    RoomFurniture::where('room_id', $room->id)->where('item_id', $itemId)->delete();
                } else {
                    RoomFurniture::updateOrCreate(
                        ['room_id' => $room->id, 'item_id' => $itemId],
                        ['quantity' => $qtyAfter]
                    );
                }

                if ($qtyBefore !== $qtyAfter) {
                    RoomFurnitureLog::record(
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

        return $this->success(null, 'Room inventory updated');
    }
}
