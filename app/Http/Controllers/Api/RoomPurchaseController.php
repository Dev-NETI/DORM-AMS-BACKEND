<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureItemLog;
use App\Models\RoomFurnitureLog;
use App\Models\RoomFurnitureStock;
use App\Models\RoomPurchase;
use App\Models\RoomPurchaseDocument;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomPurchaseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = RoomPurchase::with(['item', 'room.location', 'purchasedBy'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at');

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        return $this->success($query->get());
    }

    /**
     * Record receiving new furniture stock.
     * - Always increments room_furniture_stock.total_quantity
     * - If room_id provided: also assigns quantity to that room (room_furniture)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'sub_item_id'   => 'nullable|exists:room_furniture_item_variants,id',
            'quantity'      => 'required|integer|min:1',
            'room_id'       => 'nullable|exists:rooms,id',
            'purchase_date' => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);

        $subItemId = $validated['sub_item_id'] ?? null;

        $purchase = DB::transaction(function () use ($validated, $request, $subItemId) {
            // Increment the correct stock record (per-variant or base)
            $stock = RoomFurnitureStock::firstOrCreate(
                ['item_id' => $validated['item_id'], 'sub_item_id' => $subItemId],
                ['total_quantity' => 0]
            );
            $stock->increment('total_quantity', $validated['quantity']);

            $purchase = RoomPurchase::create([
                'item_id'       => $validated['item_id'],
                'sub_item_id'   => $subItemId,
                'room_id'       => $validated['room_id'] ?? null,
                'quantity'      => $validated['quantity'],
                'purchase_date' => $validated['purchase_date'] ?? null,
                'notes'         => $validated['notes'] ?? null,
                'purchased_by'  => $request->user()->id,
            ]);

            // If a room is specified, also assign quantity to that room
            if (! empty($validated['room_id'])) {
                $furniture = RoomFurniture::firstOrCreate(
                    ['room_id' => $validated['room_id'], 'item_id' => $validated['item_id'], 'sub_item_id' => $subItemId],
                    ['quantity' => 0]
                );
                $qtyBefore = $furniture->quantity;
                $furniture->increment('quantity', $validated['quantity']);

                RoomFurnitureLog::record(
                    roomId:      $validated['room_id'],
                    itemId:      $validated['item_id'],
                    actionType:  'purchase',
                    qtyBefore:   $qtyBefore,
                    qtyAfter:    $qtyBefore + $validated['quantity'],
                    userId:      $request->user()->id,
                    referenceId: $purchase->id,
                    notes:       $validated['notes'] ?? null,
                );
            }

            return $purchase;
        });

        $purchase->load(['item', 'room.location', 'purchasedBy', 'subItem']);

        $variantNote = $subItemId ? " [{$purchase->subItem?->name}]" : '';
        RoomFurnitureItemLog::record(
            itemName:   $purchase->item->name,
            actionType: 'purchased',
            itemId:     $purchase->item_id,
            userId:     $request->user()->id,
            notes:      "Received {$validated['quantity']} unit(s){$variantNote}" . (($validated['notes'] ?? null) ? ": {$validated['notes']}" : ''),
            purchaseId: $purchase->id,
        );

        return $this->created($purchase, 'Stock received successfully');
    }

    /**
     * Upload documents for a purchase record.
     * POST /api/room-purchases/{roomPurchase}/documents
     */
    public function storeDocuments(Request $request, RoomPurchase $roomPurchase): JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $path     = $file->store('room-purchases', 'public');
            $uploaded[] = RoomPurchaseDocument::create([
                'room_purchase_id' => $roomPurchase->id,
                'file_path'        => $path,
                'original_name'    => $file->getClientOriginalName(),
                'uploaded_by'      => $request->user()?->id,
            ]);
        }

        return $this->success($uploaded, 'Documents uploaded');
    }

    public function destroy(RoomPurchase $roomPurchase): JsonResponse
    {
        $roomPurchase->delete();

        return $this->success(null, 'Purchase record deleted');
    }
}
