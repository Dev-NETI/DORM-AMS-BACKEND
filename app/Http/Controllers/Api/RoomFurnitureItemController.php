<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureDisposal;
use App\Models\RoomFurnitureItemLog;
use App\Models\RoomFurnitureItemVariant;
use App\Models\RoomFurnitureStock;
use App\Models\Unit;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomFurnitureItemController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    /**
     * List all furniture items that have a room_furniture_stock record,
     * with deployed and available quantities appended.
     */
    public function index(Request $request): JsonResponse
    {
        // Only base (non-variant) stock records — one per item
        $stocks = RoomFurnitureStock::with(['item.category', 'item.unit'])
            ->whereNull('sub_item_id')
            ->orderBy('id')
            ->get();

        $itemIds = $stocks->pluck('item_id');

        // For items that have per-variant stocks, sum those instead of the base record
        $variantTotals = RoomFurnitureStock::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(total_quantity) as total')
            ->pluck('total', 'item_id');

        $deployed = RoomFurniture::whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        // For disposal: sum per-item disposal quantities (base items use whereNull, variant items sum all)
        $disposalBase = RoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $disposalVariant = RoomFurnitureDisposal::whereIn('item_id', $itemIds)
            ->whereNotNull('sub_item_id')
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $result = $stocks->map(function (RoomFurnitureStock $stock) use ($deployed, $variantTotals, $disposalBase, $disposalVariant) {
            $dep          = (int) ($deployed[$stock->item_id] ?? 0);
            $hasVariants  = isset($variantTotals[$stock->item_id]);
            $total        = $hasVariants
                ? (int) $variantTotals[$stock->item_id]
                : $stock->total_quantity;
            $forDisposal  = $hasVariants
                ? (int) ($disposalVariant[$stock->item_id] ?? 0)
                : (int) ($disposalBase[$stock->item_id] ?? 0);

            $netTotal = max(0, $total - $forDisposal);

            return [
                'id'             => $stock->id,
                'item_id'        => $stock->item_id,
                'item'           => $stock->item,
                'total_quantity' => $netTotal,
                'deployed'       => $dep,
                'for_disposal'   => $forDisposal,
                'available'      => max(0, $netTotal - $dep),
                'has_variants'   => $hasVariants,
                'notes'          => $stock->notes,
                'updated_at'     => $stock->updated_at,
            ];
        });

        return $this->success($result);
    }

    /**
     * Create a new furniture item (with optional initial stock quantity).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category_name'  => 'nullable|string|max:255',
            'total_quantity' => 'required|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Prevent duplicate names
        if (Item::where('name', $validated['name'])->exists()) {
            return $this->error("An item named \"{$validated['name']}\" already exists.", 422);
        }

        $result = DB::transaction(function () use ($validated) {
            // Resolve category
            $category = null;
            if (! empty($validated['category_name'])) {
                $category = Category::firstOrCreate(
                    ['name' => trim($validated['category_name'])],
                );
            } else {
                $category = Category::firstOrCreate(['name' => 'Furniture & Fixtures']);
            }

            $unit = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);

            $item = Item::create([
                'name'            => $validated['name'],
                'category_id'     => $category->id,
                'unit_id'         => $unit->id,
                'item_type'       => 'fixed_asset',
                'description'     => "Dormitory room {$validated['name']}",
                'min_stock_level' => 0,
            ]);

            $stock = RoomFurnitureStock::create([
                'item_id'        => $item->id,
                'total_quantity' => $validated['total_quantity'],
                'notes'          => $validated['notes'] ?? null,
            ]);

            return ['item' => $item, 'stock' => $stock];
        });

        RoomFurnitureItemLog::record(
            itemName:   $result['item']->name,
            actionType: 'created',
            itemId:     $result['item']->id,
            userId:     $request->user()?->id,
        );

        return $this->created([
            'id'             => $result['stock']->id,
            'item_id'        => $result['item']->id,
            'item'           => $result['item']->load('category', 'unit'),
            'total_quantity' => $result['stock']->total_quantity,
            'deployed'       => 0,
            'available'      => $result['stock']->total_quantity,
        ], 'Furniture item created');
    }

    /**
     * Update item name and/or stock quantity.
     */
    public function update(Request $request, RoomFurnitureStock $roomFurnitureItem): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'total_quantity' => 'sometimes|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $roomFurnitureItem) {
            if (isset($validated['name'])) {
                $roomFurnitureItem->item->update(['name' => $validated['name']]);
            }

            $roomFurnitureItem->update([
                'total_quantity' => $validated['total_quantity'] ?? $roomFurnitureItem->total_quantity,
                'notes'          => $validated['notes'] ?? $roomFurnitureItem->notes,
            ]);
        });

        // Validate total_quantity ≥ deployed
        $deployed = (int) RoomFurniture::where('item_id', $roomFurnitureItem->item_id)->sum('quantity');
        if ($roomFurnitureItem->fresh()->total_quantity < $deployed) {
            // Revert
            $roomFurnitureItem->update(['total_quantity' => $deployed]);
            return $this->error(
                "Total quantity cannot be less than what is already deployed ({$deployed} units).",
                422
            );
        }

        return $this->success($roomFurnitureItem->load('item.category', 'item.unit'), 'Updated successfully');
    }

    /**
     * Download an Excel template for bulk furniture item import.
     * GET /api/room-furniture-items/template
     */
    public function template(): StreamedResponse
    {
        $headers    = ['Item Name', 'Variant Name', 'Quantity', 'Notes'];
        $sampleRows = [
            ['Example Chair', '',         10, 'Base stock (no variant)'],
            ['Example Chair', 'Brand A',  5,  'Optional notes'],
            ['Example Chair', 'Brand B',  3,  ''],
            ['Example Table', '',         8,  ''],
        ];

        $spreadsheet = $this->createTemplateSpreadsheet(
            $headers,
            $sampleRows,
            "Column guide:\n" .
            "• Item Name    — required; name of the furniture item.\n" .
            "• Variant Name — optional; leave blank for base stock, or enter a brand/model name to create a variant.\n" .
            "• Quantity     — required; number of units to add (integer ≥ 0).\n" .
            "• Notes        — optional; any additional remarks.\n\n" .
            "Rows with the same Item Name are grouped under one item.\n" .
            "If a variant with the same name already exists, its quantity will be incremented.\n" .
            "Leave Quantity blank to skip a row."
        );

        $sheet   = $spreadsheet->getActiveSheet();
        $lastRow = max(count($sampleRows) + 1, 2);

        $sheet->getStyle("A2:C{$lastRow}")->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFDE7'],
            ],
        ]);

        return $this->streamXlsxDownload($spreadsheet, 'ndb_furniture_items_template.xlsx');
    }

    /**
     * Import furniture items from an uploaded Excel file.
     * POST /api/room-furniture-items/import
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $rows = $this->parseUploadedFile($request->file('file'));

        if (empty($rows)) {
            return $this->error('No data rows found in the file.', 422);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum      = $index + 2;
            $itemName    = trim((string) ($row['item_name'] ?? ''));
            $variantName = trim((string) ($row['variant_name'] ?? ''));
            $qtyRaw      = trim((string) ($row['quantity'] ?? ''));
            $notes       = trim((string) ($row['notes'] ?? ''));

            if ($itemName === '' || $qtyRaw === '') {
                $skipped++;
                continue;
            }

            if (! is_numeric($qtyRaw) || (int) $qtyRaw < 0) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => 'Quantity must be a non-negative integer.'];
                continue;
            }

            try {
                DB::transaction(function () use ($itemName, $variantName, $qtyRaw, $notes, $request) {
                    $category = Category::firstOrCreate(['name' => 'Furniture & Fixtures']);
                    $unit     = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);

                    $item = Item::firstOrCreate(
                        ['name' => $itemName],
                        [
                            'category_id'     => $category->id,
                            'unit_id'         => $unit->id,
                            'item_type'       => 'fixed_asset',
                            'description'     => "Dormitory room {$itemName}",
                            'min_stock_level' => 0,
                        ]
                    );

                    if ($variantName !== '') {
                        // Ensure base stock record exists so the item appears in the list
                        RoomFurnitureStock::firstOrCreate(
                            ['item_id' => $item->id, 'sub_item_id' => null],
                            ['total_quantity' => 0]
                        );
                        $variant = RoomFurnitureItemVariant::firstOrCreate(
                            ['item_id' => $item->id, 'name' => $variantName]
                        );
                        $stock = RoomFurnitureStock::firstOrCreate(
                            ['item_id' => $item->id, 'sub_item_id' => $variant->id],
                            ['total_quantity' => 0]
                        );
                    } else {
                        $stock = RoomFurnitureStock::firstOrCreate(
                            ['item_id' => $item->id, 'sub_item_id' => null],
                            ['total_quantity' => 0, 'notes' => $notes ?: null]
                        );
                    }

                    $stock->increment('total_quantity', (int) $qtyRaw);

                    RoomFurnitureItemLog::record(
                        itemName:   $item->name,
                        actionType: 'created',
                        itemId:     $item->id,
                        userId:     $request->user()?->id,
                    );
                });

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'item' => $itemName, 'message' => 'Failed to save: ' . $e->getMessage()];
            }
        }

        return $this->success(compact('imported', 'skipped', 'errors'), 'Import complete.');
    }

    /**
     * Delete a furniture item (only if not deployed in any room).
     */
    public function destroy(Request $request, RoomFurnitureStock $roomFurnitureItem): JsonResponse
    {
        $deployed = RoomFurniture::where('item_id', $roomFurnitureItem->item_id)->exists();
        if ($deployed) {
            return $this->error('Cannot delete an item that is still deployed in rooms.', 422);
        }

        $itemName = $roomFurnitureItem->item->name;
        $itemId   = $roomFurnitureItem->item_id;

        DB::transaction(function () use ($roomFurnitureItem, $itemId) {
            $roomFurnitureItem->delete();
            Item::find($itemId)?->delete();
        });

        RoomFurnitureItemLog::record(
            itemName:   $itemName,
            actionType: 'deleted',
            itemId:     null,   // item no longer exists
            userId:     $request->user()?->id,
        );

        return $this->success(null, 'Item deleted');
    }
}
