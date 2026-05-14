<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureItemLog;
use App\Models\RoomFurnitureStock;
use App\Models\Unit;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomFurnitureItemController extends Controller
{
    use ApiResponse;

    /**
     * List all furniture items that have a room_furniture_stock record,
     * with deployed and available quantities appended.
     */
    public function index(Request $request): JsonResponse
    {
        $stocks = RoomFurnitureStock::with(['item.category', 'item.unit'])
            ->orderBy('id')
            ->get();

        // Preload deployed quantities in a single query
        $itemIds   = $stocks->pluck('item_id');
        $deployed  = RoomFurniture::whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $result = $stocks->map(function (RoomFurnitureStock $stock) use ($deployed) {
            $dep = (int) ($deployed[$stock->item_id] ?? 0);
            return [
                'id'               => $stock->id,
                'item_id'          => $stock->item_id,
                'item'             => $stock->item,
                'total_quantity'   => $stock->total_quantity,
                'deployed'         => $dep,
                'available'        => max(0, $stock->total_quantity - $dep),
                'notes'            => $stock->notes,
                'updated_at'       => $stock->updated_at,
            ];
        });

        return $this->success($result);
    }

    /**
     * Create a new furniture item (with optional initial stock quantity).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category_name'  => 'nullable|string|max:255',
            'total_quantity' => 'required|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Prevent duplicate names
        if (Item::where('name', $validated['name'])->exists()) {
            return $this->error("An item named \"{$validated['name']}\" already exists.", 422);
        }

        $result = DB::transaction(function () use ($validated) {
            // Resolve category
            $category = null;
            if (! empty($validated['category_name'])) {
                $category = Category::firstOrCreate(
                    ['name' => trim($validated['category_name'])],
                );
            } else {
                $category = Category::firstOrCreate(['name' => 'Furniture & Fixtures']);
            }

            $unit = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);

            $item = Item::create([
                'name'            => $validated['name'],
                'category_id'     => $category->id,
                'unit_id'         => $unit->id,
                'item_type'       => 'fixed_asset',
                'description'     => "Dormitory room {$validated['name']}",
                'min_stock_level' => 0,
            ]);

            $stock = RoomFurnitureStock::create([
                'item_id'        => $item->id,
                'total_quantity' => $validated['total_quantity'],
                'notes'          => $validated['notes'] ?? null,
            ]);

            return ['item' => $item, 'stock' => $stock];
        });

        RoomFurnitureItemLog::record(
            itemName:   $result['item']->name,
            actionType: 'created',
            itemId:     $result['item']->id,
            userId:     $request->user()?->id,
        );

        return $this->created([
            'id'             => $result['stock']->id,
            'item_id'        => $result['item']->id,
            'item'           => $result['item']->load('category', 'unit'),
            'total_quantity' => $result['stock']->total_quantity,
            'deployed'       => 0,
            'available'      => $result['stock']->total_quantity,
        ], 'Furniture item created');
    }

    /**
     * Update item name and/or stock quantity.
     */
    public function update(Request $request, RoomFurnitureStock $roomFurnitureItem): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'total_quantity' => 'sometimes|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $roomFurnitureItem) {
            if (isset($validated['name'])) {
                $roomFurnitureItem->item->update(['name' => $validated['name']]);
            }

            $roomFurnitureItem->update([
                'total_quantity' => $validated['total_quantity'] ?? $roomFurnitureItem->total_quantity,
                'notes'          => $validated['notes'] ?? $roomFurnitureItem->notes,
            ]);
        });

        // Validate total_quantity ≥ deployed
        $deployed = (int) RoomFurniture::where('item_id', $roomFurnitureItem->item_id)->sum('quantity');
        if ($roomFurnitureItem->fresh()->total_quantity < $deployed) {
            // Revert
            $roomFurnitureItem->update(['total_quantity' => $deployed]);
            return $this->error(
                "Total quantity cannot be less than what is already deployed ({$deployed} units).",
                422
            );
        }

        return $this->success($roomFurnitureItem->load('item.category', 'item.unit'), 'Updated successfully');
    }

    /**
     * Delete a furniture item (only if not deployed in any room).
     */
    public function destroy(Request $request, RoomFurnitureStock $roomFurnitureItem): JsonResponse
    {
        $deployed = RoomFurniture::where('item_id', $roomFurnitureItem->item_id)->exists();
        if ($deployed) {
            return $this->error('Cannot delete an item that is still deployed in rooms.', 422);
        }

        $itemName = $roomFurnitureItem->item->name;
        $itemId   = $roomFurnitureItem->item_id;

        DB::transaction(function () use ($roomFurnitureItem, $itemId) {
            $roomFurnitureItem->delete();
            Item::find($itemId)?->delete();
        });

        RoomFurnitureItemLog::record(
            itemName:   $itemName,
            actionType: 'deleted',
            itemId:     null,   // item no longer exists
            userId:     $request->user()?->id,
        );

        return $this->success(null, 'Item deleted');
    }
}
