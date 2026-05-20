<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableCategory;
use App\Models\ConsumableItem;
use App\Models\ConsumableStock;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ConsumableItem::with(['category', 'unit'])->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('consumable_category_id', $request->integer('category_id'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consumable_category_id' => 'required|exists:consumable_categories,id',
            'name'                   => 'required|string|max:255',
            'unit_id'                => 'required|exists:units,id',
        ]);

        $validated['created_by'] = $request->user()?->id;

        // Create a mirror entry in the items table (for inventory_stocks)
        $cleaningCatId = $this->getCleaningSuppliesCategoryId();
        $mirrorItem = Item::create([
            'name'        => $validated['name'],
            'category_id' => $cleaningCatId,
            'unit_id'     => $validated['unit_id'],
            'item_type'   => 'consumable',
        ]);
        $validated['item_id'] = $mirrorItem->id;

        $consumableItem = ConsumableItem::create($validated);
        $consumableItem->load(['category', 'unit']);

        // Create dedicated consumable stock record
        ConsumableStock::create(['consumable_item_id' => $consumableItem->id, 'quantity' => 0]);

        // Create inventory_stock if category has a department (mirror pattern)
        $dept = $consumableItem->category?->department_id;
        if ($dept && $mirrorItem->id) {
            InventoryStock::firstOrCreate(
                ['item_id' => $mirrorItem->id, 'department_id' => $dept],
                ['quantity' => 0]
            );
        }

        ConsumableAuditLog::log(
            'created', 'item', $consumableItem->id,
            "Item \"{$consumableItem->name}\" added to category \"{$consumableItem->category->name}\".",
            ['unit_id' => $consumableItem->unit_id, 'category_id' => $consumableItem->consumable_category_id],
            $request->user()?->id,
        );

        return $this->created($consumableItem, 'Item created.');
    }

    public function update(Request $request, ConsumableItem $consumableItem): JsonResponse
    {
        $validated = $request->validate([
            'consumable_category_id' => 'sometimes|exists:consumable_categories,id',
            'name'                   => 'sometimes|string|max:255',
            'unit_id'                => 'sometimes|exists:units,id',
            'is_active'              => 'sometimes|boolean',
        ]);

        $old = $consumableItem->only(['name', 'unit_id', 'is_active']);
        $consumableItem->update($validated);

        // Sync name/unit to the mirror items entry
        if ($consumableItem->item_id && (isset($validated['name']) || isset($validated['unit_id']))) {
            Item::where('id', $consumableItem->item_id)->update([
                'name'    => $consumableItem->name,
                'unit_id' => $consumableItem->unit_id,
            ]);
        }

        $consumableItem->load(['category', 'unit']);

        ConsumableAuditLog::log(
            'updated', 'item', $consumableItem->id,
            "Item \"{$consumableItem->name}\" updated.",
            ['old' => $old, 'new' => $consumableItem->only(['name', 'unit_id', 'is_active'])],
            $request->user()?->id,
        );

        return $this->success($consumableItem, 'Item updated.');
    }

    public function destroy(Request $request, ConsumableItem $consumableItem): JsonResponse
    {
        $name = $consumableItem->name;
        $itemId = $consumableItem->item_id;

        $consumableItem->delete();

        // Remove inventory_stock for this item (the mirror item will cascade or be cleaned up)
        if ($itemId) {
            InventoryStock::where('item_id', $itemId)->delete();
            Item::where('id', $itemId)->delete();
        }

        ConsumableAuditLog::log(
            'deleted', 'item', null,
            "Item \"{$name}\" deleted.",
            null,
            $request->user()?->id,
        );

        return $this->success(null, 'Item deleted.');
    }

    /** Find or create the "Cleaning Supplies" category in the general categories table. */
    private function getCleaningSuppliesCategoryId(): int
    {
        $cat = Category::firstOrCreate(
            ['name' => 'Cleaning Supplies'],
            ['name' => 'Cleaning Supplies']
        );
        return $cat->id;
    }
}
