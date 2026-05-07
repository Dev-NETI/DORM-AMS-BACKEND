<?php

namespace Database\Seeders;

use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemAssetSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $dept  = fn (string $code) => Department::where('code', $code)->first()?->id;
        $item  = fn (string $name) => Item::where('name', $name)->first()?->id;
        $emp   = fn (string $eid) => Employee::where('employee_id', $eid)->first();

        $assets = [
            // ── Laptops (NOD) ─────────────────────────────────────────────
            ['item_code' => 'NOD-LAP-001', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00101', 'dept' => 'NOD', 'purchase_date' => '2024-01-15', 'purchase_price' => 75000.00, 'status' => 'assigned', 'assignee' => 'EMP-001'],
            ['item_code' => 'NOD-LAP-002', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00102', 'dept' => 'NOD', 'purchase_date' => '2024-01-15', 'purchase_price' => 75000.00, 'status' => 'assigned', 'assignee' => 'EMP-002'],
            ['item_code' => 'NOD-LAP-003', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00103', 'dept' => 'NOD', 'purchase_date' => '2024-03-20', 'purchase_price' => 77500.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'NOD-LAP-004', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00104', 'dept' => 'NOD', 'purchase_date' => '2024-03-20', 'purchase_price' => 77500.00, 'status' => 'assigned', 'assignee' => 'EMP-003'],
            // ── Desktops (NOD) ────────────────────────────────────────────
            ['item_code' => 'NOD-DES-001', 'item' => 'Lenovo ThinkCentre M720', 'serial' => 'LNV-TCM-SN00201', 'dept' => 'NOD', 'purchase_date' => '2023-06-10', 'purchase_price' => 48000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'NOD-DES-002', 'item' => 'Lenovo ThinkCentre M720', 'serial' => 'LNV-TCM-SN00202', 'dept' => 'NOD', 'purchase_date' => '2023-06-10', 'purchase_price' => 48000.00, 'status' => 'available', 'assignee' => null],
            // ── Tablets (NOD) ─────────────────────────────────────────────
            ['item_code' => 'NOD-TAB-001', 'item' => 'Apple iPad Pro 11"', 'serial' => 'AP-IPD-SN00301', 'dept' => 'NOD', 'purchase_date' => '2024-02-05', 'purchase_price' => 72000.00, 'status' => 'available', 'assignee' => null],
            // ── Network Switch (NOD) ──────────────────────────────────────
            ['item_code' => 'NOD-NSW-001', 'item' => 'Cisco Catalyst 2960-X 24-Port', 'serial' => 'CS-C2960X-SN00401', 'dept' => 'NOD', 'purchase_date' => '2023-11-01', 'purchase_price' => 42000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'NOD-NSW-002', 'item' => 'Cisco Catalyst 2960-X 24-Port', 'serial' => 'CS-C2960X-SN00402', 'dept' => 'NOD', 'purchase_date' => '2023-11-01', 'purchase_price' => 42000.00, 'status' => 'available', 'assignee' => null],
            // ── Server (NOD) ──────────────────────────────────────────────
            ['item_code' => 'NOD-SRV-001', 'item' => 'Dell PowerEdge R750', 'serial' => 'DL-PER750-SN00501', 'dept' => 'NOD', 'purchase_date' => '2023-09-15', 'purchase_price' => 320000.00, 'status' => 'available', 'assignee' => null],
            // ── UPS (NOD) ─────────────────────────────────────────────────
            ['item_code' => 'NOD-UPS-001', 'item' => 'APC Smart-UPS SRT 1.5kVA', 'serial' => 'APC-SRT-SN00601', 'dept' => 'NOD', 'purchase_date' => '2023-09-15', 'purchase_price' => 22000.00, 'status' => 'available', 'assignee' => null],
            // ── Kitchen Equipment (GOD) ───────────────────────────────────
            ['item_code' => 'GOD-FRG-001', 'item' => 'Toshiba Inverter Refrigerator', 'serial' => 'TSB-GRA-SN00701', 'dept' => 'GOD', 'purchase_date' => '2024-01-20', 'purchase_price' => 28000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'GOD-FRG-002', 'item' => 'Toshiba Inverter Refrigerator', 'serial' => 'TSB-GRA-SN00702', 'dept' => 'GOD', 'purchase_date' => '2024-01-20', 'purchase_price' => 28000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'GOD-GCR-001', 'item' => 'Modena Commercial Gas Range', 'serial' => 'MOD-CS6640-SN00801', 'dept' => 'GOD', 'purchase_date' => '2024-01-20', 'purchase_price' => 27000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'GOD-CKW-001', 'item' => 'Meyer Accolade Cookware Set', 'serial' => null, 'dept' => 'GOD', 'purchase_date' => '2024-01-20', 'purchase_price' => 9500.00, 'status' => 'available', 'assignee' => null],
            // ── Furniture (HRAD) ──────────────────────────────────────────
            ['item_code' => 'HRAD-DSK-001', 'item' => 'Executive Office Desk 1500mm', 'serial' => null, 'dept' => 'HRAD', 'purchase_date' => '2023-04-10', 'purchase_price' => 9500.00, 'status' => 'assigned', 'assignee' => 'EMP-008'],
            ['item_code' => 'HRAD-DSK-002', 'item' => 'Executive Office Desk 1500mm', 'serial' => null, 'dept' => 'HRAD', 'purchase_date' => '2023-04-10', 'purchase_price' => 9500.00, 'status' => 'assigned', 'assignee' => 'EMP-009'],
            ['item_code' => 'HRAD-CHR-001', 'item' => 'SIDIZ T50 Ergonomic Chair', 'serial' => null, 'dept' => 'HRAD', 'purchase_date' => '2023-04-10', 'purchase_price' => 12000.00, 'status' => 'assigned', 'assignee' => 'EMP-008'],
            ['item_code' => 'HRAD-CHR-002', 'item' => 'SIDIZ T50 Ergonomic Chair', 'serial' => null, 'dept' => 'HRAD', 'purchase_date' => '2023-04-10', 'purchase_price' => 12000.00, 'status' => 'assigned', 'assignee' => 'EMP-009'],
            // ── Laptops for other departments ─────────────────────────────
            ['item_code' => 'PRPD-LAP-001', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00901', 'dept' => 'PRPD', 'purchase_date' => '2024-02-01', 'purchase_price' => 75000.00, 'status' => 'assigned', 'assignee' => 'EMP-006'],
            ['item_code' => 'PRPD-LAP-002', 'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN00902', 'dept' => 'PRPD', 'purchase_date' => '2024-02-01', 'purchase_price' => 75000.00, 'status' => 'assigned', 'assignee' => 'EMP-007'],
            ['item_code' => 'FIN-LAP-001',  'item' => 'ASUS ROG Strix G15', 'serial' => 'ASUS-ROG-SN01001', 'dept' => 'FIN',  'purchase_date' => '2024-03-01', 'purchase_price' => 75000.00, 'status' => 'assigned', 'assignee' => 'EMP-015'],
            // ── AC Units (DOD) ────────────────────────────────────────────
            ['item_code' => 'DOD-ACU-001', 'item' => 'Carrier Aura Inverter Split-Type AC', 'serial' => 'CAR-AUR-SN01101', 'dept' => 'DOD', 'purchase_date' => '2023-05-01', 'purchase_price' => 32000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'DOD-ACU-002', 'item' => 'Carrier Aura Inverter Split-Type AC', 'serial' => 'CAR-AUR-SN01102', 'dept' => 'DOD', 'purchase_date' => '2023-05-01', 'purchase_price' => 32000.00, 'status' => 'available', 'assignee' => null],
            ['item_code' => 'DOD-ACU-003', 'item' => 'Carrier Aura Inverter Split-Type AC', 'serial' => 'CAR-AUR-SN01103', 'dept' => 'DOD', 'purchase_date' => '2024-01-10', 'purchase_price' => 34000.00, 'status' => 'available', 'assignee' => null],
        ];

        foreach ($assets as $assetData) {
            $itemId = $item($assetData['item']);
            $deptId = $dept($assetData['dept']);

            if (! $itemId || ! $deptId) {
                continue;
            }

            $asset = ItemAsset::firstOrCreate(
                ['item_code' => $assetData['item_code']],
                [
                    'item_id'         => $itemId,
                    'serial_number'   => $assetData['serial'],
                    'purchase_date'   => $assetData['purchase_date'],
                    'purchase_price'  => $assetData['purchase_price'],
                    'warranty_expiry' => date('Y-m-d', strtotime($assetData['purchase_date'] . ' +1 year')),
                    'department_id'   => $deptId,
                    'status'          => $assetData['status'],
                ]
            );

            // Create active assignment if assignee is set and asset doesn't have one yet
            if ($assetData['assignee'] && $asset->wasRecentlyCreated) {
                $employee = $emp($assetData['assignee']);
                if ($employee) {
                    AssetAssignment::create([
                        'asset_id'            => $asset->id,
                        'assignable_type'     => Employee::class,
                        'assignable_id'       => $employee->id,
                        'assigned_by'         => $admin->id,
                        'assigned_at'         => now()->subDays(rand(30, 180)),
                        'condition_on_assign' => 'good',
                        'purpose'             => 'Official work use',
                        'status'              => 'active',
                    ]);
                }
            }
        }
    }
}
