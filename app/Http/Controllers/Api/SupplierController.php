<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use App\Traits\HandlesExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    use ApiResponse, HandlesExcelImport;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Supplier::withCount('stockReceivials')->orderBy('name');

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string',
            'department_id'  => 'nullable|exists:departments,id',
        ]);

        $validated['department_id'] = $user->isSystemAdmin()
            ? ($validated['department_id'] ?? null)
            : $user->department_id;

        return $this->created(Supplier::create($validated));
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load('stockReceivials.item');
        $supplier->loadCount('stockReceivials');

        return $this->success($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string',
        ]);

        $supplier->update($validated);

        return $this->success($supplier, 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->success(null, 'Supplier deleted successfully');
    }

    // ── Excel Import / Template ───────────────────────────────────────────────

    public function template(): StreamedResponse
    {
        $pool = [
            ['ABC Supplies Corp',      'Juan dela Cruz',  'juan@abcsupplies.com',     '09171234567',  '123 Rizal Ave, Manila'],
            ['XYZ Trading Co.',        'Maria Santos',    'maria@xyztrading.ph',       '02-8123-4567', '456 Ayala Ave, Makati City'],
            ['Global Office Inc.',     'Pedro Reyes',     'info@globaloffice.com',     '09281234567',  '789 Osmena Blvd, Cebu City'],
            ['Prime Solutions Ltd.',   'Ana Garcia',      'ana@primesolutions.ph',     '09351234567',  '12 Session Rd, Baguio City'],
            ['Metro Distributors',     'Carlos Tan',      'carlos@metrodist.com',      '02-7654-3210', '34 Bonifacio St, Iloilo City'],
            ['Sunshine Enterprises',   'Liza Reyes',      'liza@sunshine.ph',          '09451234567',  '56 Colon St, Cebu City'],
            ['Pacific Trade Corp',     'Mark Lim',        'mark@pacifictrade.com',     '02-5678-9012', '78 Taft Ave, Manila'],
            ['Alpha Suppliers Inc.',   'Joy Cruz',        'joy@alphasuppliers.ph',     '09561234567',  '90 Katipunan Ave, Quezon City'],
            ['Delta Office Supply',    'Ron Santos',      'ron@deltaoffice.com',       '09671234567',  '11 España Blvd, Manila'],
            ['Omega General Trading',  'Lyn Bautista',    'lyn@omegatrading.ph',       '02-4321-5678', '22 Shaw Blvd, Mandaluyong'],
            ['Summit Procurement Co.', 'Ben Villanueva',  'ben@summitprocure.com',     '09781234567',  '33 Aurora Blvd, Quezon City'],
            ['Horizon Supply Group',   'Claire Morales',  'claire@horizonsupply.ph',   '09891234567',  '44 EDSA, Pasay City'],
        ];

        shuffle($pool);

        $spreadsheet = $this->createTemplateSpreadsheet(
            headers: ['Name', 'Contact Person', 'Email', 'Phone', 'Address'],
            sampleRows: array_slice($pool, 0, 3),
            note: 'Note: "Name" is required. "Email" must be a valid email address if provided. Other fields are optional.'
        );

        return $this->streamXlsxDownload($spreadsheet, 'suppliers_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $rows   = $this->parseUploadedFile($request->file('file'));
        $user   = $request->user();
        $deptId = $user->isSystemAdmin() ? null : $user->department_id;

        // Pre-load existing supplier names (case-insensitive, dept-scoped)
        $existingQuery = Supplier::query();
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
            $rowNum = $index + 2;
            $name   = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $errors[] = ['row' => $rowNum, 'name' => null, 'message' => 'Name is required.'];
                continue;
            }

            if (strlen($name) > 255) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Name exceeds 255 characters.'];
                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => "Invalid email address: \"{$email}\"."];
                continue;
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            if (strlen($phone) > 50) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Phone number exceeds 50 characters.'];
                continue;
            }

            $contactPerson = trim((string) ($row['contact_person'] ?? ''));
            if (strlen($contactPerson) > 255) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'message' => 'Contact Person exceeds 255 characters.'];
                continue;
            }

            // ── Duplicate check ────────────────────────────────────────────────
            $nameKey = strtolower($name);
            if (array_key_exists($nameKey, $existingNames)) {
                $skipped[] = [
                    'row'    => $rowNum,
                    'name'   => $name,
                    'reason' => 'A supplier with this name already exists.',
                ];
                continue;
            }

            try {
                Supplier::create([
                    'name'           => $name,
                    'contact_person' => $contactPerson ?: null,
                    'email'          => $email ?: null,
                    'phone'          => $phone ?: null,
                    'address'        => $row['address'] ?: null,
                    'department_id'  => $deptId,
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
