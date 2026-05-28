<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\ConsumableReceival;
use App\Models\ConsumableStock;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoffeeWaterInventoryController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    /**
     * Return weekly inventory summary for coffee/bottled water items.
     *
     * Same calculation as ConsumableInventoryController but scoped to
     * module='coffee_water' categories and uses a week range instead of a day.
     *
     * Logic:
     *   ending    = current_stock + issuances_after_week − receivals_after_week
     *   add       = sum(receivals within week)
     *   consumed  = sum(issuances within week)
     *   beginning = ending − add + consumed
     *   total     = beginning + add
     *
     * GET /api/coffee-water-inventory?week=YYYY-Www[&category_id=N]
     */
    public function index(Request $request): JsonResponse
    {
        $weekStr = $request->input('week', now()->format('Y-\WW'));

        // Parse ISO week string "2026-W22" → Monday and Sunday of that week
        $parts   = explode('-W', $weekStr);
        $year    = (int) ($parts[0] ?? now()->year);
        $weekNum = (int) ($parts[1] ?? now()->isoWeek());

        $weekStart = Carbon::now()->setISODate($year, $weekNum)->startOfWeek()->startOfDay();
        $weekEnd   = (clone $weekStart)->endOfWeek()->endOfDay();

        $dateFrom = $weekStart->toDateString();
        $dateTo   = $weekEnd->toDateString();

        $itemQuery = ConsumableItem::with(['unit', 'category'])
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('module', 'coffee_water'))
            ->orderBy('name');

        if ($request->filled('category_id')) {
            $itemQuery->where('consumable_category_id', $request->integer('category_id'));
        }

        $items = $itemQuery->get();

        if ($items->isEmpty()) {
            return $this->success([
                'week'      => $weekStr,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'rows'      => [],
            ]);
        }

        $itemIds = $items->pluck('id')->all();

        // Current live stock from consumable_stocks
        $currentStocks = ConsumableStock::whereIn('consumable_item_id', $itemIds)
            ->pluck('quantity', 'consumable_item_id');

        // Issuances STRICTLY AFTER the week end
        $issuancesAfter = ConsumableIssuance::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', '>', $weekEnd)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Receivals STRICTLY AFTER the week end
        $receivalsAfter = ConsumableReceival::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('received_date', '>', $weekEnd)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Receivals WITHIN the week (add)
        $receivalsIn = ConsumableReceival::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereBetween('received_date', [$dateFrom, $dateTo])
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Issuances WITHIN the week (consumed)
        $issuancesIn = ConsumableIssuance::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Usage: aggregate non-empty usage_history strings from issuances within the week
        $usageRows = ConsumableIssuance::whereIn('consumable_item_id', $itemIds)
            ->whereBetween('issued_date', [$dateFrom, $dateTo])
            ->whereNotNull('usage_history')
            ->where('usage_history', '!=', '')
            ->orderBy('id')
            ->get(['consumable_item_id', 'usage_history']);

        $usageMap = [];
        foreach ($usageRows as $u) {
            $usageMap[$u->consumable_item_id][] = $u->usage_history;
        }
        $usageMap = array_map(
            fn ($arr) => implode('; ', array_unique($arr)),
            $usageMap
        );

        $result = [];
        foreach ($items as $item) {
            $id = $item->id;

            $currentQty = (float) ($currentStocks[$id] ?? 0);
            $issAfter   = (float) ($issuancesAfter[$id] ?? 0);
            $recAfter   = (float) ($receivalsAfter[$id] ?? 0);
            $add        = (float) ($receivalsIn[$id] ?? 0);
            $consumed   = (float) ($issuancesIn[$id] ?? 0);

            $ending    = $currentQty + $issAfter - $recAfter;
            $beginning = $ending - $add + $consumed;
            $total     = $beginning + $add;

            $result[] = [
                'item_id'             => $id,
                'item_name'           => $item->name,
                'unit'                => $item->unit?->abbreviation ?? '',
                'category'            => $item->category?->name ?? '',
                'beginning_inventory' => round($beginning, 2),
                'add'                 => round($add, 2),
                'total'               => round($total, 2),
                'consumed'            => round($consumed, 2),
                'ending_inventory'    => round($ending, 2),
                'usage'               => $usageMap[$id] ?? null,
            ];
        }

        usort($result, fn ($a, $b) =>
            [$a['category'], $a['item_name']] <=> [$b['category'], $b['item_name']]
        );

        return $this->success([
            'week'      => $weekStr,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'rows'      => $result,
        ]);
    }

    /**
     * Download an Excel template pre-filled with all active coffee/water items.
     * GET /api/coffee-water-inventory/template
     */
    public function template(): StreamedResponse
    {
        $items = ConsumableItem::with(['category'])
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('module', 'coffee_water'))
            ->orderBy('consumable_category_id')
            ->orderBy('name')
            ->get();

        $headers = ['Category', 'Item', 'Quantity'];

        $sampleRows = $items->map(fn ($item) => [
            $item->category?->name ?? '',
            $item->name,
            '',
        ])->toArray();

        $spreadsheet = $this->createTemplateSpreadsheet(
            $headers,
            $sampleRows,
            "Column guide:\n" .
            "• Category  — pre-filled; do not change.\n" .
            "• Item      — pre-filled; do not change.\n" .
            "• Quantity  — required; positive number.\n\n" .
            "Leave Quantity blank to skip a row (it will be ignored during import).\n" .
            "Date Received will be set automatically to today's date."
        );

        $sheet   = $spreadsheet->getActiveSheet();
        $lastRow = max(count($sampleRows) + 1, 2);

        $sheet->getStyle("A2:B{$lastRow}")->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F4FF'],
            ],
            'font' => ['color' => ['rgb' => '555555']],
        ]);

        $sheet->getStyle("C2:C{$lastRow}")->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFDE7'],
            ],
        ]);

        return $this->streamXlsxDownload($spreadsheet, 'coffee_water_import_template.xlsx');
    }

    /**
     * Import receivals from an uploaded Excel file (coffee/water items only).
     * POST /api/coffee-water-inventory/import
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $rows = $this->parseUploadedFile($request->file('file'));

        if (empty($rows)) {
            return $this->error('No data rows found in the file.', 422);
        }

        // Load only coffee_water items
        $allItems = ConsumableItem::where('is_active', true)
            ->with(['item', 'category'])
            ->whereHas('category', fn ($q) => $q->where('module', 'coffee_water'))
            ->get();

        $itemMapCompound = $allItems->mapWithKeys(
            fn ($i) => [strtolower(trim($i->category?->name ?? '')) . ':' . strtolower(trim($i->name)) => $i]
        );
        $itemMapSimple = $allItems->keyBy(fn ($i) => strtolower(trim($i->name)));

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum   = $index + 2;
            $itemName = trim((string) ($row['item'] ?? $row['item_name'] ?? ''));
            $catName  = trim((string) ($row['category'] ?? ''));
            $qtyRaw   = trim((string) ($row['quantity'] ?? ''));

            if ($itemName === '' || $qtyRaw === '') {
                $skipped++;
                continue;
            }

            $compoundKey    = strtolower($catName) . ':' . strtolower($itemName);
            $consumableItem = $itemMapCompound[$compoundKey]
                ?? ($catName === '' ? ($itemMapSimple[strtolower($itemName)] ?? null) : null);

            if (! $consumableItem) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => "Item \"{$itemName}\" not found in category \"{$catName}\"."];
                continue;
            }

            if (! is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => 'Quantity must be a positive number.'];
                continue;
            }

            $receivedDate = now()->format('Y-m-d');

            try {
                DB::transaction(function () use ($consumableItem, $qtyRaw, $receivedDate, $request) {
                    ConsumableReceival::create([
                        'consumable_item_id' => $consumableItem->id,
                        'quantity'           => (float) $qtyRaw,
                        'received_date'      => $receivedDate,
                        'notes'              => null,
                        'received_by'        => $request->user()?->id,
                    ]);

                    $stock = ConsumableStock::firstOrCreate(
                        ['consumable_item_id' => $consumableItem->id],
                        ['quantity' => 0]
                    );
                    $stock->increment('quantity', (float) $qtyRaw);

                    if ($consumableItem->item_id) {
                        $deptId = $consumableItem->category?->department_id;
                        if ($deptId) {
                            $mirror = InventoryStock::firstOrCreate(
                                ['item_id' => $consumableItem->item_id, 'department_id' => $deptId],
                                ['quantity' => 0]
                            );
                            $mirror->increment('quantity', (float) $qtyRaw);
                        }
                    }

                    ConsumableAuditLog::log(
                        'created', 'receival', $consumableItem->id,
                        "Imported receival: {$consumableItem->name} × {$qtyRaw} on {$receivedDate}.",
                        null,
                        $request->user()?->id,
                    );
                });

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => 'Failed to save: ' . $e->getMessage()];
            }
        }

        return $this->success(compact('imported', 'skipped', 'errors'), 'Import complete.');
    }
}
