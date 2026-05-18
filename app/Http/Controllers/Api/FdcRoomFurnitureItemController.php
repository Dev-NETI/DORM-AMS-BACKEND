<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FdcRoomFurnitureDisposal;
use App\Models\FdcRoomFurnitureStock;
use App\Models\Item;
use App\Models\RoomFurniture;
use App\Models\FdcRoomFurnitureItemLog;
use App\Models\Unit;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FdcRoomFurnitureItemController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $stocks = FdcRoomFurnitureStock::with(['item.category', 'item.unit'])
            ->whereNull('sub_item_id')
            ->orderBy('id')
            ->get();

        $itemIds = $stocks->pluck('item_id');

        $variantTotals = FdcRoomFurnitureStock::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(total_quantity) as total')
            ->pluck('total', 'item_id');

        // Deployed: from rooms tagged as FDC (location_type='fdc')
        $fdcRoomIds = \App\Models\Room::whereHas('location', fn ($q) => $q->where('location_type', 'fdc'))
            ->pluck('id');

        $deployed = RoomFurniture::whereIn('item_id', $itemIds)
            ->whereIn('room_id', $fdcRoomIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $disposalBase = FdcRoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $disposalVariant = FdcRoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $result = $stocks->map(function (FdcRoomFurnitureStock $stock) use ($deployed, $variantTotals, $disposalBase, $disposalVariant) {
            $dep         = (int) ($deployed[$stock->item_id] ?? 0);
            $hasVariants = isset($variantTotals[$stock->item_id]);
            $rawTotal    = $hasVariants
                ? (int) $variantTotals[$stock->item_id]
                : $stock->total_quantity;
            $forDisposal = $hasVariants
                ? (int) ($disposalVariant[$stock->item_id] ?? 0)
                : (int) ($disposalBase[$stock->item_id] ?? 0);

            $netTotal = max(0, $rawTotal - $forDisposal);

            return [
                'id'             => $stock->id,
                'item_id'        => $stock->item_id,
                'item'           => $stock->item,
                'total_quantity' => $netTotal,
                'deployed'       => $dep,
                'for_disposal'   => $forDisposal,
                'available'      => max(0, $netTotal - $dep),
                'has_variants'   => $hasVariants,
                'notes'          => $stock->notes,
                'updated_at'     => $stock->updated_at,
            ];
        });

        return $this->success($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category_name'  => 'nullable|string|max:255',
            'total_quantity' => 'required|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $category = ! empty($validated['category_name'])
                ? Category::firstOrCreate(['name' => trim($validated['category_name'])])
                : Category::firstOrCreate(['name' => 'Furniture & Fixtures']);

            $unit = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);

            $item = Item::firstOrCreate(
                ['name' => $validated['name']],
                [
                    'category_id'     => $category->id,
                    'unit_id'         => $unit->id,
                    'item_type'       => 'fixed_asset',
                    'description'     => "FDC room {$validated['name']}",
                    'min_stock_level' => 0,
                ],
            );

            $stock = FdcRoomFurnitureStock::firstOrCreate(
                ['item_id' => $item->id, 'sub_item_id' => null],
                [
                    'total_quantity' => $validated['total_quantity'],
                    'notes'          => $validated['notes'] ?? null,
                ],
            );

            return ['item' => $item, 'stock' => $stock];
        });

        FdcRoomFurnitureItemLog::record(
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
        ], 'FDC furniture item created');
    }

    public function update(Request $request, FdcRoomFurnitureStock $fdcRoomFurnitureItem): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'total_quantity' => 'sometimes|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $fdcRoomFurnitureItem) {
            if (isset($validated['name'])) {
                $fdcRoomFurnitureItem->item->update(['name' => $validated['name']]);
            }

            $fdcRoomFurnitureItem->update([
                'total_quantity' => $validated['total_quantity'] ?? $fdcRoomFurnitureItem->total_quantity,
                'notes'          => $validated['notes'] ?? $fdcRoomFurnitureItem->notes,
            ]);
        });

        $fdcRoomIds  = \App\Models\Room::whereHas('location', fn ($q) => $q->where('location_type', 'fdc'))->pluck('id');
        $deployed    = (int) RoomFurniture::where('item_id', $fdcRoomFurnitureItem->item_id)->whereIn('room_id', $fdcRoomIds)->sum('quantity');

        if ($fdcRoomFurnitureItem->fresh()->total_quantity < $deployed) {
            $fdcRoomFurnitureItem->update(['total_quantity' => $deployed]);
            return $this->error(
                "Total quantity cannot be less than what is already deployed ({$deployed} units).",
                422
            );
        }

        return $this->success($fdcRoomFurnitureItem->load('item.category', 'item.unit'), 'Updated successfully');
    }

    public function destroy(Request $request, FdcRoomFurnitureStock $fdcRoomFurnitureItem): JsonResponse
    {
        $fdcRoomIds = \App\Models\Room::whereHas('location', fn ($q) => $q->where('location_type', 'fdc'))->pluck('id');
        $deployed   = RoomFurniture::where('item_id', $fdcRoomFurnitureItem->item_id)->whereIn('room_id', $fdcRoomIds)->exists();

        if ($deployed) {
            return $this->error('Cannot delete an item that is still deployed in FDC rooms.', 422);
        }

        $itemName = $fdcRoomFurnitureItem->item->name;
        $itemId   = $fdcRoomFurnitureItem->item_id;

        DB::transaction(function () use ($fdcRoomFurnitureItem, $itemId) {
            $fdcRoomFurnitureItem->delete();
            // Only delete the base item if no other stock records (NDB/FDC) reference it
            $hasOtherStock = \App\Models\RoomFurnitureStock::where('item_id', $itemId)->exists()
                || FdcRoomFurnitureStock::where('item_id', $itemId)->exists();
            if (! $hasOtherStock) {
                Item::find($itemId)?->delete();
            }
        });

        FdcRoomFurnitureItemLog::record(
            itemName:   $itemName,
            actionType: 'deleted',
            itemId:     null,
            userId:     $request->user()?->id,
        );

        return $this->success(null, 'FDC item deleted');
    }
}
