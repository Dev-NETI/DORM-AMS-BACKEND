<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequisitionController extends Controller
{
    use ApiResponse;

    /**
     * Return categories with their active items, including current stock and
     * min stock level, for the requisition slip builder.
     */
    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:consumable_categories,id',
        ]);

        $categories = ConsumableCategory::with([
            'items' => fn ($q) => $q->where('is_active', true)
                ->with(['unit', 'stock'])
                ->orderBy('name'),
        ])
            ->whereIn('id', $validated['category_ids'])
            ->orderBy('name')
            ->get();

        $result = $categories->map(fn ($cat) => [
            'id'    => $cat->id,
            'name'  => $cat->name,
            'items' => $cat->items->map(function ($item) {
                $currentStock = (float) ($item->stock?->quantity ?? 0);
                $minStock     = (float) ($item->min_stock_level ?? 0);
                return [
                    'id'              => $item->id,
                    'name'            => $item->name,
                    'unit'            => $item->unit?->abbreviation ?? '—',
                    'current_stock'   => $currentStock,
                    'min_stock_level' => $minStock,
                    'suggested_qty'   => max(0, $minStock - $currentStock),
                ];
            }),
        ]);

        return $this->success($result);
    }
}
