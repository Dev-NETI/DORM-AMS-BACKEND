<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdcRoomFurnitureDisposal;
use App\Models\FdcRoomFurnitureStock;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureItemVariant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FdcRoomFurnitureItemVariantController extends Controller
{
    use ApiResponse;

    private function fdcRoomIds()
    {
        return Room::whereHas('location', fn ($q) => $q->where('location_type', 'fdc'))->pluck('id');
    }

    /** GET /api/fdc-room-furniture-item-variants?item_id=X */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['item_id' => 'required|exists:items,id']);

        $itemId   = (int) $request->item_id;
        $variants = RoomFurnitureItemVariant::where('item_id', $itemId)
            ->orderBy('name')
            ->get();

        $stocks = FdcRoomFurnitureStock::where('item_id', $itemId)
            ->whereNotNull('sub_item_id')
            ->pluck('total_quantity', 'sub_item_id');

        $fdcRoomIds = $this->fdcRoomIds();

        $deployed = RoomFurniture::where('item_id', $itemId)
            ->whereNotNull('sub_item_id')
            ->whereIn('room_id', $fdcRoomIds)
            ->groupBy('sub_item_id')
            ->selectRaw('sub_item_id, SUM(quantity) as total')
            ->pluck('total', 'sub_item_id');

        $disposals = FdcRoomFurnitureDisposal::where('item_id', $itemId)
            ->whereNotNull('sub_item_id')
            ->groupBy('sub_item_id')
            ->selectRaw('sub_item_id, SUM(quantity) as total')
            ->pluck('total', 'sub_item_id');

        $result = $variants->map(function ($v) use ($stocks, $deployed, $disposals) {
            $total      = (int) ($stocks[$v->id] ?? 0);
            $dep        = (int) ($deployed[$v->id] ?? 0);
            $forDispose = (int) ($disposals[$v->id] ?? 0);
            $netTotal   = max(0, $total - $forDispose);
            return [
                'id'             => $v->id,
                'item_id'        => $v->item_id,
                'name'           => $v->name,
                'notes'          => $v->notes,
                'total_quantity' => $netTotal,
                'deployed'       => $dep,
                'for_disposal'   => $forDispose,
                'available'      => max(0, $netTotal - $dep),
            ];
        });

        return $this->success($result);
    }

    /** POST /api/fdc-room-furniture-item-variants */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id'  => 'required|exists:items,id',
            'name'     => 'required|string|max:255',
            'notes'    => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $variant = RoomFurnitureItemVariant::create([
            'item_id' => $validated['item_id'],
            'name'    => $validated['name'],
            'notes'   => $validated['notes'] ?? null,
        ]);

        $qty = (int) ($validated['quantity'] ?? 0);
        if ($qty > 0) {
            FdcRoomFurnitureStock::create([
                'item_id'        => $validated['item_id'],
                'sub_item_id'    => $variant->id,
                'total_quantity' => $qty,
            ]);
        }

        return $this->created(array_merge($variant->toArray(), [
            'total_quantity' => $qty,
            'deployed'       => 0,
            'available'      => $qty,
        ]), 'Variant added');
    }

    /** PUT /api/fdc-room-furniture-item-variants/{variant} */
    public function update(Request $request, RoomFurnitureItemVariant $roomFurnitureItemVariant): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'notes'    => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $roomFurnitureItemVariant->update([
            'name'  => $validated['name'],
            'notes' => $validated['notes'] ?? $roomFurnitureItemVariant->notes,
        ]);

        if (array_key_exists('quantity', $validated) && $validated['quantity'] !== null) {
            FdcRoomFurnitureStock::updateOrCreate(
                ['item_id' => $roomFurnitureItemVariant->item_id, 'sub_item_id' => $roomFurnitureItemVariant->id],
                ['total_quantity' => $validated['quantity']]
            );
        }

        return $this->success($roomFurnitureItemVariant, 'Variant updated');
    }

    /** DELETE /api/fdc-room-furniture-item-variants/{variant} */
    public function destroy(RoomFurnitureItemVariant $roomFurnitureItemVariant): JsonResponse
    {
        $fdcRoomIds = $this->fdcRoomIds();
        $inUse = RoomFurniture::where('sub_item_id', $roomFurnitureItemVariant->id)
            ->whereIn('room_id', $fdcRoomIds)
            ->exists();
        if ($inUse) {
            return $this->error('This variant is currently assigned to one or more FDC rooms and cannot be deleted.', 422);
        }

        $roomFurnitureItemVariant->delete();

        return $this->success(null, 'Variant deleted');
    }
}
