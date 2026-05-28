<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\ConsumableStock;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableIssuanceController extends Controller
{
    use ApiResponse;

    public function usageHistory(): JsonResponse
    {
        $suggestions = ConsumableIssuance::whereNotNull('usage_history')
            ->where('usage_history', '!=', '')
            ->orderByDesc('issued_date')
            ->pluck('usage_history')
            ->unique()
            ->values();
        return $this->success($suggestions, 'Usage history suggestions.');
    }

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

    /** Increment (+) or decrement (−) both consumable_stocks and the mirror inventory_stocks. */
    private function adjustStock(?ConsumableItem $item, float $delta): void
    {
        if (! $item) return;

        // Dedicated consumable stock table
        $stock = ConsumableStock::firstOrCreate(
            ['consumable_item_id' => $item->id],
            ['quantity' => 0]
        );
        $stock->quantity = max(0, (float) $stock->quantity + $delta);
        $stock->save();

        // Mirror update to inventory_stocks (keeps /inventory-stocks page in sync)
        if ($item->item_id) {
            $deptId = $item->category?->department_id;
            if ($deptId) {
                $mirror = InventoryStock::firstOrCreate(
                    ['item_id' => $item->item_id, 'department_id' => $deptId],
                    ['quantity' => 0]
                );
                $mirror->quantity = max(0, (float) $mirror->quantity + $delta);
                $mirror->save();
            }
        }
    }
}
