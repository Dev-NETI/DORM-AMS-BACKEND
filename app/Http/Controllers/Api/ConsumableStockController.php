<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableItem;
use App\Models\ConsumableStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableStockController extends Controller
{
    use ApiResponse;

    /**
     * Return all consumable stock levels.
     * GET /api/consumable-stocks
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConsumableStock::with(['item.unit', 'item.category'])
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->orderBy('consumable_item_id');

        if ($request->filled('category_id')) {
            $query->whereHas('item', fn ($q) => $q->where('consumable_category_id', $request->integer('category_id')));
        }

        $stocks = $query->get()->map(function (ConsumableStock $stock) {
            $item      = $stock->item;
            $minLevel  = (float) ($item?->min_stock_level ?? 0);
            $qty       = (float) $stock->quantity;

            return [
                'id'               => $stock->id,
                'item_id'          => $stock->consumable_item_id,
                'quantity'         => $qty,
                'is_below_minimum' => $minLevel > 0 && $qty < $minLevel,
                'item'             => $item ? [
                    'id'              => $item->id,
                    'name'            => $item->name,
                    'unit'            => $item->unit ? ['abbreviation' => $item->unit->abbreviation] : null,
                    'min_stock_level' => $minLevel,
                    'category_id'     => $item->consumable_category_id,
                    'category_name'   => $item->category?->name,
                ] : null,
            ];
        });

        return $this->success($stocks);
    }

    /**
     * Update minimum stock threshold for a consumable item.
     * PATCH /api/consumable-stocks/{itemId}/min-stock
     */
    public function setMinStock(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'min_stock_level' => 'required|numeric|min:0',
        ]);

        $item = ConsumableItem::findOrFail($itemId);
        $item->update(['min_stock_level' => $validated['min_stock_level']]);

        return $this->success(
            ['min_stock_level' => $item->min_stock_level],
            'Minimum stock level updated.'
        );
    }
}
