<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableIssuanceController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consumable_item_id' => 'required|exists:consumable_items,id',
            'quantity'           => 'required|numeric|min:0.01',
            'issued_date'        => 'required|date',
            'usage_history'      => 'nullable|string',
        ]);

        $validated['issued_by'] = $request->user()?->id;

        $item = ConsumableItem::with(['unit', 'category'])->find($validated['consumable_item_id']);

        $issuance = ConsumableIssuance::create($validated);

        // Sync to inventory_stocks (decrement)
        $this->adjustStock($item, -(float) $validated['quantity']);

        $unitAbbr = $item?->unit?->abbreviation ?? '';
        ConsumableAuditLog::log(
            'created', 'issuance', $issuance->id,
            "Issued {$issuance->quantity} {$unitAbbr} of \"{$item?->name}\" on {$issuance->issued_date->toDateString()}.",
            [
                'item_id'       => $item?->id,
                'item_name'     => $item?->name,
                'quantity'      => (float) $issuance->quantity,
                'issued_date'   => $issuance->issued_date->toDateString(),
                'usage_history' => $issuance->usage_history,
            ],
            $request->user()?->id,
        );

        return $this->created($issuance, 'Issuance recorded.');
    }

    public function destroy(Request $request, ConsumableIssuance $consumableIssuance): JsonResponse
    {
        $item     = ConsumableItem::with(['unit', 'category'])->find($consumableIssuance->consumable_item_id);
        $unitAbbr = $item?->unit?->abbreviation ?? '';

        ConsumableAuditLog::log(
            'deleted', 'issuance', null,
            "Issuance of {$consumableIssuance->quantity} {$unitAbbr} of \"{$item?->name}\" (dated {$consumableIssuance->issued_date->toDateString()}) deleted.",
            [
                'item_id'     => $item?->id,
                'item_name'   => $item?->name,
                'quantity'    => (float) $consumableIssuance->quantity,
                'issued_date' => $consumableIssuance->issued_date->toDateString(),
            ],
            $request->user()?->id,
        );

        // Reverse stock decrement before deleting
        $this->adjustStock($item, (float) $consumableIssuance->quantity);

        $consumableIssuance->delete();

        return $this->success(null, 'Issuance deleted.');
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
