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
        ['name' => 'Single Bed with Mattress',   'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'SBD'],
        ['name' => 'Double Bed with Mattress',   'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'DBL'],
        ['name' => 'Bed Bunks',                  'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'BNK'],
        ['name' => 'Closet',                     'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'CLO'],
        ['name' => 'Hangers',                    'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'HNG'],
        ['name' => 'Study Table with Chair',     'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'STC'],
        ['name' => 'Study Lamp',                 'cat' => 'Fixtures & Lighting',       'unit' => 'pcs', 'code' => 'SLP'],
        ['name' => 'Night Table',                'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'NTB'],
        ['name' => 'Night Lampshade',            'cat' => 'Fixtures & Lighting',       'unit' => 'pcs', 'code' => 'NLS'],
        ['name' => 'AC Remote',                  'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'ACR'],
        ['name' => 'Shower Curtain',             'cat' => 'Bathroom & Fixtures',       'unit' => 'pcs', 'code' => 'SCT'],
        ['name' => 'Garbage Bin',                'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'GBN'],
        ['name' => 'Doormat',                    'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'DMT'],
        ['name' => 'Window Curtain Set',         'cat' => 'Furniture & Fixtures',      'unit' => 'set', 'code' => 'WCS'],
        ['name' => 'Mini Fridge',                'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'FRG'],
        ['name' => 'TV with Remote',             'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'TVR'],
        ['name' => 'Cable with Remote',          'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'CBR'],
    ];

    // ── Excel column index (0-based) → item code ──────────────────────────────
    private const COL_TO_CODE = [
        1  => 'SBD',  // B = Single Bed with Mattress
        2  => 'DBL',  // C = Double Bed with Mattress
        3  => 'BNK',  // D = Bed Bunks
        4  => 'CLO',  // E = Closet
        5  => 'HNG',  // F = Hangers
        6  => 'STC',  // G = Study Table with Chair
        7  => 'SLP',  // H = Study Lamp
        8  => 'NTB',  // I = Night Table
        9  => 'NLS',  // J = Night Lampshade
        10 => 'ACR',  // K = AC Remote
        11 => 'SCT',  // L = Shower Curtain
        12 => 'GBN',  // M = Garbage Bin
        13 => 'DMT',  // N = Doormat
        14 => 'WCS',  // O = Window Curtain Set
        15 => 'FRG',  // P = Mini Fridge
        16 => 'TVR',  // Q = TV with Remote
        17 => 'CBR',  // R = Cable with Remote
    ];

    // ── FDC: 1 location, rooms 1–52 ──────────────────────────────────────────
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
        $unitPiece = Unit::firstOrCreate(['abbreviation' => 'pcs'],  ['name' => 'Piece']);
        $unitSet   = Unit::firstOrCreate(['abbreviation' => 'set'],   ['name' => 'Set']);

        // ── 3. Item definitions + FDC stock records ───────────────────────────
        $itemByCode = [];
        foreach (self::ITEMS as $def) {
            $unit = $def['unit'] === 'set' ? $unitSet : $unitPiece;
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

        $roomMap = [];
        foreach (self::FDC_ROOMS as $roomNum) {
            $room              = Room::firstOrCreate(['room_number' => $roomNum, 'room_location_id' => $location->id]);
            $roomMap[$roomNum] = $room;
        }

        // ── 5. Parse Excel for per-room totals ────────────────────────────────
        $totals   = array_fill_keys(array_column(self::ITEMS, 'code'), 0);
        $filePath = storage_path('app/public/references/FDC FURNITURE INVENTORY 2026 1.xlsx');

        if (file_exists($filePath)) {
            $rows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, false, false);

            foreach (array_slice($rows, 3) as $row) {
                $colA = trim((string) ($row[0] ?? ''));

                if ($colA === '' || ! $this->isRoomRow($colA)) {
                    continue; // skip headers, TOTAL rows, hallway/gym/office sections
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
                    if ($qty > 0) {
                        $totals[$code] += $qty;
                    }
                }
            }
        } else {
            $this->command->warn('  FdcRoomInventorySeeder: Reference file not found — stock totals set to 0.');
        }

        // ── 6. Clear room_furniture rows for FDC rooms ────────────────────────
        $fdcRoomIds = collect($roomMap)->pluck('id');
        $deleted    = RoomFurniture::whereIn('room_id', $fdcRoomIds)->delete();

        // ── 7. Upsert fdc_room_furniture_stock totals ─────────────────────────
        foreach ($itemByCode as $code => $item) {
            FdcRoomFurnitureStock::updateOrCreate(
                ['item_id' => $item->id, 'sub_item_id' => null],
                ['total_quantity' => $totals[$code] ?? 0],
            );
        }

        $nonZero = collect($totals)->filter(fn($q) => $q > 0)->count();
        $this->command->info("  FdcRoomInventorySeeder: {$nonZero} item types with stock. Matrix cleared ({$deleted} rows removed).");
    }

    private function isRoomRow(string $value): bool
    {
        return (bool) preg_match('/^\d+$/', $value) && (int) $value >= 1 && (int) $value <= 52;
    }
}
