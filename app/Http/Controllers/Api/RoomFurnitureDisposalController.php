<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureDisposal;
use App\Models\RoomFurnitureDisposedDocument;
use App\Models\RoomFurnitureDisposedHistory;
use App\Models\RoomFurnitureStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomFurnitureDisposalController extends Controller
{
    use ApiResponse;

    /** GET /api/room-furniture-disposals */
    public function index(): JsonResponse
    {
        $disposals = RoomFurnitureDisposal::with([
            'item.category',
            'subItem',
            'tagger:id,name',
        ])->orderByDesc('created_at')->get();

        return $this->success($disposals);
    }

    /** POST /api/room-furniture-disposals */
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

        // Validate that disposal quantity does not exceed available stock
        if ($subItemId) {
            $stock = RoomFurnitureStock::where('item_id', $itemId)
                ->where('sub_item_id', $subItemId)
                ->first();
            $deployed = (int) RoomFurniture::where('item_id', $itemId)
                ->where('sub_item_id', $subItemId)
                ->sum('quantity');
            $existingDisposal = (int) RoomFurnitureDisposal::where('item_id', $itemId)
                ->where('sub_item_id', $subItemId)
                ->sum('quantity');
        } else {
            $stock = RoomFurnitureStock::where('item_id', $itemId)
                ->whereNull('sub_item_id')
                ->first();
            $deployed = (int) RoomFurniture::where('item_id', $itemId)->sum('quantity');
            $existingDisposal = (int) RoomFurnitureDisposal::where('item_id', $itemId)
                ->whereNull('sub_item_id')
                ->sum('quantity');
        }

        $total     = $stock?->total_quantity ?? 0;
        $available = max(0, $total - $deployed - $existingDisposal);

        if ($quantity > $available) {
            return $this->error(
                "Only {$available} unit(s) available for disposal (total: {$total}, deployed: {$deployed}, already tagged: {$existingDisposal}).",
                422
            );
        }

        $disposal = RoomFurnitureDisposal::create([
            'item_id'     => $itemId,
            'sub_item_id' => $subItemId,
            'quantity'    => $quantity,
            'notes'       => $validated['notes'] ?? null,
            'tagged_by'   => $request->user()?->id,
        ]);

        return $this->created($disposal->load('item', 'subItem', 'tagger:id,name'), 'Tagged for disposal');
    }

    /** DELETE /api/room-furniture-disposals/{disposal} */
    public function destroy(RoomFurnitureDisposal $roomFurnitureDisposal): JsonResponse
    {
        $roomFurnitureDisposal->delete();
        return $this->success(null, 'Disposal record removed');
    }

    /** POST /api/room-furniture-disposals/{disposal}/dispose */
    public function dispose(Request $request, RoomFurnitureDisposal $roomFurnitureDisposal): JsonResponse
    {
        $request->validate([
            'additional_notes' => 'nullable|string',
            'disposed_at'      => 'nullable|date',
            'documents'        => 'required|array|min:1',
            'documents.*'      => 'required|file|mimes:pdf|max:20480',
        ]);

        $history = RoomFurnitureDisposedHistory::create([
            'item_id'          => $roomFurnitureDisposal->item_id,
            'sub_item_id'      => $roomFurnitureDisposal->sub_item_id,
            'quantity'         => $roomFurnitureDisposal->quantity,
            'additional_notes' => $request->input('additional_notes'),
            'disposed_by'      => $request->user()?->id,
            'disposed_at'      => $request->input('disposed_at') ?? now(),
        ]);

        foreach ($request->file('documents') as $file) {
            $path = $file->store('disposal-documents/ndb', 'public');
            RoomFurnitureDisposedDocument::create([
                'disposed_history_id' => $history->id,
                'file_path'           => $path,
                'file_name'           => $file->getClientOriginalName(),
            ]);
        }

        $roomFurnitureDisposal->delete();

        return $this->success(
            $history->load('item', 'subItem', 'disposedByUser:id,name', 'documents'),
            'Item disposed successfully.'
        );
    }

    /** GET /api/room-furniture-disposed-history */
    public function history(): JsonResponse
    {
        $records = RoomFurnitureDisposedHistory::with([
            'item.category',
            'subItem',
            'disposedByUser:id,name',
            'documents',
        ])->orderByDesc('disposed_at')->get();

        return $this->success($records);
    }
}
