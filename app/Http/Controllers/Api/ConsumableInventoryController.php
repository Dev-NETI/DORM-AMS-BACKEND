<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\ConsumableReceival;
use App\Models\ConsumableRemark;
use App\Models\ConsumableStock;
use App\Models\InventoryStock;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsumableInventoryController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    /**
     * Return daily inventory summary per item for a given date.
     *
     * Logic (mirrors GalleyInventoryController):
     *   ending    = current_stock + issuances_after_date − receivals_after_date
     *   add       = sum(receivals on date)
     *   consumed  = sum(issuances on date)
     *   beginning = ending − add + consumed   (i.e. ending of previous day)
     *   total     = beginning + add
     *
     * GET /api/consumable-inventory?date=YYYY-MM-DD[&category_id=N]
     */
    public function index(Request $request): JsonResponse
    {
        $date   = $request->input('date', now()->toDateString());
        $carbon = Carbon::parse($date)->startOfDay();

        $itemQuery = ConsumableItem::with(['unit', 'category'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('category_id')) {
            $itemQuery->where('consumable_category_id', $request->integer('category_id'));
        }

        $items = $itemQuery->get();

        if ($items->isEmpty()) {
            return $this->success([]);
        }

        $itemIds = $items->pluck('id')->all();

        // Current live stock from consumable_stocks (keyed by consumable_item_id)
        $currentStocks = ConsumableStock::whereIn('consumable_item_id', $itemIds)
            ->pluck('quantity', 'consumable_item_id');

        // Issuances STRICTLY AFTER the date
        $issuancesAfter = ConsumableIssuance::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', '>', $carbon)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Receivals STRICTLY AFTER the date
        $receivalsAfter = ConsumableReceival::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('received_date', '>', $carbon)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Receivals ON the date (add)
        $receivalsOn = ConsumableReceival::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('received_date', $carbon)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Issuances ON the date (consumed)
        $issuancesOn = ConsumableIssuance::select('consumable_item_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', $carbon)
            ->groupBy('consumable_item_id')
            ->pluck('total', 'consumable_item_id');

        // Usage: aggregate non-empty usage_history strings from all issuances on the date
        $usageRows = ConsumableIssuance::whereIn('consumable_item_id', $itemIds)
            ->whereDate('issued_date', $carbon)
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

        // Build result
        $result = [];
        foreach ($items as $item) {
            $id = $item->id;

            $currentQty = (float) ($currentStocks[$id] ?? 0);
            $issAfter   = (float) ($issuancesAfter[$id] ?? 0);
            $recAfter   = (float) ($receivalsAfter[$id] ?? 0);
            $add        = (float) ($receivalsOn[$id] ?? 0);
            $consumed   = (float) ($issuancesOn[$id] ?? 0);

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

        // Sort by category then item name (matches galley)
        usort($result, fn ($a, $b) =>
            [$a['category'], $a['item_name']] <=> [$b['category'], $b['item_name']]
        );

        return $this->success($result);
    }

    /**
     * Save or update a remark for a specific item on a given date.
     * POST /api/consumable-inventory/remark
     */
    public function saveRemark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:consumable_items,id',
            'date'    => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $remark = ConsumableRemark::updateOrCreate(
            ['consumable_item_id' => $validated['item_id'], 'date' => $validated['date']],
            ['remarks' => $validated['remarks']],
        );

        return $this->success($remark, 'Remark saved.');
    }

    /**
     * Download an Excel template pre-filled with all active items (categorised).
     * The user only needs to fill in Quantity, Date Received, and optionally Notes.
     *
     * GET /api/consumable-inventory/template
     */
    public function template(): StreamedResponse
    {
        $items = ConsumableItem::with(['category'])
            ->where('is_active', true)
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

        // Lock Category and Item columns (A–B) visually with light fill
        $sheet   = $spreadsheet->getActiveSheet();
        $lastRow = max(count($sampleRows) + 1, 2);

        $sheet->getStyle("A2:B{$lastRow}")->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F4FF'],
            ],
            'font' => ['color' => ['rgb' => '555555']],
        ]);

        // Highlight the Quantity column the user must fill
        $sheet->getStyle("C2:C{$lastRow}")->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFDE7'],
            ],
        ]);

        return $this->streamXlsxDownload($spreadsheet, 'cleaning_supplies_import_template.xlsx');
    }

    /**
     * Import receivals from an uploaded Excel file.
     * POST /api/consumable-inventory/import
     *
     * Expects columns: Category, Item, Quantity
     * Rows with blank Quantity are skipped.
     * Date Received defaults to today automatically.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $rows = $this->parseUploadedFile($request->file('file'));

        if (empty($rows)) {
            return $this->error('No data rows found in the file.', 422);
        }

        // Pre-load all active items keyed by "category:item" (compound) to prevent
        // name collisions when the same item name exists in multiple categories (e.g. NDB vs NGH).
        // A plain item-name key is also kept as fallback for rows where Category is blank.
        $allItems  = ConsumableItem::where('is_active', true)->with(['item', 'category'])->get();
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

            // Skip rows with no item name or blank quantity (user left them empty)
            if ($itemName === '' || $qtyRaw === '') {
                $skipped++;
                continue;
            }

            // Validate item exists — prefer compound "category:item" lookup to avoid
            // collisions when the same name exists in both NDB and NGH categories.
            $compoundKey = strtolower($catName) . ':' . strtolower($itemName);
            $consumableItem = $itemMapCompound[$compoundKey]
                ?? ($catName === '' ? ($itemMapSimple[strtolower($itemName)] ?? null) : null);

            if (! $consumableItem) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => "Item \"{$itemName}\" not found in category \"{$catName}\"."];
                continue;
            }

            // Validate quantity
            if (! is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => 'Quantity must be a positive number.'];
                continue;
            }

            $receivedDate = now()->format('Y-m-d');

            try {
                DB::transaction(function () use ($consumableItem, $qtyRaw, $receivedDate, $request) {
                    // Create receival record
                    ConsumableReceival::create([
                        'consumable_item_id' => $consumableItem->id,
                        'quantity'           => (float) $qtyRaw,
                        'received_date'      => $receivedDate,
                        'notes'              => null,
                        'received_by'        => $request->user()?->id,
                    ]);

                    // Update consumable stock (dedicated table)
                    $stock = ConsumableStock::firstOrCreate(
                        ['consumable_item_id' => $consumableItem->id],
                        ['quantity' => 0]
                    );
                    $stock->increment('quantity', (float) $qtyRaw);

                    // Mirror update to inventory_stocks
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

                    // Audit log
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
