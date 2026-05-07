<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Unit;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Item::with(['category', 'unit']);

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%");
            });
        }

        $items = $query->orderBy('name')->get()->map(function ($item) {
            if ($item->isFixedAsset()) {
                $item->total_units     = $item->assets()->count();
                $item->available_units = $item->assets()->where('status', 'available')->count();
                $item->assigned_units  = $item->assets()->where('status', 'assigned')->count();
            } else {
                $item->total_stock = $item->inventoryStocks()->sum('quantity');
            }

            return $item;
        });

        return $this->success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'category_id'     => 'required|exists:categories,id',
            'unit_id'         => 'required|exists:units,id',
            'item_type'       => 'required|in:fixed_asset,consumable',
            'brand'           => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'specifications'  => 'nullable|array',
            'min_stock_level' => 'nullable|numeric|min:0',
            'department_id'   => 'nullable|exists:departments,id',
        ]);

        $validated['department_id']   = $user->isSystemAdmin()
            ? ($validated['department_id'] ?? null)
            : $user->department_id;
        $validated['min_stock_level'] = $validated['min_stock_level'] ?? 0;

        $item = Item::create($validated);
        $item->load(['category', 'unit']);

        return $this->created($item);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load(['category', 'unit']);

        if ($item->isFixedAsset()) {
            $item->load(['assets.department', 'assets.activeAssignment.assignable']);
        } else {
            $item->load(['inventoryStocks.department']);
        }

        return $this->success($item);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'category_id'     => 'sometimes|exists:categories,id',
            'unit_id'         => 'sometimes|exists:units,id',
            'brand'           => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'specifications'  => 'nullable|array',
            'min_stock_level' => 'nullable|numeric|min:0',
        ]);

        // item_type cannot be changed once created to protect data integrity
        if (array_key_exists('min_stock_level', $validated)) {
            $validated['min_stock_level'] = $validated['min_stock_level'] ?? 0;
        }
        $item->update($validated);
        $item->load(['category', 'unit']);

        return $this->success($item, 'Item updated successfully');
    }

    public function destroy(Item $item): JsonResponse
    {
        if ($item->isFixedAsset() && $item->assets()->exists()) {
            return $this->error('Cannot delete item with existing asset records.', 422);
        }

        if ($item->isConsumable() && $item->inventoryStocks()->where('quantity', '>', 0)->exists()) {
            return $this->error('Cannot delete item with existing stock.', 422);
        }

        $item->delete();

        return $this->success(null, 'Item deleted successfully');
    }

    // ── Excel Import / Template ───────────────────────────────────────────────

    public function template(): StreamedResponse
    {
        // Pull random real categories and units so the examples are always valid
        $categories = Category::inRandomOrder()->limit(10)->pluck('name')->all();
        $units      = Unit::inRandomOrder()->limit(10)->get(['name', 'abbreviation']);

        // Helper to pick a random value from an array (with fallback)
        $cat  = fn (string $fallback) => $categories[array_rand($categories)] ?? $fallback;
        $unit = function (string $fallbackName, string $fallbackAbbr) use ($units): string {
            if ($units->isEmpty()) {
                return $fallbackAbbr;
            }
            $u = $units->random();

            return $u->abbreviation ?: $u->name ?: $fallbackAbbr;
        };

        $pool = [
            ['Laptop Computer',    'Main computing device for staff',    $cat('IT Equipment'),    $unit('pieces', 'pcs'),  'fixed_asset', 'Dell',         'Latitude 5520', ''],
            ['Desktop Computer',   'Office desktop workstation',         $cat('IT Equipment'),    $unit('pieces', 'pcs'),  'fixed_asset', 'HP',           'EliteDesk 800', ''],
            ['Office Chair',       'Ergonomic office chair',             $cat('Furniture'),       $unit('pieces', 'pcs'),  'fixed_asset', 'Herman Miller', 'Aeron',        ''],
            ['Filing Cabinet',     '4-drawer metal filing cabinet',      $cat('Furniture'),       $unit('pieces', 'pcs'),  'fixed_asset', 'Steelcase',    'Series 9000',   ''],
            ['Projector',          'LCD projector for presentations',    $cat('IT Equipment'),    $unit('pieces', 'pcs'),  'fixed_asset', 'Epson',        'EB-X41',        ''],
            ['Bond Paper A4',      'Standard A4 80gsm bond paper',       $cat('Office Supplies'), $unit('ream', 'ream'),   'consumable',  '',             '',              '10'],
            ['Ballpen',            'Blue ink ballpen',                   $cat('Office Supplies'), $unit('pieces', 'pcs'),  'consumable',  'Pilot',        '',              '50'],
            ['Correction Tape',    'Correction roller tape',             $cat('Office Supplies'), $unit('pieces', 'pcs'),  'consumable',  'Pentel',       '',              '20'],
            ['Alcohol 70%',        'Isopropyl alcohol 70% solution',     $cat('Janitorial'),      $unit('bottle', 'btl'),  'consumable',  '',             '',              '15'],
            ['Staple Wire',        'Standard staple wire no. 35',        $cat('Office Supplies'), $unit('box', 'box'),     'consumable',  '',             '',              '10'],
            ['Mop Head',           'Cotton string mop replacement head', $cat('Janitorial'),      $unit('pieces', 'pcs'),  'consumable',  '',             '',              '5'],
            ['Ethernet Cable',     'Cat6 UTP LAN cable per meter',       $cat('IT Equipment'),    $unit('meter', 'm'),     'consumable',  '',             '',              '20'],
        ];

        shuffle($pool);

        $spreadsheet = $this->createTemplateSpreadsheet(
            headers: ['Name', 'Description', 'Category', 'Unit', 'Item Type', 'Brand', 'Model', 'Min Stock Level'],
            sampleRows: array_slice($pool, 0, 4),
            note: 'Notes: "Category" must match an existing category name. "Unit" must match an existing unit name or abbreviation. "Item Type" must be exactly "fixed_asset" or "consumable". "Min Stock Level" is only applicable to consumable items.'
        );

        return $this->streamXlsxDownload($spreadsheet, 'items_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $rows = $this->parseUploadedFile($request->file('file'));
        $user = $request->user();
        $deptId = $user->isSystemAdmin() ? null : $user->department_id;

        // Pre-load lookup maps to avoid N+1 queries
        $categoryMap = Category::pluck('id', 'name')->all();

        // Map both name (lowercase) → id and abbreviation (lowercase) → id
        $unitMap = [];
        Unit::all()->each(function ($u) use (&$unitMap) {
            $unitMap[strtolower($u->name)]         = $u->id;
            $unitMap[strtolower($u->abbreviation)] = $u->id;
        });

        // Pre-load existing item names (case-insensitive, dept-scoped)
        $existingItemQuery = Item::query();
        if ($deptId === null) {
            $existingItemQuery->whereNull('department_id');
        } else {
            $existingItemQuery->where('department_id', $deptId);
        }
        $existingNames = $existingItemQuery
            ->pluck('name')
            ->mapWithKeys(fn ($n) => [strtolower(trim($n)) => true])
            ->all();

        $imported = 0;
        $created  = [];
        $skipped  = [];
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // ── Name ──────────────────────────────────────────────────────────
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $errors[] = ['row' => $rowNum, 'name' => null, 'message' => 'Name is required.'];
                continue;
            }
            if (strlen($name) > 255) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Name exceeds 255 characters.'];
                continue;
            }

            // ── Category ──────────────────────────────────────────────────────
            $categoryName = trim((string) ($row['category'] ?? ''));
            $categoryId   = $categoryMap[$categoryName] ?? null;
            if (! $categoryId) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => "Category \"{$categoryName}\" not found. Please create it first."];
                continue;
            }

            // ── Unit ──────────────────────────────────────────────────────────
            $unitKey = strtolower(trim((string) ($row['unit'] ?? '')));
            $unitId  = $unitMap[$unitKey] ?? null;
            if (! $unitId) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => "Unit \"{$row['unit']}\" not found. Use an existing unit name or abbreviation."];
                continue;
            }

            // ── Item Type ─────────────────────────────────────────────────────
            $itemType = strtolower(trim((string) ($row['item_type'] ?? '')));
            if (! in_array($itemType, ['fixed_asset', 'consumable'], true)) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => "Item Type must be \"fixed_asset\" or \"consumable\" (got: \"{$row['item_type']}\")."];
                continue;
            }

            // ── Min Stock Level ───────────────────────────────────────────────
            $minStock    = $row['min_stock_level'] ?? null;
            $minStockVal = 0;
            if ($minStock !== null && $minStock !== '') {
                if (! is_numeric($minStock) || (float) $minStock < 0) {
                    $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Min Stock Level must be a non-negative number.'];
                    continue;
                }
                $minStockVal = (float) $minStock;
            }

            // ── Duplicate check ────────────────────────────────────────────────
            $nameKey = strtolower($name);
            if (array_key_exists($nameKey, $existingNames)) {
                $skipped[] = [
                    'row'    => $rowNum,
                    'name'   => $name,
                    'reason' => 'An item with this name already exists.',
                ];
                continue;
            }

            try {
                Item::create([
                    'name'            => $name,
                    'description'     => $row['description'] ?: null,
                    'category_id'     => $categoryId,
                    'unit_id'         => $unitId,
                    'item_type'       => $itemType,
                    'brand'           => $row['brand'] ?: null,
                    'model'           => $row['model'] ?: null,
                    'specifications'  => null,
                    'min_stock_level' => $minStockVal,
                    'department_id'   => $deptId,
                ]);
                $created[]               = ['row' => $rowNum, 'name' => $name];
                $existingNames[$nameKey] = true; // prevent within-file duplicates
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Failed to save: '.$e->getMessage()];
            }
        }

        return $this->success(
            [
                'imported' => $imported,
                'created'  => $created,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ],
            "Import complete: {$imported} record(s) imported."
        );
    }
}
