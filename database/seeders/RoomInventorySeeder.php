<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureStock;
use App\Models\RoomLocation;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RoomInventorySeeder extends Seeder
{
    // ── Exact item list provided by user ──────────────────────────────────────
    // key = short code used when building asset references in the future
    private const ITEMS = [
        ['name' => 'Double Bed with Mattress',   'code' => 'DBL', 'cat' => 'Furniture & Fixtures',      'col' => 1],
        ['name' => 'Single Bed with Mattress',   'code' => 'SBD', 'cat' => 'Furniture & Fixtures',      'col' => 3],
        ['name' => 'Bed Bunks with Mattress',    'code' => 'BNK', 'cat' => 'Furniture & Fixtures',      'col' => 4],
        ['name' => 'Closet',                     'code' => 'CLO', 'cat' => 'Furniture & Fixtures',      'col' => 5],
        ['name' => 'Single Closet',              'code' => 'SCL', 'cat' => 'Furniture & Fixtures',      'col' => null],
        ['name' => 'Built-In Closet',            'code' => 'BIC', 'cat' => 'Furniture & Fixtures',      'col' => null],
        ['name' => 'Hangers',                    'code' => 'HNG', 'cat' => 'Furniture & Fixtures',      'col' => 6],
        ['name' => 'Study Table with Chair',     'code' => 'STC', 'cat' => 'Furniture & Fixtures',      'col' => 7],
        ['name' => 'Study Lamp',                 'code' => 'SLP', 'cat' => 'Fixtures & Lighting',       'col' => 8],
        ['name' => 'Night Table',                'code' => 'NTB', 'cat' => 'Furniture & Fixtures',      'col' => 9],
        ['name' => 'Night Lampshade',            'code' => 'NLS', 'cat' => 'Fixtures & Lighting',       'col' => 10],
        ['name' => 'AC Remote',                  'code' => 'ACR', 'cat' => 'Electronics & Appliances',  'col' => 11],
        ['name' => 'Shower Curtain',             'code' => 'SCT', 'cat' => 'Bathroom & Fixtures',       'col' => 12],
        ['name' => 'Garbage Bin',                'code' => 'GBN', 'cat' => 'Furniture & Fixtures',      'col' => 13],
        ['name' => 'Doormat',                    'code' => 'DMT', 'cat' => 'Furniture & Fixtures',      'col' => 14],
        ['name' => 'Window Curtain Set',         'code' => 'WCS', 'cat' => 'Furniture & Fixtures',      'col' => 15],
        ['name' => 'Mini Fridge',                'code' => 'FRG', 'cat' => 'Electronics & Appliances',  'col' => 16],
        ['name' => 'Aircon',                     'code' => 'ACU', 'cat' => 'Electronics & Appliances',  'col' => 17],
        ['name' => 'Shower Heater',              'code' => 'SHT', 'cat' => 'Bathroom & Fixtures',       'col' => 18],
        ['name' => 'Single Bed',                 'code' => 'SNB', 'cat' => 'Furniture & Fixtures',      'col' => null],
        ['name' => 'Sofa',                       'code' => 'SOF', 'cat' => 'Furniture & Fixtures',      'col' => null],
    ];

    // ── Exact location areas + rooms from user ────────────────────────────────
    private const LOCATIONS = [
        [
            'name'  => 'SENIOR OFFICERS ROOM (DOUBLE BED) 1ST FLOOR AREA',
            'type'  => 'Senior Officers Room',
            'floor' => '1st Floor',
            'rooms' => ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16'],
        ],
        [
            'name'  => 'JUNIOR OFFICERS ROOM (SINGLE BED) 2ND FLOOR AREA',
            'type'  => 'Junior Officers Room',
            'floor' => '2nd Floor',
            'rooms' => ['17','18','19','20','21','22','23','24','25','26'],
        ],
        [
            'name'  => 'JUNIOR OFFICERS ROOM 27-30 (ELECT. CADET ROOMS) BED BUNKS',
            'type'  => 'Junior Officers Room',
            'floor' => '2nd Floor',
            'rooms' => ['27','28','29','30','35','36','37','38','39','40'],
        ],
        [
            'name'  => 'JUNIOR OFFICERS ROOM BED BUNKS (3PAX) 3RD FLOOR AREA',
            'type'  => 'Junior Officers Room',
            'floor' => '3rd Floor',
            'rooms' => ['34','43','44','31','32','33','41','42'],
        ],
        [
            'name'  => 'RATINGS ROOM',
            'type'  => 'Ratings Room',
            'floor' => null,
            'rooms' => ['45','46','47','48','49','50','53','54','57','58','59','60','63','64','65','66','67','68','69'],
        ],
        [
            'name'  => 'RATING ROOM BED BUNKS (6PAX)',
            'type'  => 'Ratings Room',
            'floor' => null,
            'rooms' => ['51','52','55','56','61','62','70'],
        ],
        [
            'name'  => "DORM A & B ROOM (FOR UTP's & OJT's)",
            'type'  => 'Dormitory Room',
            'floor' => null,
            'rooms' => ['ROOM A', 'ROOM B'],
        ],
    ];

    // ── Excel column index (0-based) → item code mapping ─────────────────────
    // Only columns that exist in the Excel file
    private const COL_TO_CODE = [
        1  => 'DBL',
        3  => 'SBD',
        4  => 'BNK',
        5  => 'CLO',
        6  => 'HNG',
        7  => 'STC',
        8  => 'SLP',
        9  => 'NTB',
        10 => 'NLS',
        11 => 'ACR',
        12 => 'SCT',
        13 => 'GBN',
        14 => 'DMT',
        15 => 'WCS',
        16 => 'FRG',
        // ACU (col 17) excluded: Aircon starts at 0 stock with no deployed units
        18 => 'SHT',
    ];

    public function run(): void
    {
        // ── 1. Categories ─────────────────────────────────────────────────────
        $catDefs = [
            'Furniture & Fixtures'     => 'Beds, closets, tables, chairs, curtains, doormats, and general room furniture',
            'Fixtures & Lighting'      => 'Lamps, lampshades, and fixed lighting fixtures',
            'Electronics & Appliances' => 'Air conditioners, mini fridges, AC remotes, and electronic appliances',
            'Bathroom & Fixtures'      => 'Shower curtains, shower heaters, and bathroom accessories',
        ];

        $categoryMap = [];
        foreach ($catDefs as $name => $desc) {
            $categoryMap[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        // ── 2. Units ──────────────────────────────────────────────────────────
        $unitPiece = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);
        $unitSet   = Unit::firstOrCreate(['name' => 'Set'],   ['abbreviation' => 'set']);

        // ── 3. Item definitions ───────────────────────────────────────────────
        $itemByCode = [];
        foreach (self::ITEMS as $def) {
            $unit = str_contains($def['name'], 'Set') ? $unitSet : $unitPiece;
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'category_id'     => $categoryMap[$def['cat']]->id,
                    'unit_id'         => $unit->id,
                    'item_type'       => 'fixed_asset',
                    'description'     => "Dormitory room {$def['name']}",
                    'min_stock_level' => 0,
                ],
            );
            // Ensure a stock record exists (total_quantity will be updated after Excel parse)
            RoomFurnitureStock::firstOrCreate(['item_id' => $item->id], ['total_quantity' => 0]);

            $itemByCode[$def['code']] = $item;
        }

        // ── 4. Room locations + rooms ─────────────────────────────────────────
        $locationRoomMap = []; // [locationIndex][roomNumber] => Room

        foreach (self::LOCATIONS as $locIdx => $locDef) {
            $location = RoomLocation::firstOrCreate(
                ['name' => $locDef['name']],
                [
                    'location_type' => $locDef['type'],
                    'floor'         => $locDef['floor'],
                ],
            );

            foreach ($locDef['rooms'] as $roomNum) {
                $room = Room::firstOrCreate(
                    ['room_number' => $roomNum, 'room_location_id' => $location->id],
                );
                $locationRoomMap[$locIdx][$roomNum] = $room;
            }
        }

        // ── 5. Read Excel quantities ──────────────────────────────────────────
        $filePath = storage_path('app/public/references/NDB FURNITURE INVENTORY 2026 1.xlsx');

        if (! file_exists($filePath)) {
            $this->command->warn('  Reference file not found: ' . $filePath);
            $this->command->warn('  Locations and rooms created; re-run with the file to populate quantities.');
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        // We'll track which location area we're currently inside by matching
        // the section header text to our LOCATIONS array.
        $currentLocIdx  = null;
        $totalFurniture = 0;

        foreach (array_slice($rows, 3) as $row) {
            $colA = trim((string) ($row[0] ?? ''));

            if ($colA === '') {
                continue;
            }

            // Is this a data row (room number) or a section header?
            if ($this->isDataRow($colA)) {
                if ($currentLocIdx === null) {
                    continue; // orphan row
                }

                $room = $locationRoomMap[$currentLocIdx][$colA] ?? null;
                if (! $room) {
                    continue; // room number not in our exact list — skip (e.g. gym rows)
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
                        ['room_id' => $room->id, 'item_id' => $item->id],
                        ['quantity' => $qty],
                    );

                    $totalFurniture++;
                }
            } else {
                // Section header — find which of our LOCATIONS it matches
                $currentLocIdx = $this->matchLocation($colA);
            }
        }

        $totalRooms = collect($locationRoomMap)->flatten()->count();

        // ── 6. Sync room_furniture_stock totals from actual deployed quantities ──
        foreach ($itemByCode as $item) {
            $deployed = RoomFurniture::where('item_id', $item->id)->sum('quantity');
            RoomFurnitureStock::where('item_id', $item->id)
                ->update(['total_quantity' => $deployed]);
        }

        $this->command->info("  RoomInventorySeeder: 7 location areas, {$totalRooms} rooms, {$totalFurniture} furniture quantity rows seeded.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isDataRow(string $value): bool
    {
        // Matches plain numbers ("1", "27") or "ROOM A" / "ROOM B"
        return (bool) preg_match('/^\d+[A-Za-z]?$/', $value)
            || preg_match('/^ROOM\s+[AB]$/i', $value);
    }

    /**
     * Match a section header from the Excel file to one of our LOCATIONS indices.
     * Returns the index (0-based) or null if the section should be skipped.
     */
    private function matchLocation(string $header): ?int
    {
        $upper = strtoupper($header);

        // Skip gym, hallway, washing machine sections
        if (
            str_contains($upper, 'GYM')
            || str_contains($upper, 'WASHING')
            || str_contains($upper, 'DRYER')
            || str_contains($upper, 'HALLWAY')
            || str_contains($upper, 'CORRIDOR')
            || str_contains($upper, 'CLINIC')
        ) {
            return null;
        }

        foreach (self::LOCATIONS as $idx => $def) {
            if (strtoupper($def['name']) === $upper) {
                return $idx;
            }
        }

        // Fuzzy match: check if major keywords from header match a location name
        foreach (self::LOCATIONS as $idx => $def) {
            $defUpper = strtoupper($def['name']);
            // Use first ~30 chars for matching to handle slight differences
            if (str_contains($upper, substr($defUpper, 0, 30)) || str_contains($defUpper, substr($upper, 0, 30))) {
                return $idx;
            }
        }

        return null;
    }
}
