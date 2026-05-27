<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CdcRoomFurnitureStock;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomLocation;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CdcRoomInventorySeeder extends Seeder
{
    // ── CDC item list ──────────────────────────────────────────────────────────
    // Items 0–21 correspond to the main room section columns (0-based col 1..22).
    // Items 22+ are from admin office, hallway, and classroom sections.
    private const ITEMS = [
        // ── Main room section (rows 4-55, rooms 1-23) ─────────────────────────
        ['name' => 'Single Bed with Mattress',   'cat' => 'Furniture & Fixtures'],      // col 1
        ['name' => 'Bed Bunks',                  'cat' => 'Furniture & Fixtures'],      // col 2
        ['name' => 'Closet',                     'cat' => 'Furniture & Fixtures'],      // col 3
        ['name' => 'Cabinet for Fridge',         'cat' => 'Furniture & Fixtures'],      // col 4
        ['name' => 'Clip Hangers',               'cat' => 'Furniture & Fixtures'],      // col 5
        ['name' => 'Hangers',                    'cat' => 'Furniture & Fixtures'],      // col 6
        ['name' => 'Mini Fridge',                'cat' => 'Electronics & Appliances'], // col 7
        ['name' => 'Throw Pillow',               'cat' => 'Furniture & Fixtures'],      // col 8
        ['name' => 'Pillows',                    'cat' => 'Furniture & Fixtures'],      // col 9
        ['name' => 'TV Remote',                  'cat' => 'Electronics & Appliances'], // col 10
        ['name' => 'LED TV',                     'cat' => 'Electronics & Appliances'], // col 11
        ['name' => 'Study Chair',                'cat' => 'Furniture & Fixtures'],      // col 12
        ['name' => 'Study Table',                'cat' => 'Furniture & Fixtures'],      // col 13
        ['name' => 'Water Kettle',               'cat' => 'Electronics & Appliances'], // col 14
        ['name' => 'Study Lamp',                 'cat' => 'Fixtures & Lighting'],       // col 15
        ['name' => 'Water Heater',               'cat' => 'Electronics & Appliances'], // col 16
        ['name' => 'Aircon',                     'cat' => 'Electronics & Appliances'], // col 17
        ['name' => 'Aircon Remote',              'cat' => 'Electronics & Appliances'], // col 18
        ['name' => 'Window Curtain',             'cat' => 'Furniture & Fixtures'],      // col 19
        ['name' => 'Doormats',                   'cat' => 'Furniture & Fixtures'],      // col 20
        ['name' => 'Trashbins',                  'cat' => 'Furniture & Fixtures'],      // col 21
        ['name' => 'Fire Extinguisher',          'cat' => 'Safety Equipment'],          // col 22
        // ── Admin Office (hardcoded from Excel row 57-58) ─────────────────────
        ['name' => 'Office Table',               'cat' => 'Furniture & Fixtures'],
        ['name' => 'Office Chair',               'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 3 Seater',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 2 Seater',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Center Table',               'cat' => 'Furniture & Fixtures'],
        ['name' => 'Stainless Cabinet',          'cat' => 'Furniture & Fixtures'],
        ['name' => 'Printer',                    'cat' => 'Electronics & Appliances'],
        ['name' => 'Side Table',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Console Table',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Telephone',                  'cat' => 'Electronics & Appliances'],
        ['name' => 'Water Dispenser (Fabriano)', 'cat' => 'Electronics & Appliances'],
        ['name' => 'Sliding Wooden Cabinet',     'cat' => 'Furniture & Fixtures'],
        ['name' => 'Aircon (Aux)',               'cat' => 'Electronics & Appliances'],
        // ── Hallway & Lobby ────────────────────────────────────────────────────
        ['name' => 'Water Dispenser',            'cat' => 'Electronics & Appliances'],
        ['name' => 'Brown Cabinet',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Wall Frame',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 1 Seater',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Steel Bench (Black)',        'cat' => 'Furniture & Fixtures'],
        ['name' => 'Guard Table',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Cigarette Ashtray Bin',      'cat' => 'Furniture & Fixtures'],
        // ── Classrooms ─────────────────────────────────────────────────────────
        ['name' => 'Training Tables',            'cat' => 'Furniture & Fixtures'],
        ['name' => 'Colored Chairs',             'cat' => 'Furniture & Fixtures'],
        ['name' => 'Monitor',                    'cat' => 'Electronics & Appliances'],
    ];

    // ── Hardcoded totals for non-room sections (from CDC Excel reference) ──────
    // Admin Office (Excel row 58):
    //   Office Table=2, Office Chair=2, Sofa 3 Seater=1, Sofa 2 Seater=1,
    //   Center Table=1, Stainless Cabinet=1, Printer=1, Side Table=1,
    //   Console Table=1, Telephone=1, Water Dispenser(Fabriano)=1,
    //   Sliding Wooden Cabinet=1, Aircon(Aux)=1
    // Hallway & Lobby (Excel row 63):
    //   Water Dispenser=1, Sofa 2 Seater=2, Fire Extinguisher=4, Side Table=4,
    //   Brown Cabinet=1, Wall Frame=2, Sofa 3 Seater=1, Sofa 1 Seater=2,
    //   Center Table=1, Water Dispenser(Fabriano)=1, Steel Bench(Black)=5,
    //   Guard Table=1, Cigarette Ashtray Bin=1
    // Classrooms (rooms 1-8, Aircon=7, Colored Chairs=75, Monitor=7):
    //   Aircon adds to main Aircon total
    private const NON_ROOM_TOTALS = [
        // item name => total qty
        'Office Table'               => 2,
        'Office Chair'               => 2,
        'Sofa 3 Seater'              => 2,   // 1 admin + 1 hallway
        'Sofa 2 Seater'              => 3,   // 1 admin + 2 hallway (B+K)
        'Center Table'               => 2,   // 1 admin + 1 hallway
        'Stainless Cabinet'          => 1,
        'Printer'                    => 1,
        'Side Table'                 => 5,   // 1 admin + 4 hallway
        'Console Table'              => 1,
        'Telephone'                  => 1,
        'Water Dispenser (Fabriano)' => 2,   // 1 admin + 1 hallway
        'Sliding Wooden Cabinet'     => 1,
        'Aircon (Aux)'               => 1,
        'Water Dispenser'            => 4,   // 1 hallway + 3 classrooms (rooms 5,6,7)
        'Brown Cabinet'              => 1,
        'Wall Frame'                 => 2,
        'Sofa 1 Seater'              => 2,
        'Steel Bench (Black)'        => 5,
        'Guard Table'                => 1,
        'Cigarette Ashtray Bin'      => 1,
        'Fire Extinguisher'          => 4,   // hallway (adds to room total)
        'Training Tables'            => 0,
        'Colored Chairs'             => 75,  // 3 classrooms × 25
        'Monitor'                    => 7,   // classrooms 1-7
        'Aircon'                     => 7,   // classrooms 1-4, 6-8 (room 5 no aircon in data)
    ];

    // ── CDC locations ──────────────────────────────────────────────────────────
    private const CDC_LOCATIONS = [
        [
            'name'  => 'CDC 2nd Floor',
            'floor' => '2nd Floor',
            'rooms' => ['1','2','3','4','5','6','7','8','9','10',
                        '11','12','13','14','15','16','17','18','19','20',
                        '21','22','23'],
        ],
        [
            'name'  => 'CDC Ground Floor',
            'floor' => 'Ground Floor',
            'rooms' => ['CDC Admin Office', 'HALLWAY & LOBBY'],
        ],
        [
            'name'  => 'Classrooms',
            'floor' => null,
            'rooms' => ['1','2','3','4','5','6','7','8'],
        ],
    ];

    public function run(): void
    {
        // ── 1. Categories ─────────────────────────────────────────────────────
        $catDefs = [
            'Furniture & Fixtures'     => 'Beds, closets, tables, chairs, curtains, and general room furniture',
            'Fixtures & Lighting'      => 'Lamps, lampshades, and fixed lighting fixtures',
            'Electronics & Appliances' => 'Air conditioners, fridges, TVs, remotes, and electronic appliances',
            'Safety Equipment'         => 'Fire extinguishers and safety gear',
        ];

        $categoryMap = [];
        foreach ($catDefs as $name => $desc) {
            $categoryMap[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        // ── 2. Units ──────────────────────────────────────────────────────────
        $unitPiece = Unit::firstOrCreate(['abbreviation' => 'pcs'], ['name' => 'Piece']);

        // ── 3. Item definitions ───────────────────────────────────────────────
        $itemList = [];
        foreach (self::ITEMS as $def) {
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'category_id'     => $categoryMap[$def['cat']]->id,
                    'unit_id'         => $unitPiece->id,
                    'item_type'       => 'fixed_asset',
                    'description'     => "CDC room {$def['name']}",
                    'min_stock_level' => 0,
                ],
            );
            $itemList[] = $item;
        }

        // ── 4. CDC locations + rooms ───────────────────────────────────────────
        $allRoomIds = [];
        foreach (self::CDC_LOCATIONS as $locDef) {
            $location = RoomLocation::firstOrCreate(
                ['name' => $locDef['name']],
                [
                    'location_type' => 'cdc',
                    'floor'         => $locDef['floor'],
                ],
            );
            foreach ($locDef['rooms'] as $roomNum) {
                $room         = Room::firstOrCreate(['room_number' => $roomNum, 'room_location_id' => $location->id]);
                $allRoomIds[] = $room->id;
            }
        }

        // ── 5. Parse Excel for main room section totals ───────────────────────
        // Only process rows with integer room numbers 1-23 (CDC 2nd floor rooms).
        // Cols 1-22 map to items[0..21] via itemIndex = colIdx - 1.
        $totals   = array_fill(0, count(self::ITEMS), 0); // indexed by position in ITEMS
        $filePath = storage_path('app/public/references/CDC FURNITURE INVENTORY 2026 2.xlsx');

        if (file_exists($filePath)) {
            $rows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, false, false);

            foreach (array_slice($rows, 3) as $row) {
                $colA = trim((string) ($row[0] ?? ''));

                if ($colA === '' || ! $this->isMainRoomRow($colA)) {
                    continue;
                }

                // Cols 1..22 map to items[0..21]
                for ($colIdx = 1; $colIdx <= 22; $colIdx++) {
                    $cellVal = trim((string) ($row[$colIdx] ?? ''));
                    if ($cellVal === '' || $cellVal === '0') {
                        continue;
                    }
                    if (! preg_match('/^(\d+)/', $cellVal, $m)) {
                        continue;
                    }
                    $qty = (int) $m[1];
                    if ($qty > 0) {
                        $totals[$colIdx - 1] += $qty;
                    }
                }
            }
        } else {
            $this->command->warn('  CdcRoomInventorySeeder: Reference file not found — stock totals set to 0.');
        }

        // ── 6. Add non-room section totals ────────────────────────────────────
        foreach (self::NON_ROOM_TOTALS as $itemName => $qty) {
            foreach ($itemList as $idx => $item) {
                if ($item->name === $itemName) {
                    $totals[$idx] += $qty;
                    break;
                }
            }
        }

        // ── 7. Clear room_furniture rows for all CDC rooms ────────────────────
        $deleted = RoomFurniture::whereIn('room_id', $allRoomIds)->delete();

        // ── 8. Upsert cdc_room_furniture_stock totals ─────────────────────────
        foreach ($itemList as $idx => $item) {
            CdcRoomFurnitureStock::updateOrCreate(
                ['item_id' => $item->id, 'sub_item_id' => null],
                ['total_quantity' => $totals[$idx]],
            );
        }

        $nonZero = count(array_filter($totals, fn($q) => $q > 0));
        $this->command->info("  CdcRoomInventorySeeder: {$nonZero} item types with stock. Matrix cleared ({$deleted} rows removed).");
    }

    /** Match only main room rows: integers 1-23 (CDC 2nd floor rooms). */
    private function isMainRoomRow(string $value): bool
    {
        return (bool) preg_match('/^\d+$/', $value) && (int) $value >= 1 && (int) $value <= 23;
    }
}
