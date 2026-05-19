<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableCategory;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\ConsumableOpeningBalance;
use App\Models\ConsumableReceival;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableMonthlyController extends Controller
{
    use ApiResponse;

    /**
     * Return the monthly inventory matrix for a category.
     * GET /api/consumable-monthly/{categoryId}/{year}/{month}
     */
    public function show(int $categoryId, int $year, int $month): JsonResponse
    {
        $category = ConsumableCategory::findOrFail($categoryId);

        $items = ConsumableItem::where('consumable_category_id', $categoryId)
            ->where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->get();

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();
        $startOfYear  = Carbon::create($year, 1, 1)->startOfDay();

        $itemIds = $items->pluck('id');

        // Opening balances for the year
        $openingBalances = ConsumableOpeningBalance::whereIn('consumable_item_id', $itemIds)
            ->where('year', $year)
            ->pluck('quantity', 'consumable_item_id');

        // Receivals before this month (within the same year)
        $receivalsBeforeMonth = ConsumableReceival::whereIn('consumable_item_id', $itemIds)
            ->whereDate('received_date', '>=', $startOfYear)
            ->whereDate('received_date', '<', $startOfMonth)
            ->groupBy('consumable_item_id')
            ->selectRaw('consumable_item_id, SUM(quantity) as total')
            ->pluck('total', 'consumable_item_id');

        // Issuances before this month (within the same year)
        $issuancesBeforeMonth = ConsumableIssuance::whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', '>=', $startOfYear)
            ->whereDate('issued_date', '<', $startOfMonth)
            ->groupBy('consumable_item_id')
            ->selectRaw('consumable_item_id, SUM(quantity) as total')
            ->pluck('total', 'consumable_item_id');

        // Receivals this month (with detail)
        $monthReceivalsByItem = ConsumableReceival::whereIn('consumable_item_id', $itemIds)
            ->whereDate('received_date', '>=', $startOfMonth)
            ->whereDate('received_date', '<=', $endOfMonth)
            ->orderBy('received_date')
            ->get()
            ->groupBy('consumable_item_id');

        // Issuances this month (with detail)
        $monthIssuancesByItem = ConsumableIssuance::whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', '>=', $startOfMonth)
            ->whereDate('issued_date', '<=', $endOfMonth)
            ->orderBy('issued_date')
            ->get()
            ->groupBy('consumable_item_id');

        $rows = $items->map(function (ConsumableItem $item) use (
            $openingBalances, $receivalsBeforeMonth, $issuancesBeforeMonth,
            $monthReceivalsByItem, $monthIssuancesByItem,
        ) {
            $opening   = (float) ($openingBalances[$item->id] ?? 0);
            $recBefore = (float) ($receivalsBeforeMonth[$item->id] ?? 0);
            $issBefore = (float) ($issuancesBeforeMonth[$item->id] ?? 0);
            $beginning = $opening + $recBefore - $issBefore;

            $itemReceivalsThisMonth = $monthReceivalsByItem[$item->id] ?? collect();
            $itemIssuancesThisMonth = $monthIssuancesByItem[$item->id] ?? collect();

            $add      = (float) $itemReceivalsThisMonth->sum('quantity');
            $consumed = (float) $itemIssuancesThisMonth->sum('quantity');
            $total    = $beginning + $add;
            $ending   = $total - $consumed;

            $usageHistory = $itemIssuancesThisMonth
                ->filter(fn ($i) => filled($i->usage_history))
                ->pluck('usage_history')
                ->unique()
                ->join('; ');

            return [
                'item_id'       => $item->id,
                'item_name'     => $item->name,
                'unit'          => $item->unit?->abbreviation ?? '',
                'beginning'     => round($beginning, 2),
                'add'           => round($add, 2),
                'total'         => round($total, 2),
                'consumed'      => round($consumed, 2),
                'ending'        => round($ending, 2),
                'usage_history' => $usageHistory,
                'receivals'     => $itemReceivalsThisMonth->map(fn ($r) => [
                    'id'            => $r->id,
                    'quantity'      => (float) $r->quantity,
                    'received_date' => $r->received_date?->toDateString(),
                    'po_number'     => $r->po_number,
                    'notes'         => $r->notes,
                ])->values(),
                'issuances'     => $itemIssuancesThisMonth->map(fn ($i) => [
                    'id'            => $i->id,
                    'quantity'      => (float) $i->quantity,
                    'issued_date'   => $i->issued_date?->toDateString(),
                    'usage_history' => $i->usage_history,
                ])->values(),
            ];
        });

        return $this->success([
            'category' => ['id' => $category->id, 'name' => $category->name],
            'year'     => $year,
            'month'    => $month,
            'rows'     => $rows,
        ]);
    }

    /**
     * Set / update the opening balance for an item + year.
     * PUT /api/consumable-items/{item}/opening
     */
    public function setOpening(Request $request, ConsumableItem $consumableItem): JsonResponse
    {
        $validated = $request->validate([
            'year'     => 'required|integer|min:2020|max:2100',
            'quantity' => 'required|numeric|min:0',
        ]);

        $old = ConsumableOpeningBalance::where('consumable_item_id', $consumableItem->id)
            ->where('year', $validated['year'])
            ->value('quantity') ?? 0;

        $balance = ConsumableOpeningBalance::updateOrCreate(
            ['consumable_item_id' => $consumableItem->id, 'year' => $validated['year']],
            ['quantity' => $validated['quantity'], 'set_by' => $request->user()?->id],
        );

        ConsumableAuditLog::log(
            'updated',
            'opening_balance',
            $balance->id,
            "Opening balance for \"{$consumableItem->name}\" (year {$validated['year']}) set to {$validated['quantity']} (was {$old}).",
            ['item_id' => $consumableItem->id, 'year' => $validated['year'], 'old' => $old, 'new' => $validated['quantity']],
            $request->user()?->id,
        );

        return $this->success($balance, 'Opening balance updated.');
    }
}
