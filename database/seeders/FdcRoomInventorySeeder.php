<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FdcRoomFurnitureStock;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomLocation;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FdcRoomInventorySeeder extends Seeder
{
    // ── FDC item list ──────────────────────────────────────────────────────────
    private const ITEMS = [
        ['name' => 'Single Bed with Mattress',   'code' => 'SBD', 'cat' => 'Furniture & Fixtures',      'col' => 1],
        ['name' => 'Double Bed with Mattress',   'code' => 'DBL', 'cat' => 'Furniture & Fixtures',      'col' => 2],
        ['name' => 'Bed Bunks',                  'code' => 'BNK', 'cat' => 'Furniture & Fixtures',      'col' => 3],
        ['name' => 'Closet',                     'code' => 'CLO', 'cat' => 'Furniture & Fixtures',      'col' => 4],
        ['name' => 'Hangers',                    'code' => 'HNG', 'cat' => 'Furniture & Fixtures',      'col' => 5],
        ['name' => 'Study Table with Chair',     'code' => 'STC', 'cat' => 'Furniture & Fixtures',      'col' => 6],
        ['name' => 'Study Lamp',                 'code' => 'SLP', 'cat' => 'Fixtures & Lighting',       'col' => 7],
        ['name' => 'Night Table',                'code' => 'NTB', 'cat' => 'Furniture & Fixtures',      'col' => 8],
        ['name' => 'Night Lampshade',            'code' => 'NLS', 'cat' => 'Fixtures & Lighting',       'col' => 9],
        ['name' => 'AC Remote',                  'code' => 'ACR', 'cat' => 'Electronics & Appliances',  'col' => 10],
        ['name' => 'Shower Curtain',             'code' => 'SCT', 'cat' => 'Bathroom & Fixtures',       'col' => 11],
        ['name' => 'Garbage Bin',                'code' => 'GBN', 'cat' => 'Furniture & Fixtures',      'col' => 12],
        ['name' => 'Doormat',                    'code' => 'DMT', 'cat' => 'Furniture & Fixtures',      'col' => 13],
        ['name' => 'Window Curtain Set',         'code' => 'WCS', 'cat' => 'Furniture & Fixtures',      'col' => 14],
        ['name' => 'Mini Fridge',                'code' => 'FRG', 'cat' => 'Electronics & Appliances',  'col' => 15],
        ['name' => 'TV with Remote',             'code' => 'TVR', 'cat' => 'Electronics & Appliances',  'col' => 16],
        ['name' => 'Cable with Remote',          'code' => 'CBR', 'cat' => 'Electronics & Appliances',  'col' => 17],
    ];

    // ── FDC location: 1 location with rooms 1–52 ─────────────────────────────
    private const FDC_LOCATION = [
        'name'  => 'FDC',
        'type'  => 'fdc',
        'floor' => null,
    ];

    private const FDC_ROOMS = [
        '1','2','3','4','5','6','7','8','9','10',
        '11','12','13','14','15','16','17','18','19','20',
        '21','22','23','24','25','26','27','28','29','30',
        '31','32','33','34','35','36','37','38','39','40',
        '41','42','43','44','45','46','47','48','49','50',
        '51','52',
    ];

    // ── Excel column index (0-based) → item code ──────────────────────────────
    // Adjust these if the actual Excel column order differs
    private const COL_TO_CODE = [
        1  => 'SBD',  // Single Bed with Mattress
        2  => 'DBL',  // Double Bed with Mattress
        3  => 'BNK',  // Bed Bunks
        4  => 'CLO',  // Closet
        5  => 'HNG',  // Hangers
        6  => 'STC',  // Study Table with Chair
        7  => 'SLP',  // Study Lamp
        8  => 'NTB',  // Night Table
        9  => 'NLS',  // Night Lampshade
        10 => 'ACR',  // AC Remote
        11 => 'SCT',  // Shower Curtain
        12 => 'GBN',  // Garbage Bin
        13 => 'DMT',  // Doormat
        14 => 'WCS',  // Window Curtain Set
        15 => 'FRG',  // Mini Fridge
        16 => 'TVR',  // TV with Remote
        17 => 'CBR',  // Cable with Remote
    ];

    public function run(): void
    {
        // ── 1. Categories ─────────────────────────────────────────────────────
        $catDefs = [
            'Furniture & Fixtures'     => 'Beds, closets, tables, chairs, curtains, doormats, and general room furniture',
            'Fixtures & Lighting'      => 'Lamps, lampshades, and fixed lighting fixtures',
            'Electronics & Appliances' => 'Air conditioners, mini fridges, TVs, remotes, and electronic appliances',
            'Bathroom & Fixtures'      => 'Shower curtains, shower heaters, and bathroom accessories',
        ];

        $categoryMap = [];
        foreach ($catDefs as $name => $desc) {
            $categoryMap[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        // ── 2. Units ──────────────────────────────────────────────────────────
        $unitPiece = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);
        $unitSet   = Unit::firstOrCreate(['name' => 'Set'],   ['abbreviation' => 'set']);

        // ── 3. Item definitions + FDC stock records ───────────────────────────
        $itemByCode = [];
        foreach (self::ITEMS as $def) {
            $unit = str_contains($def['name'], 'Set') ? $unitSet : $unitPiece;
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'category_id'     => $categoryMap[$def['cat']]->id,
                    'unit_id'         => $unit->id,
                    'item_type'       => 'fixed_asset',
                    'description'     => "FDC room {$def['name']}",
                    'min_stock_level' => 0,
                ],
            );
            // Ensure a FDC stock record exists (total_quantity will be updated later)
            FdcRoomFurnitureStock::firstOrCreate(['item_id' => $item->id, 'sub_item_id' => null], ['total_quantity' => 0]);

            $itemByCode[$def['code']] = $item;
        }

        // ── 4. FDC location + rooms ───────────────────────────────────────────
        $location = RoomLocation::firstOrCreate(
            ['name' => self::FDC_LOCATION['name']],
            [
                'location_type' => self::FDC_LOCATION['type'],
                'floor'         => self::FDC_LOCATION['floor'],
            ],
        );

        $roomMap = []; // roomNumber => Room
        foreach (self::FDC_ROOMS as $roomNum) {
            $room          = Room::firstOrCreate(
                ['room_number' => $roomNum, 'room_location_id' => $location->id],
            );
            $roomMap[$roomNum] = $room;
        }

        // ── 5. Read Excel quantities ──────────────────────────────────────────
        $filePath = storage_path('app/public/references/FDC FURNITURE INVENTORY 2026 1.xlsx');

        if (! file_exists($filePath)) {
            $this->command->warn('  FdcRoomInventorySeeder: Reference file not found: ' . $filePath);
            $this->command->warn('  FDC location and rooms created; re-run with the file to populate quantities.');
            return;
        }

        $spreadsheet    = IOFactory::load($filePath);
        $rows           = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $totalFurniture = 0;

        foreach (array_slice($rows, 3) as $row) {
            $colA = trim((string) ($row[0] ?? ''));

            if ($colA === '' || ! $this->isRoomRow($colA)) {
                // Skip section headers, gym, office, hallway rows
                continue;
            }

            $room = $roomMap[$colA] ?? null;
            if (! $room) {
                continue; // room not in our FDC list — skip
            }

            foreach (self::COL_TO_CODE as $colIdx => $code) {
                $cellVal = trim((string) ($row[$colIdx] ?? ''));

                if ($cellVal === '' || $cellVal === '0') {
                    continue;
                }

                if (! preg_match('/^(\d+)/', $cellVal, $m)) {
                    continue;
                }

                $qty = (int) $m[1];
                if ($qty <= 0) {
                    continue;
                }

                $item = $itemByCode[$code] ?? null;
                if (! $item) {
                    continue;
                }

                RoomFurniture::updateOrCreate(
                    ['room_id' => $room->id, 'item_id' => $item->id, 'sub_item_id' => null],
                    ['quantity' => $qty],
                );

                $totalFurniture++;
            }
        }

        // ── 6. Sync fdc_room_furniture_stocks totals from actual deployed quantities
        $fdcRoomIds = collect($roomMap)->pluck('id');
        foreach ($itemByCode as $item) {
            $deployed = RoomFurniture::where('item_id', $item->id)
                ->whereIn('room_id', $fdcRoomIds)
                ->sum('quantity');
            FdcRoomFurnitureStock::where('item_id', $item->id)
                ->whereNull('sub_item_id')
                ->update(['total_quantity' => $deployed]);
        }

        $this->command->info("  FdcRoomInventorySeeder: 1 FDC location, 52 rooms, {$totalFurniture} furniture quantity rows seeded.");
    }

    private function isRoomRow(string $value): bool
    {
        // Matches plain integers 1–52
        return (bool) preg_match('/^\d+$/', $value) && (int) $value >= 1 && (int) $value <= 52;
    }
}
