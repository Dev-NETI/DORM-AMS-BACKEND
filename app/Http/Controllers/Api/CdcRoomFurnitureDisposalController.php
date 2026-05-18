<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CdcRoomFurnitureDisposal;
use App\Models\CdcRoomFurnitureStock;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdcRoomFurnitureDisposalController extends Controller
{
    use ApiResponse;

    /** GET /api/cdc-room-furniture-disposals */
    public function index(): JsonResponse
    {
        $disposals = CdcRoomFurnitureDisposal::with([
            'item.category',
            'subItem',
            'tagger:id,name',
        ])->orderByDesc('created_at')->get();

        return $this->success($disposals);
    }

    /** POST /api/cdc-room-furniture-disposals */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id'     => 'required|exists:items,id',
            'sub_item_id' => 'nullable|exists:room_furniture_item_variants,id',
            'quantity'    => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $itemId    = (int) $validated['item_id'];
        $subItemId = isset($validated['sub_item_id']) ? (int) $validated['sub_item_id'] : null;
        $quantity  = (int) $validated['quantity'];

        $cdcRoomIds = Room::whereHas('location', fn ($q) => $q->where('location_type', 'cdc'))->pluck('id');

        if ($subItemId) {
            $stock            = CdcRoomFurnitureStock::where('item_id', $itemId)->where('sub_item_id', $subItemId)->first();
            $deployed         = (int) RoomFurniture::where('item_id', $itemId)->where('sub_item_id', $subItemId)->whereIn('room_id', $cdcRoomIds)->sum('quantity');
            $existingDisposal = (int) CdcRoomFurnitureDisposal::where('item_id', $itemId)->where('sub_item_id', $subItemId)->sum('quantity');
        } else {
            $stock            = CdcRoomFurnitureStock::where('item_id', $itemId)->whereNull('sub_item_id')->first();
            $deployed         = (int) RoomFurniture::where('item_id', $itemId)->whereIn('room_id', $cdcRoomIds)->sum('quantity');
            $existingDisposal = (int) CdcRoomFurnitureDisposal::where('item_id', $itemId)->whereNull('sub_item_id')->sum('quantity');
        }

        $total     = $stock?->total_quantity ?? 0;
        $available = max(0, $total - $deployed - $existingDisposal);

        if ($quantity > $available) {
            return $this->error(
                "Only {$available} unit(s) available for disposal (total: {$total}, deployed: {$deployed}, already tagged: {$existingDisposal}).",
                422
            );
        }

        $disposal = CdcRoomFurnitureDisposal::create([
            'item_id'     => $itemId,
            'sub_item_id' => $subItemId,
            'quantity'    => $quantity,
            'notes'       => $validated['notes'] ?? null,
            'tagged_by'   => $request->user()?->id,
        ]);

        return $this->created($disposal->load('item', 'subItem', 'tagger:id,name'), 'Tagged for disposal');
    }

    /** DELETE /api/cdc-room-furniture-disposals/{disposal} */
    public function destroy(CdcRoomFurnitureDisposal $cdcRoomFurnitureDisposal): JsonResponse
    {
        $cdcRoomFurnitureDisposal->delete();
        return $this->success(null, 'Disposal record removed');
    }
}
