<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableCategory;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableOpeningBalance;
use App\Models\ConsumableReceival;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableCategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = ConsumableCategory::with('department')->withCount('items')->orderBy('name')->get();
        return $this->success($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255|unique:consumable_categories,name',
            'description'   => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $category = ConsumableCategory::create($validated);

        ConsumableAuditLog::log(
            'created', 'category', $category->id,
            "Category \"{$category->name}\" created.",
            null,
            $request->user()?->id,
        );

        return $this->created($category->load('department'), 'Category created.');
    }

    public function update(Request $request, ConsumableCategory $consumableCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'          => "required|string|max:255|unique:consumable_categories,name,{$consumableCategory->id}",
            'description'   => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $oldDeptId = $consumableCategory->department_id;
        $oldName   = $consumableCategory->name;
        $consumableCategory->update($validated);

        // If department changed, sync inventory_stocks for all items in this category
        $newDeptId = $consumableCategory->department_id;
        if ($oldDeptId !== $newDeptId) {
            $this->syncInventoryStocks($consumableCategory, $oldDeptId);
        }

        ConsumableAuditLog::log(
            'updated', 'category', $consumableCategory->id,
            "Category \"{$oldName}\" updated.",
            ['old_name' => $oldName, 'new_name' => $consumableCategory->name,
             'old_dept' => $oldDeptId, 'new_dept' => $newDeptId],
            $request->user()?->id,
        );

        return $this->success($consumableCategory->load('department'), 'Category updated.');
    }

    public function destroy(Request $request, ConsumableCategory $consumableCategory): JsonResponse
    {
        $name = $consumableCategory->name;
        $consumableCategory->delete();

        ConsumableAuditLog::log(
            'deleted', 'category', null,
            "Category \"{$name}\" deleted.",
            null,
            $request->user()?->id,
        );

        return $this->success(null, 'Category deleted.');
    }

    /**
     * When a category's department_id changes:
     * - Remove old inventory_stock entries (if dept changed away)
     * - Create new inventory_stock entries with quantity computed from history
     */
    private function syncInventoryStocks(ConsumableCategory $category, ?int $oldDeptId): void
    {
        $items = $category->items()->whereNotNull('item_id')->get();

        // Remove old stock records
        if ($oldDeptId) {
            $itemIds = $items->pluck('item_id')->filter();
            InventoryStock::where('department_id', $oldDeptId)
                ->whereIn('item_id', $itemIds)
                ->delete();
        }

        // Create new stock records from historical totals
        $newDeptId = $category->department_id;
        if (! $newDeptId) return;

        foreach ($items as $ci) {
            if (! $ci->item_id) continue;

            $opening  = (float) ConsumableOpeningBalance::where('consumable_item_id', $ci->id)->sum('quantity');
            $received = (float) ConsumableReceival::where('consumable_item_id', $ci->id)->sum('quantity');
            $issued   = (float) ConsumableIssuance::where('consumable_item_id', $ci->id)->sum('quantity');
            $qty      = max(0, $opening + $received - $issued);

            InventoryStock::updateOrCreate(
                ['item_id' => $ci->item_id, 'department_id' => $newDeptId],
                ['quantity' => $qty]
            );
        }
    }
}
