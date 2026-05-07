<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\ReceivalDocument;
use App\Models\StockReceival;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReceivalController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    /**
     * List receivals with filters.
     * Filters: department_id, item_id, supplier_id
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = StockReceival::with(['item.unit', 'department', 'supplier', 'receivedBy', 'documents']);

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        return $this->success(
            $query->orderByDesc('received_at')->get()
        );
    }

    public function show(StockReceival $stockReceival): JsonResponse
    {
        $stockReceival->load(['item.unit', 'department', 'supplier', 'receivedBy', 'documents']);

        return $this->success($stockReceival);
    }

    /**
     * Receive new consumable stock into a department.
     * Also increments the InventoryStock.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'item_id'              => 'required|exists:items,id',
            'department_id'        => $user->isSystemAdmin() ? 'required|exists:departments,id' : 'nullable|exists:departments,id',
            'quantity'             => 'required|numeric|min:0.01',
            'unit_cost'            => 'nullable|numeric|min:0',
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'delivery_receipt_no'  => 'nullable|string|max:100',
            'delivery_receipt_file'=> 'nullable|file|mimes:pdf|max:5120',
            'received_at'          => 'nullable|date',
            'notes'                => 'nullable|string',
        ]);

        if (! $user->isSystemAdmin()) {
            $validated['department_id'] = $user->department_id;
        }

        $item = Item::findOrFail($validated['item_id']);
        if ($item->isFixedAsset()) {
            return $this->error('Fixed assets are registered individually via item-assets, not as stock receivals.', 422);
        }

        // Handle file upload outside the transaction
        $filePath = null;
        if ($request->hasFile('delivery_receipt_file')) {
            $filePath = $request->file('delivery_receipt_file')
                ->store('delivery_receipt', 'public');
        }

        $receival = DB::transaction(function () use ($validated, $request, $filePath) {
            // Upsert inventory stock
            $stock = InventoryStock::firstOrCreate(
                ['item_id' => $validated['item_id'], 'department_id' => $validated['department_id']],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $validated['quantity']);

            return StockReceival::create([
                'item_id'               => $validated['item_id'],
                'department_id'         => $validated['department_id'],
                'quantity'              => $validated['quantity'],
                'unit_cost'             => $validated['unit_cost'] ?? null,
                'supplier_id'           => $validated['supplier_id'] ?? null,
                'delivery_receipt_no'   => $validated['delivery_receipt_no'] ?? null,
                'delivery_receipt_file' => $filePath,
                'received_by'           => $request->user()->id,
                'received_at'           => $validated['received_at'] ?? now(),
                'notes'                 => $validated['notes'] ?? null,
            ]);
        });

        $receival->load(['item.unit', 'department', 'supplier', 'receivedBy']);

        return $this->created($receival, 'Stock received successfully');
    }

    /**
     * Upload a related purchase document for a receival.
     * POST /api/stock-receivals/{stockReceival}/documents
     */
    public function uploadDocument(Request $request, StockReceival $stockReceival): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
        ]);

        $path = $request->file('file')->store('receival_documents', 'public');
        $doc  = $stockReceival->documents()->create([
            'file_path'     => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return $this->success($doc, 'Document uploaded.');
    }

    // ── Excel template ────────────────────────────────────────────────────────

    public function template(Request $request): StreamedResponse
    {
        $user = $request->user();

        // Load consumable items (scoped by dept for non-admins)
        $itemQuery = Item::where('item_type', 'consumable');
        if (! $user->isSystemAdmin()) {
            $itemQuery->where('department_id', $user->department_id);
        }
        $items     = $itemQuery->orderBy('name')->pluck('name')->toArray();
        $depts     = Department::orderBy('name')->pluck('name')->toArray();
        $suppliers = Supplier::orderBy('name')->pluck('name')->toArray();

        $headers = [
            'Item Name', 'Department', 'Quantity', 'Unit Cost',
            'Supplier', 'Delivery Receipt No', 'Date Received', 'Notes',
        ];

        $sampleRows = [
            [$items[0] ?? 'Chicken Breast', $depts[0] ?? 'GOD', 20, 185.00, $suppliers[0] ?? '', 'DR-2025-001', date('Y-m-d'), 'Initial stock'],
            [$items[1] ?? 'Bond Paper A4',  $depts[0] ?? 'PRPD', 10, 350.00, $suppliers[0] ?? '', '',            date('Y-m-d'), ''],
        ];

        $spreadsheet = $this->createTemplateSpreadsheet(
            $headers,
            $sampleRows,
            "Column guide:\n" .
            "• Item Name   — must match an existing consumable item name.\n" .
            "• Department  — required for admin users; ignored for department users (your dept is used).\n" .
            "• Quantity    — required; positive number.\n" .
            "• Unit Cost   — optional; cost per unit in PHP.\n" .
            "• Supplier    — optional; must match an existing supplier name.\n" .
            "• Delivery Receipt No — optional; free text.\n" .
            "• Date Received — optional; format YYYY-MM-DD. Defaults to today if blank.\n" .
            "• Notes       — optional; free text."
        );

        $dataSheet = $spreadsheet->getActiveSheet();

        // ── Hidden _ref sheet with dropdown lists ─────────────────────────────
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('_ref');

        foreach ($items as $i => $name) {
            $refSheet->setCellValue('A' . ($i + 1), $name);
        }
        foreach ($depts as $i => $name) {
            $refSheet->setCellValue('B' . ($i + 1), $name);
        }
        foreach ($suppliers as $i => $name) {
            $refSheet->setCellValue('C' . ($i + 1), $name);
        }

        $refSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
        $spreadsheet->setActiveSheetIndex(0);

        // ── Apply data-validation dropdowns ───────────────────────────────────
        $addDropdown = function (string $startCell, string $sqref, string $formula) use ($dataSheet): void {
            $v = $dataSheet->getCell($startCell)->getDataValidation();
            $v->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $v->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $v->setAllowBlank(true);
            $v->setShowDropDown(true);
            $v->setShowInputMessage(false);
            $v->setShowErrorMessage(false);
            $v->setFormula1($formula);
            $v->setSqref($sqref);
        };

        $itemCount = max(count($items), 1);
        $deptCount = max(count($depts), 1);
        $suppCount = max(count($suppliers), 1);

        $addDropdown('A2', 'A2:A1000', "'_ref'!\$A\$1:\$A\${$itemCount}");
        $addDropdown('B2', 'B2:B1000', "'_ref'!\$B\$1:\$B\${$deptCount}");
        $addDropdown('E2', 'E2:E1000', "'_ref'!\$C\$1:\$C\${$suppCount}");

        return $this->streamXlsxDownload($spreadsheet, 'stock_receivals_template.xlsx');
    }

    // ── Bulk import ───────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $rows = $this->parseUploadedFile($request->file('file'));

        if (empty($rows)) {
            return $this->error('No data rows found in the file.', 422);
        }

        $user = $request->user();

        // Pre-load reference maps
        $itemQuery = Item::where('item_type', 'consumable');
        if (! $user->isSystemAdmin()) {
            $itemQuery->where('department_id', $user->department_id);
        }
        $itemMap = $itemQuery->get()->keyBy(fn ($i) => strtolower(trim($i->name)));
        $deptMap = Department::all()->keyBy(fn ($d) => strtolower(trim($d->name)));
        $suppMap = Supplier::all()->keyBy(fn ($s) => strtolower(trim($s->name)));

        $created  = [];
        $skipped  = [];
        $errors   = [];
        $imported = 0;

        foreach ($rows as $index => $row) {
            $rowNum   = $index + 2;
            $itemName = trim((string) ($row['item_name'] ?? $row['item'] ?? ''));
            $deptName = trim((string) ($row['department'] ?? ''));
            $qtyRaw   = trim((string) ($row['quantity'] ?? ''));
            $unitCost = trim((string) ($row['unit_cost'] ?? ''));
            $suppName = trim((string) ($row['supplier'] ?? ''));
            $drNo     = trim((string) ($row['delivery_receipt_no'] ?? ''));
            $dateRaw  = trim((string) ($row['date_received'] ?? ''));
            $notes    = trim((string) ($row['notes'] ?? ''));

            // ── Validate item ─────────────────────────────────────────────
            if ($itemName === '') {
                $errors[] = ['row' => $rowNum, 'name' => null, 'message' => 'Item Name is required.'];
                continue;
            }

            $item = $itemMap[strtolower($itemName)] ?? null;
            if (! $item) {
                $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Item \"{$itemName}\" not found or is not a consumable item."];
                continue;
            }

            // ── Validate quantity ─────────────────────────────────────────
            if ($qtyRaw === '' || ! is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
                $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Quantity must be a positive number.'];
                continue;
            }

            // ── Validate unit cost ────────────────────────────────────────
            if ($unitCost !== '' && (! is_numeric($unitCost) || (float) $unitCost < 0)) {
                $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Unit Cost must be a non-negative number.'];
                continue;
            }

            // ── Resolve department ────────────────────────────────────────
            $deptId = null;
            if ($user->isSystemAdmin()) {
                if ($deptName === '') {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Department is required.'];
                    continue;
                }
                $dept = $deptMap[strtolower($deptName)] ?? null;
                if (! $dept) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Department \"{$deptName}\" not found."];
                    continue;
                }
                $deptId = $dept->id;
            } else {
                $deptId = $user->department_id;
            }

            // ── Resolve supplier (optional) ───────────────────────────────
            $suppId = null;
            if ($suppName !== '') {
                $supp = $suppMap[strtolower($suppName)] ?? null;
                if (! $supp) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Supplier \"{$suppName}\" not found."];
                    continue;
                }
                $suppId = $supp->id;
            }

            // ── Validate date ─────────────────────────────────────────────
            $receivedAt = null;
            if ($dateRaw !== '') {
                $parsed = date_create($dateRaw);
                if (! $parsed) {
                    $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => "Date Received \"{$dateRaw}\" is not a valid date."];
                    continue;
                }
                $receivedAt = $parsed->format('Y-m-d');
            }

            // ── Persist ───────────────────────────────────────────────────
            try {
                DB::transaction(function () use ($item, $deptId, $qtyRaw, $unitCost, $suppId, $drNo, $receivedAt, $notes, $user) {
                    $stock = InventoryStock::firstOrCreate(
                        ['item_id' => $item->id, 'department_id' => $deptId],
                        ['quantity' => 0]
                    );
                    $stock->increment('quantity', (float) $qtyRaw);

                    StockReceival::create([
                        'item_id'              => $item->id,
                        'department_id'        => $deptId,
                        'quantity'             => (float) $qtyRaw,
                        'unit_cost'            => $unitCost !== '' ? (float) $unitCost : null,
                        'supplier_id'          => $suppId,
                        'delivery_receipt_no'  => $drNo ?: null,
                        'received_by'          => $user->id,
                        'received_at'          => $receivedAt ?? now()->format('Y-m-d'),
                        'notes'                => $notes ?: null,
                    ]);
                });

                $created[] = ['row' => $rowNum, 'name' => $itemName];
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'name' => $itemName, 'message' => 'Failed to save: ' . $e->getMessage()];
            }
        }

        return $this->success(compact('imported', 'created', 'skipped', 'errors'), 'Import complete.');
    }
}
