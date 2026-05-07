<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Category::orderBy('name');

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user   = $request->user();
        $deptId = $user->isSystemAdmin()
            ? ($request->input('department_id') ?: null)
            : $user->department_id;

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $validated['department_id'] = $deptId;

        $category = Category::create($validated);

        return $this->created($category);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('items.unit');
        $category->loadCount('items');

        return $this->success($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return $this->success($category, 'Category updated successfully');
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->items()->exists()) {
            return $this->error('Cannot delete category that has items.', 422);
        }

        $category->delete();

        return $this->success(null, 'Category deleted successfully');
    }

    // ── Excel Import / Template ───────────────────────────────────────────────

    public function template(): StreamedResponse
    {
        $pool = [
            ['IT Equipment',            'Information Technology devices and peripherals'],
            ['Office Supplies',         'General office consumable supplies'],
            ['Furniture',               'Office furniture and fixtures'],
            ['Medical Supplies',        'First aid and medical consumables'],
            ['Janitorial Supplies',     'Cleaning and sanitation materials'],
            ['Communication Equipment', 'Radios, phones, and network devices'],
            ['Tools and Equipment',     'Hand tools and power tools'],
            ['Kitchen Supplies',        'Pantry and kitchen consumables'],
            ['Safety Equipment',        'PPE and personal protective equipment'],
            ['Laboratory Equipment',    'Scientific instruments and lab supplies'],
            ['Printing Materials',      'Ink, toner, and printing consumables'],
            ['Electrical Supplies',     'Wiring, switches, and electrical components'],
        ];

        shuffle($pool);

        $spreadsheet = $this->createTemplateSpreadsheet(
            headers: ['Name', 'Description'],
            sampleRows: array_slice($pool, 0, 3),
            note: 'Note: "Name" is required (max 255 chars). "Description" is optional.'
        );

        return $this->streamXlsxDownload($spreadsheet, 'categories_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $rows   = $this->parseUploadedFile($request->file('file'));
        $user   = $request->user();
        $deptId = $user->isSystemAdmin() ? null : $user->department_id;

        // Pre-load existing category names for this department scope (lowercase)
        // so duplicate checks are a simple hash-map lookup instead of N+1 queries.
        $existingQuery = Category::query();
        if ($deptId === null) {
            $existingQuery->whereNull('department_id');
        } else {
            $existingQuery->where('department_id', $deptId);
        }
        $existingNames = $existingQuery
            ->pluck('name')
            ->mapWithKeys(fn ($n) => [strtolower(trim($n)) => true])
            ->all();

        $imported = 0;
        $created  = [];
        $skipped  = [];
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Row 1 is the header
            $name   = trim((string) ($row['name'] ?? ''));

            // ── Validation ────────────────────────────────────────────────────
            if ($name === '') {
                $errors[] = ['row' => $rowNum, 'name' => null, 'message' => 'Name is required.'];
                continue;
            }

            if (strlen($name) > 255) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Name exceeds 255 characters.'];
                continue;
            }

            // ── Duplicate check (case-insensitive, scoped to department) ──────
            $nameKey = strtolower($name);
            if (array_key_exists($nameKey, $existingNames)) {
                $skipped[] = [
                    'row'    => $rowNum,
                    'name'   => $name,
                    'reason' => 'A category with this name already exists.',
                ];
                continue;
            }

            // ── Insert ────────────────────────────────────────────────────────
            try {
                Category::create([
                    'name'          => $name,
                    'description'   => $row['description'] ?: null,
                    'department_id' => $deptId,
                ]);

                $created[]              = ['row' => $rowNum, 'name' => $name];
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
