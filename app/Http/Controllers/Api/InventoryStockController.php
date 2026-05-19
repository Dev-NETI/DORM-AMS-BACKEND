<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    use ApiResponse;

    /**
     * List all stock records.
     * Filters: department_id, item_id, low_stock (bool)
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = InventoryStock::with(['item.category', 'item.unit', 'department']);

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        // Show only items below minimum stock threshold
        if ($request->boolean('low_stock')) {
            $query->whereHas('item', function ($q) {
                $q->where('min_stock_level', '>', 0);
            })->whereColumn('quantity', '<', function ($q) {
                $q->select('min_stock_level')->from('items')->whereColumn('items.id', 'inventory_stocks.item_id');
            });
        }

        $stocks = $query->orderBy('department_id')->orderBy('item_id')->get();

        return $this->success($stocks);
    }

    /**
     * Show stock for a specific item in a specific department.
     * GET /api/inventory-stocks/{itemId}/{departmentId}
     */
    public function show(int $itemId, int $departmentId): JsonResponse
    {
        $stock = InventoryStock::with(['item.unit', 'department'])
            ->where('item_id', $itemId)
            ->where('department_id', $departmentId)
            ->first();

        if (! $stock) {
            return $this->error('Stock record not found for this item and department.', 404);
        }

        return $this->success($stock);
    }

    /**
     * Manually adjust stock quantity (e.g. after physical count).
     * POST /api/inventory-stocks/adjust
     */
    public function adjust(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'department_id' => $user->isSystemAdmin() ? 'required|exists:departments,id' : 'nullable|exists:departments,id',
            'quantity'      => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        if (! $user->isSystemAdmin()) {
            $validated['department_id'] = $user->department_id;
        }

        $item = \App\Models\Item::findOrFail($validated['item_id']);
        if ($item->isFixedAsset()) {
            return $this->error('Fixed assets are tracked individually, not via stock levels.', 422);
        }

        $stock = InventoryStock::updateOrCreate(
            ['item_id' => $validated['item_id'], 'department_id' => $validated['department_id']],
            ['quantity' => $validated['quantity']]
        );

        $stock->load(['item.unit', 'department']);

        return $this->success($stock, 'Stock adjusted successfully');
    }

    /**
     * Update the minimum stock threshold for an item.
     * PATCH /api/inventory-stocks/{itemId}/min-stock
     */
    public function setMinStock(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'min_stock_level' => 'required|numeric|min:0',
        ]);

        $item = Item::findOrFail($itemId);
        $item->update(['min_stock_level' => $validated['min_stock_level']]);

        return $this->success(['min_stock_level' => $item->min_stock_level], 'Minimum stock level updated.');
    }
}
