<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableItem;
use App\Models\ConsumableReceival;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableReceivalController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consumable_item_id' => 'required|exists:consumable_items,id',
            'quantity'           => 'required|numeric|min:0.01',
            'received_date'      => 'required|date',
            'po_number'          => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
        ]);

        $validated['received_by'] = $request->user()?->id;

        $receival = ConsumableReceival::create($validated);
        $item     = ConsumableItem::with(['unit', 'category'])->find($validated['consumable_item_id']);

        // Sync to inventory_stocks
        $this->adjustStock($item, (float) $validated['quantity']);

        $unitAbbr = $item?->unit?->abbreviation ?? '';
        ConsumableAuditLog::log(
            'created', 'receival', $receival->id,
            "Received {$receival->quantity} {$unitAbbr} of \"{$item?->name}\" on {$receival->received_date->toDateString()}.",
            [
                'item_id'       => $item?->id,
                'item_name'     => $item?->name,
                'quantity'      => (float) $receival->quantity,
                'received_date' => $receival->received_date->toDateString(),
                'po_number'     => $receival->po_number,
            ],
            $request->user()?->id,
        );

        return $this->created($receival, 'Receival recorded.');
    }

    public function destroy(Request $request, ConsumableReceival $consumableReceival): JsonResponse
    {
        $item     = ConsumableItem::with(['unit', 'category'])->find($consumableReceival->consumable_item_id);
        $unitAbbr = $item?->unit?->abbreviation ?? '';

        ConsumableAuditLog::log(
            'deleted', 'receival', null,
            "Receival of {$consumableReceival->quantity} {$unitAbbr} of \"{$item?->name}\" (dated {$consumableReceival->received_date->toDateString()}) deleted.",
            [
                'item_id'       => $item?->id,
                'item_name'     => $item?->name,
                'quantity'      => (float) $consumableReceival->quantity,
                'received_date' => $consumableReceival->received_date->toDateString(),
            ],
            $request->user()?->id,
        );

        // Reverse stock increment before deleting
        $this->adjustStock($item, -(float) $consumableReceival->quantity);

        $consumableReceival->delete();

        return $this->success(null, 'Receival deleted.');
    }

    /** Increment (+) or decrement (−) inventory_stock for this item's category department. */
    private function adjustStock(?ConsumableItem $item, float $delta): void
    {
        if (! $item || ! $item->item_id) return;

        $deptId = $item->category?->department_id;
        if (! $deptId) return;

        $stock = InventoryStock::firstOrCreate(
            ['item_id' => $item->item_id, 'department_id' => $deptId],
            ['quantity' => 0]
        );

        $stock->quantity = max(0, (float) $stock->quantity + $delta);
        $stock->save();
    }
}
