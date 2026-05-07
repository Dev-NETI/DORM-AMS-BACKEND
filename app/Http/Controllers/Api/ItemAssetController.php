<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use App\Models\AssetDocument;
use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\Unit;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemAssetController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = ItemAsset::with(['item.category', 'department', 'activeAssignment.assignable', 'documents']);

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('item_code', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('mac_address', 'like', "%{$request->search}%");
            });
        }

        return $this->success($query->orderBy('item_code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'item_id'         => 'required|exists:items,id',
            'item_code'       => 'required|string|max:100|unique:item_assets,item_code',
            'serial_number'   => 'nullable|string|max:255',
            'mac_address'     => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/'],
            'purchase_date'   => 'nullable|date',
            'purchase_price'  => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date|after_or_equal:purchase_date',
            'department_id'   => $user->isSystemAdmin() ? 'required|exists:departments,id' : 'nullable|exists:departments,id',
            'status'               => 'nullable|in:available,assigned',
            'notes'                => 'nullable|string',
            'delivery_receipt_no'  => 'nullable|string|max:255',
        ]);

        // Employee users are scoped to their own department
        if (! $user->isSystemAdmin()) {
            $validated['department_id'] = $user->department_id;
        }

        // Ensure item is a fixed_asset type
        $item = \App\Models\Item::findOrFail($validated['item_id']);
        if ($item->isConsumable()) {
            return $this->error('Cannot register an asset for a consumable item. Use stock receivals instead.', 422);
        }

        $asset = ItemAsset::create($validated);
        $asset->load(['item.category', 'department']);

        return $this->created($asset);
    }

    /**
     * Public lookup by item_code — no auth required.
     * GET /api/item-assets/code/{code}
     */
    public function showByCode(string $code): JsonResponse
    {
        $asset = ItemAsset::with([
            'item.category',
            'department',
            'activeAssignment.assignable',
        ])->where('item_code', $code)->first();

        if (! $asset) {
            return $this->error('Asset not found.', 404);
        }

        return $this->success($asset);
    }

    public function show(ItemAsset $itemAsset): JsonResponse
    {
        $itemAsset->load([
            'item.category',
            'item.unit',
            'department',
            'activeAssignment.assignable',
            'documents',
            'assignments' => fn($q) => $q->with(['assignable', 'assignedBy', 'returnedBy'])->latest('assigned_at'),
        ]);

        return $this->success($itemAsset);
    }

    public function update(Request $request, ItemAsset $itemAsset): JsonResponse
    {
        $validated = $request->validate([
            'serial_number'   => 'nullable|string|max:255',
            'mac_address'     => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/'],
            'purchase_date'   => 'nullable|date',
            'purchase_price'  => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'department_id'        => 'sometimes|exists:departments,id',
            'status'               => 'nullable|in:available,assigned',
            'notes'                => 'nullable|string',
            'delivery_receipt_no'  => 'nullable|string|max:255',
        ]);

        $itemAsset->update($validated);
        $itemAsset->load(['item.category', 'department', 'activeAssignment.assignable']);

        return $this->success($itemAsset, 'Asset updated successfully');
    }

    /**
     * Upload or replace the delivery receipt file.
     * POST /api/item-assets/{itemAsset}/upload-dr
     */
    public function uploadDeliveryReceipt(Request $request, ItemAsset $itemAsset): JsonResponse
    {
        $request->validate([
            'delivery_receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Delete old file if one exists
        if ($itemAsset->delivery_receipt_file) {
            Storage::disk('public')->delete($itemAsset->delivery_receipt_file);
        }

        $path = $request->file('delivery_receipt_file')->store('delivery_receipts', 'public');
        $itemAsset->update(['delivery_receipt_file' => $path]);

        return $this->success($itemAsset, 'Delivery receipt uploaded.');
    }

    /**
     * Upload a related purchase document.
     * POST /api/item-assets/{itemAsset}/documents
     */
    public function uploadDocument(Request $request, ItemAsset $itemAsset): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
        ]);

        $path = $request->file('file')->store('asset_documents', 'public');
        $doc  = $itemAsset->documents()->create([
            'file_path'     => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return $this->success($doc, 'Document uploaded.');
    }

    /**
     * Delete a related purchase document.
     * DELETE /api/item-assets/{itemAsset}/documents/{document}
     */
    public function deleteDocument(ItemAsset $itemAsset, AssetDocument $document): JsonResponse
    {
        if ($document->item_asset_id !== $itemAsset->id) {
            return $this->error('Document not found for this asset.', 404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return $this->success(null, 'Document deleted.');
    }

    public function destroy(ItemAsset $itemAsset): JsonResponse
    {
        if ($itemAsset->activeAssignment()->exists()) {
            return $this->error('Cannot delete an asset that is currently assigned.', 422);
        }

        $itemAsset->delete();

        return $this->success(null, 'Asset deleted successfully');
    }

    /**
     * Assign this asset to an employee or department.
     * POST /api/item-assets/{itemAsset}/assign
     */
    public function assign(Request $request, ItemAsset $itemAsset): JsonResponse
    {
        if ($itemAsset->status === 'assigned') {
            return $this->error('Asset is already assigned. Return it first.', 422);
        }

        $validated = $request->validate([
            'assignable_type'      => 'required|in:employee,department,others',
            'assignable_id'        => 'required_unless:assignable_type,others|nullable|integer',
            'assignable_label'     => 'required_if:assignable_type,others|nullable|string|max:500',
            'assigned_at'          => 'nullable|date',
            'expected_return_date' => 'nullable|date',
            'purpose'              => 'nullable|string|max:500',
            'notes'                => 'nullable|string',
        ]);

        // Resolve the polymorphic model (skip for 'others')
        $modelMap = [
            'employee'   => \App\Models\Employee::class,
            'department' => \App\Models\Department::class,
        ];

        $modelClass = null;
        if ($validated['assignable_type'] !== 'others') {
            $modelClass = $modelMap[$validated['assignable_type']];
            if (! $modelClass::find($validated['assignable_id'])) {
                return $this->error(ucfirst($validated['assignable_type']) . ' not found.', 422);
            }
        }

        DB::transaction(function () use ($itemAsset, $validated, $modelClass, $request) {
            AssetAssignment::create([
                'asset_id'             => $itemAsset->id,
                'assignable_type'      => $modelClass,
                'assignable_id'        => $validated['assignable_id'] ?? null,
                'assignable_label'     => $validated['assignable_label'] ?? null,
                'assigned_by'          => $request->user()->id,
                'assigned_at'          => $validated['assigned_at'] ?? now(),
                'expected_return_date' => $validated['expected_return_date'] ?? null,
                'purpose'              => $validated['purpose'] ?? null,
                'notes'                => $validated['notes'] ?? null,
                'status'               => 'active',
            ]);

            $itemAsset->update(['status' => 'assigned']);
        });

        $itemAsset->load(['item', 'department', 'activeAssignment.assignable']);

        return $this->success($itemAsset, 'Asset assigned successfully');
    }

    // ── Excel Import / Template ───────────────────────────────────────────────

    public function template(Request $request): StreamedResponse
    {
        $user = $request->user();

        // Scope fixed-asset items by department for non-admins
        $itemQuery = Item::where('item_type', 'fixed_asset')->orderBy('name');
        if (! $user->isSystemAdmin()) {
            $itemQuery->where('department_id', $user->department_id);
        }
        $fixedItems = $itemQuery->get();
        $itemNames  = $fixedItems->pluck('name')->all();

        // Reference lists for dropdowns
        $deptNames = Department::orderBy('name')->pluck('name')->all();
        $catNames  = Category::orderBy('name')->pluck('name')->all();
        $unitNames = Unit::orderBy('name')->get()
            ->flatMap(fn ($u) => array_filter([$u->name, $u->abbreviation], fn ($v) => $v !== null && $v !== ''))
            ->unique()->values()->all();

        // ── Sample rows ───────────────────────────────────────────────────────
        $suffixes    = ['001', '002', '003', '004'];
        $sampleItems = $fixedItems->shuffle()->take(min(4, $fixedItems->count()));
        $sampleRows  = [];
        foreach ($sampleItems as $idx => $item) {
            $dept   = $deptNames[$idx % max(count($deptNames), 1)] ?? '';
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $item->name), 0, 3));
            $code   = strtoupper(substr(str_replace(' ', '', $dept), 0, 3)).'-'.$prefix.'-'.$suffixes[$idx];
            $sampleRows[] = [
                $item->name,                                           // Item Name
                $code,                                                 // Item Code
                $dept,                                                 // Department
                '',                                                    // Category (blank — item exists)
                '',                                                    // Unit (blank — item exists)
                'SN-'.rand(100000, 999999),                           // Serial Number
                '',                                                    // MAC Address
                '2024-01-15',                                          // Purchase Date
                rand(5000, 80000),                                     // Purchase Price
                '2027-01-15',                                          // Warranty Expiry
                'DR-2024-'.str_pad($idx + 1, 3, '0', STR_PAD_LEFT),  // Delivery Receipt No
                '',                                                    // Notes
            ];
        }

        $spreadsheet = $this->createTemplateSpreadsheet(
            headers: ['Item Name', 'Item Code', 'Department', 'Category', 'Unit', 'Serial Number', 'MAC Address', 'Purchase Date', 'Purchase Price', 'Warranty Expiry', 'Delivery Receipt No', 'Notes'],
            sampleRows: $sampleRows,
            note: implode(' ', [
                '"Item Name": Select an existing item from the dropdown or type a new name.',
                'For NEW items, you must also fill the "Category" and "Unit" columns — the item will be created automatically as a fixed asset.',
                '"Category" and "Unit" are ignored when the item already exists.',
                '"Item Code" must be globally unique (e.g. NOD-LAP-001).',
                '"Department" is required for admin users and ignored for regular users (auto-set to their department).',
                'Dates must be in YYYY-MM-DD format.',
                '"MAC Address" format: XX:XX:XX:XX:XX:XX (colons or dashes).',
                '"Purchase Price" must be a plain number (no currency symbol).',
                'All imported rows are created with status = available.',
            ])
        );

        $dataSheet = $spreadsheet->getActiveSheet();

        // ── Hidden reference sheet ────────────────────────────────────────────
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('_ref');
        $refSheet->setSheetState('veryHidden');

        // Col A: item names | B: dept names | C: category names | D: unit names
        foreach ($itemNames as $i => $name) {
            $refSheet->setCellValue('A'.($i + 1), $name);
        }
        foreach ($deptNames as $i => $name) {
            $refSheet->setCellValue('B'.($i + 1), $name);
        }
        foreach ($catNames as $i => $name) {
            $refSheet->setCellValue('C'.($i + 1), $name);
        }
        foreach ($unitNames as $i => $name) {
            $refSheet->setCellValue('D'.($i + 1), $name);
        }

        // ── Data-validation dropdowns on the Data sheet ───────────────────────
        $addDropdown = function (
            string $startCell,
            string $sqref,
            string $formula,
            string $promptTitle,
            string $prompt
        ) use ($dataSheet): void {
            $v = $dataSheet->getCell($startCell)->getDataValidation();
            $v->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $v->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $v->setAllowBlank(true);
            $v->setShowDropDown(true);
            $v->setShowInputMessage(true);
            $v->setShowErrorMessage(false);
            $v->setPromptTitle($promptTitle);
            $v->setPrompt($prompt);
            $v->setFormula1($formula);
            $v->setSqref($sqref);
        };

        if (! empty($itemNames)) {
            $addDropdown('A2', 'A2:A1000',
                "'_ref'!\$A\$1:\$A\$".count($itemNames),
                'Item Name',
                'Choose an existing item from the list, or type a new name. If new, also fill the Category and Unit columns.');
        }
        if (! empty($deptNames)) {
            $addDropdown('C2', 'C2:C1000',
                "'_ref'!\$B\$1:\$B\$".count($deptNames),
                'Department',
                'Select the department for this asset. (Ignored for non-admin users — auto-set to their department.)');
        }
        if (! empty($catNames)) {
            $addDropdown('D2', 'D2:D1000',
                "'_ref'!\$C\$1:\$C\$".count($catNames),
                'Category',
                'Required only when entering a brand-new item name. Leave blank for existing items.');
        }
        if (! empty($unitNames)) {
            $addDropdown('E2', 'E2:E1000',
                "'_ref'!\$D\$1:\$D\$".count($unitNames),
                'Unit',
                'Required only when entering a brand-new item name. Leave blank for existing items.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->streamXlsxDownload($spreadsheet, 'item_assets_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $rows = $this->parseUploadedFile($request->file('file'));
        $user = $request->user();

        // ── Pre-load lookup maps ──────────────────────────────────────────────
        $itemMap = Item::where('item_type', 'fixed_asset')
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        $deptMap = Department::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        // Category & Unit maps — used when auto-creating a new Item
        $categoryMap = Category::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        $unitMap = [];
        Unit::all()->each(function ($u) use (&$unitMap) {
            if ($u->name)         $unitMap[strtolower($u->name)]         = $u->id;
            if ($u->abbreviation) $unitMap[strtolower($u->abbreviation)] = $u->id;
        });

        // Pre-load existing item codes (global — codes must be unique across all assets)
        $existingCodes = ItemAsset::pluck('item_code')
            ->mapWithKeys(fn ($code) => [strtolower(trim($code)) => true])
            ->all();

        $imported = 0;
        $created  = [];
        $skipped  = [];
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // ── Item Name → item_id (or auto-create new Item) ─────────────────
            $itemName = trim((string) ($row['item_name'] ?? ''));
            if ($itemName === '') {
                $errors[] = ['row' => $rowNum, 'name' => null, 'message' => 'Item Name is required.'];
                continue;
            }

            $itemId = $itemMap[strtolower($itemName)] ?? null;

            if (! $itemId) {
                // Try to auto-create the item if Category and Unit are supplied
                $categoryName = trim((string) ($row['category'] ?? ''));
                $unitName     = trim((string) ($row['unit'] ?? ''));

                if ($categoryName === '' || $unitName === '') {
                    $errors[] = [
                        'row'     => $rowNum,
                        'name'    => $itemName,
                        'message' => "Item \"{$itemName}\" does not exist. Fill the \"Category\" and \"Unit\" columns to create it automatically.",
                    ];
                    continue;
                }

                $newCategoryId = $categoryMap[strtolower($categoryName)] ?? null;
                if (! $newCategoryId) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Category \"{$categoryName}\" not found."];
                    continue;
                }

                $newUnitId = $unitMap[strtolower($unitName)] ?? null;
                if (! $newUnitId) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Unit \"{$unitName}\" not found. Use an existing unit name or abbreviation."];
                    continue;
                }

                try {
                    $newItem = Item::create([
                        'name'            => $itemName,
                        'item_type'       => 'fixed_asset',
                        'category_id'     => $newCategoryId,
                        'unit_id'         => $newUnitId,
                        'department_id'   => $user->isSystemAdmin() ? null : $user->department_id,
                        'min_stock_level' => 0,
                    ]);
                    $itemId                         = $newItem->id;
                    $itemMap[strtolower($itemName)] = $itemId; // prevent within-file duplication
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Failed to create item: '.$e->getMessage()];
                    continue;
                }
            }

            // ── Item Code ─────────────────────────────────────────────────────
            $itemCode = trim((string) ($row['item_code'] ?? ''));
            if ($itemCode === '') {
                $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Item Code is required.'];
                continue;
            }
            if (strlen($itemCode) > 100) {
                $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => 'Item Code exceeds 100 characters.'];
                continue;
            }
            $codeKey = strtolower($itemCode);
            if (array_key_exists($codeKey, $existingCodes)) {
                $skipped[] = ['row' => $rowNum, 'name' => $itemCode, 'reason' => "Item Code \"{$itemCode}\" already exists (duplicate in DB or in this file)."];
                continue;
            }

            // ── Department ────────────────────────────────────────────────────
            $deptName = trim((string) ($row['department'] ?? ''));
            if (! $user->isSystemAdmin()) {
                $deptId = $user->department_id;
            } else {
                if ($deptName === '') {
                    $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => 'Department is required.'];
                    continue;
                }
                $deptId = $deptMap[strtolower($deptName)] ?? null;
                if (! $deptId) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => "Department \"{$deptName}\" not found."];
                    continue;
                }
            }

            // ── MAC Address ───────────────────────────────────────────────────
            $macAddress = trim((string) ($row['mac_address'] ?? ''));
            if ($macAddress !== '' && ! preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/', $macAddress)) {
                $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => "MAC Address \"{$macAddress}\" is invalid. Use format XX:XX:XX:XX:XX:XX."];
                continue;
            }

            // ── Purchase Date ─────────────────────────────────────────────────
            $purchaseDateRaw = trim((string) ($row['purchase_date'] ?? ''));
            $purchaseDate    = null;
            if ($purchaseDateRaw !== '') {
                try {
                    $purchaseDate = Carbon::parse($purchaseDateRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => "Purchase Date \"{$purchaseDateRaw}\" is invalid. Use YYYY-MM-DD format."];
                    continue;
                }
            }

            // ── Purchase Price ────────────────────────────────────────────────
            $priceRaw      = $row['purchase_price'] ?? null;
            $purchasePrice = null;
            if ($priceRaw !== null && $priceRaw !== '') {
                if (! is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => 'Purchase Price must be a non-negative number.'];
                    continue;
                }
                $purchasePrice = (float) $priceRaw;
            }

            // ── Warranty Expiry ───────────────────────────────────────────────
            $warrantyRaw    = trim((string) ($row['warranty_expiry'] ?? ''));
            $warrantyExpiry = null;
            if ($warrantyRaw !== '') {
                try {
                    $warrantyExpiry = Carbon::parse($warrantyRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => "Warranty Expiry \"{$warrantyRaw}\" is invalid. Use YYYY-MM-DD format."];
                    continue;
                }
            }

            try {
                ItemAsset::create([
                    'item_id'             => $itemId,
                    'item_code'           => $itemCode,
                    'department_id'       => $deptId,
                    'serial_number'       => ($row['serial_number'] ?? '') ?: null,
                    'mac_address'         => $macAddress ?: null,
                    'purchase_date'       => $purchaseDate,
                    'purchase_price'      => $purchasePrice,
                    'warranty_expiry'     => $warrantyExpiry,
                    'delivery_receipt_no' => ($row['delivery_receipt_no'] ?? '') ?: null,
                    'notes'               => ($row['notes'] ?? '') ?: null,
                    'status'              => 'available',
                ]);

                $created[]               = ['row' => $rowNum, 'name' => $itemCode];
                $existingCodes[$codeKey] = true; // prevent within-file duplicates
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'name' => $itemCode, 'message' => 'Failed to save: '.$e->getMessage()];
            }
        }

        return $this->success(
            [
                'imported' => $imported,
                'created'  => $created,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ],
            "Import complete: {$imported} asset(s) imported."
        );
    }

    /**
     * Return an assigned asset.
     * POST /api/item-assets/{itemAsset}/return
     */
    public function returnAsset(Request $request, ItemAsset $itemAsset): JsonResponse
    {
        $assignment = $itemAsset->activeAssignment;

        if (! $assignment) {
            return $this->error('Asset has no active assignment to return.', 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($assignment, $itemAsset, $validated, $request) {
            $assignment->update([
                'returned_at' => now(),
                'returned_by' => $request->user()->id,
                'notes'       => $validated['notes'] ?? $assignment->notes,
                'status'      => 'returned',
            ]);

            $itemAsset->update(['status' => 'available']);
        });

        $itemAsset->load(['item', 'department', 'assignments' => fn($q) => $q->latest()->first()]);

        return $this->success($itemAsset, 'Asset returned successfully');
    }
}
