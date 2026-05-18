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
    // ── CDC item list (unique items across all CDC sections) ───────────────────
    private const ITEMS = [
        ['name' => 'Single Bed with Mattress',     'cat' => 'Furniture & Fixtures'],
        ['name' => 'Bed Bunks',                    'cat' => 'Furniture & Fixtures'],
        ['name' => 'Closet',                       'cat' => 'Furniture & Fixtures'],
        ['name' => 'Cabinet for Fridge',           'cat' => 'Furniture & Fixtures'],
        ['name' => 'Clip Hangers',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Hangers',                      'cat' => 'Furniture & Fixtures'],
        ['name' => 'Mini Fridge',                  'cat' => 'Electronics & Appliances'],
        ['name' => 'Throw Pillow',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Pillows',                      'cat' => 'Furniture & Fixtures'],
        ['name' => 'TV Remote',                    'cat' => 'Electronics & Appliances'],
        ['name' => 'LED TV',                       'cat' => 'Electronics & Appliances'],
        ['name' => 'Study Chair',                  'cat' => 'Furniture & Fixtures'],
        ['name' => 'Study Table',                  'cat' => 'Furniture & Fixtures'],
        ['name' => 'Water Kettle',                 'cat' => 'Electronics & Appliances'],
        ['name' => 'Study Lamp',                   'cat' => 'Fixtures & Lighting'],
        ['name' => 'Water Heater',                 'cat' => 'Electronics & Appliances'],
        ['name' => 'Aircon',                       'cat' => 'Electronics & Appliances'],
        ['name' => 'Aircon Remote',                'cat' => 'Electronics & Appliances'],
        ['name' => 'Window Curtain',               'cat' => 'Furniture & Fixtures'],
        ['name' => 'Doormats',                     'cat' => 'Furniture & Fixtures'],
        ['name' => 'Trashbins',                    'cat' => 'Furniture & Fixtures'],
        ['name' => 'Fire Extinguisher',            'cat' => 'Safety Equipment'],
        ['name' => 'Office Table',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Office Chair',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 3 Seater',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 2 Seater',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Center Table',                 'cat' => 'Furniture & Fixtures'],
        ['name' => 'Stainless Cabinet',            'cat' => 'Furniture & Fixtures'],
        ['name' => 'Printer',                      'cat' => 'Electronics & Appliances'],
        ['name' => 'Side Table',                   'cat' => 'Furniture & Fixtures'],
        ['name' => 'Console Table',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Telephone',                    'cat' => 'Electronics & Appliances'],
        ['name' => 'Water Dispenser (Fabriano)',   'cat' => 'Electronics & Appliances'],
        ['name' => 'Sliding Wooden Cabinet',       'cat' => 'Furniture & Fixtures'],
        ['name' => 'Aircon (Aux)',                 'cat' => 'Electronics & Appliances'],
        ['name' => 'Water Dispenser',              'cat' => 'Electronics & Appliances'],
        ['name' => 'Brown Cabinet',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Wall Frame',                   'cat' => 'Furniture & Fixtures'],
        ['name' => 'Sofa 1 Seater',                'cat' => 'Furniture & Fixtures'],
        ['name' => 'Steel Bench (Black)',           'cat' => 'Furniture & Fixtures'],
        ['name' => 'Guard Table',                  'cat' => 'Furniture & Fixtures'],
        ['name' => 'Cigarette Ashtray Bin',        'cat' => 'Furniture & Fixtures'],
        ['name' => 'Training Tables',              'cat' => 'Furniture & Fixtures'],
        ['name' => 'Colored Chairs',               'cat' => 'Furniture & Fixtures'],
        ['name' => 'Monitor',                      'cat' => 'Electronics & Appliances'],
    ];

    // ── CDC locations and rooms ────────────────────────────────────────────────
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
        $unitPiece = Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => 'pcs']);

        // ── 3. Item definitions + CDC stock records ───────────────────────────
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
            CdcRoomFurnitureStock::firstOrCreate(
                ['item_id' => $item->id, 'sub_item_id' => null],
                ['total_quantity' => 0]
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
                $room         = Room::firstOrCreate(
                    ['room_number' => $roomNum, 'room_location_id' => $location->id],
                );
                $allRoomIds[] = $room->id;
            }
        }

        // ── 5. Read Excel quantities ──────────────────────────────────────────
        $filePath = storage_path('app/public/references/CDC FURNITURE INVENTORY 2026 2.xlsx');

        if (! file_exists($filePath)) {
            $this->command->warn('  CdcRoomInventorySeeder: Reference file not found: ' . $filePath);
            $this->command->warn('  CDC locations and rooms created; re-run with the file to populate quantities.');
            return;
        }

        $spreadsheet    = IOFactory::load($filePath);
        $rows           = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $totalFurniture = 0;

        // Build a room lookup: room_number@location_name → Room
        // We need to match rows from Excel to the right rooms
        foreach (array_slice($rows, 3) as $row) {
            $colA = trim((string) ($row[0] ?? ''));
            if ($colA === '') {
                continue;
            }

            // Try to match this row's room number against any CDC room
            $matchedRoom = Room::whereIn('id', $allRoomIds)
                ->where('room_number', $colA)
                ->first();

            if (! $matchedRoom) {
                continue;
            }

            // Scan columns 1..N for numeric quantities
            foreach ($row as $colIdx => $cellVal) {
                if ($colIdx === 0) {
                    continue; // skip room number column
                }
                $cellStr = trim((string) ($cellVal ?? ''));
                if ($cellStr === '' || $cellStr === '0') {
                    continue;
                }
                if (! preg_match('/^(\d+)/', $cellStr, $m)) {
                    continue;
                }
                $qty = (int) $m[1];
                if ($qty <= 0) {
                    continue;
                }

                // Map column index to item (col 1 = first item, col 2 = second item, etc.)
                $itemIndex = $colIdx - 1; // 0-based item index
                if (! isset($itemList[$itemIndex])) {
                    continue;
                }

                $item = $itemList[$itemIndex];
                RoomFurniture::updateOrCreate(
                    ['room_id' => $matchedRoom->id, 'item_id' => $item->id, 'sub_item_id' => null],
                    ['quantity' => $qty],
                );
                $totalFurniture++;
            }
        }

        // ── 6. Sync cdc_room_furniture_stocks totals ──────────────────────────
        $allRoomIdsCollection = collect($allRoomIds);
        foreach ($itemList as $item) {
            $deployed = RoomFurniture::where('item_id', $item->id)
                ->whereIn('room_id', $allRoomIdsCollection)
                ->sum('quantity');
            CdcRoomFurnitureStock::where('item_id', $item->id)
                ->whereNull('sub_item_id')
                ->update(['total_quantity' => $deployed]);
        }

        $locationCount = count(self::CDC_LOCATIONS);
        $roomCount     = count($allRoomIds);
        $this->command->info("  CdcRoomInventorySeeder: {$locationCount} CDC locations, {$roomCount} rooms, {$totalFurniture} furniture quantity rows seeded.");
    }
}
