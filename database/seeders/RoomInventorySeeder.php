<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Models\RoomFurnitureItemVariant;
use App\Models\RoomFurnitureStock;
use App\Models\RoomLocation;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RoomInventorySeeder extends Seeder
{
    // ── Item definitions ───────────────────────────────────────────────────────
    private const ITEMS = [
        // Standard room items (matched to Excel columns via COL_TO_CODE)
        ['name' => 'Double Bed with Mattress',    'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'DBL'],
        ['name' => 'Single Bed with Mattress',    'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'SBD'],
        ['name' => 'Bed Bunks with Mattress',     'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'BNK'],
        ['name' => 'Closet',                      'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'CLO'],
        ['name' => 'Single Closet',               'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'SCL'],
        ['name' => 'Built-In Closet',             'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'BIC'],
        ['name' => 'Hangers',                     'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'HNG'],
        ['name' => 'Study Table with Chair',      'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'STC'],
        ['name' => 'Study Lamp',                  'cat' => 'Fixtures & Lighting',       'unit' => 'pcs', 'code' => 'SLP'],
        ['name' => 'Night Table',                 'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'NTB'],
        ['name' => 'Night Lampshade',             'cat' => 'Fixtures & Lighting',       'unit' => 'pcs', 'code' => 'NLS'],
        ['name' => 'AC Remote',                   'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'ACR'],
        ['name' => 'Shower Curtain',              'cat' => 'Bathroom & Fixtures',       'unit' => 'pcs', 'code' => 'SCT'],
        ['name' => 'Garbage Bin',                 'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'GBN'],
        ['name' => 'Doormat',                     'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'DMT'],
        ['name' => 'Window Curtain Set',          'cat' => 'Furniture & Fixtures',      'unit' => 'set', 'code' => 'WCS'],
        ['name' => 'Mini Fridge',                 'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'FRG'],
        ['name' => 'Aircon',                      'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'ACU'],
        ['name' => 'Shower Heater',               'cat' => 'Bathroom & Fixtures',       'unit' => 'pcs', 'code' => 'SHT'],
        // Dorm A&B specific
        ['name' => 'Bed Bunks',                   'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'BDS'],
        ['name' => 'Sofa',                        'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'SOF'],
        // Gym equipment
        ['name' => 'Treadmill',                   'cat' => 'Gym Equipment',             'unit' => 'pcs', 'code' => 'TRM'],
        ['name' => 'Stationary Bike',             'cat' => 'Gym Equipment',             'unit' => 'pcs', 'code' => 'STB'],
        ['name' => 'Bench Press',                 'cat' => 'Gym Equipment',             'unit' => 'pcs', 'code' => 'BNP'],
        ['name' => 'Dumbbells',                   'cat' => 'Gym Equipment',             'unit' => 'pair','code' => 'DUM'],
        ['name' => 'Kettlebells',                 'cat' => 'Gym Equipment',             'unit' => 'pcs', 'code' => 'KTB'],
        // Common area / hallway
        ['name' => 'Sofa 2 Seater',               'cat' => 'Furniture & Fixtures',      'unit' => 'pcs', 'code' => 'SF2'],
        ['name' => 'ATM Machine',                 'cat' => 'Electronics & Appliances',  'unit' => 'unit','code' => 'ATM'],
        ['name' => 'TV Monitor',                  'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'TVM'],
        ['name' => 'Water Dispenser',             'cat' => 'Electronics & Appliances',  'unit' => 'pcs', 'code' => 'WDP'],
        // Laundry room
        ['name' => 'Washing/Dryer',               'cat' => 'Laundry Equipment',         'unit' => 'unit','code' => 'WDR'],
        ['name' => 'Industrial Washing Machine',  'cat' => 'Laundry Equipment',         'unit' => 'unit','code' => 'IWM'],
        ['name' => 'Industrial Dryer',            'cat' => 'Laundry Equipment',         'unit' => 'unit','code' => 'IDR'],
    ];

    // ── Variants: item name → [variant name, ...]  ────────────────────────────
    private const VARIANTS = [
        'Mini Fridge'                => ['Everest', 'LG'],
        'Aircon'                     => ['AUX Split Type', 'Everest (Window Type)', 'Kolin (Window Type)', 'Samsung (Window Type)', 'Carrier (Window Type)'],
        'Shower Heater'              => ['Stiebel Eltron'],
        'Water Dispenser'            => ['Hanabishi'],
        'TV Monitor'                 => ['Changhong', 'Fabriano', 'Mitsutech'],
        'Treadmill'                  => ['Tobys'],
        'Stationary Bike'            => ['Tobys'],
        'Bench Press'                => ['Tobys'],
        'Dumbbells'                  => ['Tobys'],
        'Kettlebells'                => ['Tobys'],
        'ATM Machine'                => ['BDO'],
        'Washing/Dryer'              => ['Alliance'],
        'Industrial Dryer'           => ['Image', 'IPSO'],
        'Industrial Washing Machine' => ['IPSO'],
    ];

    // ── Excel column index (0-based) → item code (standard room sections) ─────
    private const COL_TO_CODE = [
        1  => 'DBL',  // B = Double Bed with Mattress
        3  => 'SBD',  // D = Single Bed with Mattress
        4  => 'BNK',  // E = Bed Bunks with Mattress
        5  => 'CLO',  // F = Closet
        6  => 'HNG',  // G = Hangers
        7  => 'STC',  // H = Study Table with Chair
        8  => 'SLP',  // I = Study Lamp
        9  => 'NTB',  // J = Night Table
        10 => 'NLS',  // K = Night Lampshade
        11 => 'ACR',  // L = AC Remote
        12 => 'SCT',  // M = Shower Curtain
        13 => 'GBN',  // N = Garbage Bin
        14 => 'DMT',  // O = Doormat
        15 => 'WCS',  // P = Window Curtain Set
        16 => 'FRG',  // Q = Mini Fridge
        17 => 'ACU',  // R = Aircon
        18 => 'SHT',  // S = Shower Heater
    ];

    // ── Dorm A&B col mapping (cols 1,4,5 have different item meanings) ─────────
    private const DORM_AB_COL_TO_CODE = [
        1  => 'BDS',  // B = Bed Bunks (plain, no mattress)
        4  => 'SCL',  // E = Single Closet
        5  => 'BIC',  // F = Built-In Closet
        // Cols 13,14,15,17 retain their standard meaning
        13 => 'GBN',
        14 => 'DMT',
        15 => 'WCS',
        17 => 'ACU',  // Aircon (brand parsed separately)
    ];

    // ── Aircon brand keyword → variant name ───────────────────────────────────
    private const AIRCON_BRANDS = [
        'AUX'     => 'AUX Split Type',
        'EVEREST' => 'Everest (Window Type)',
        'KOLIN'   => 'Kolin (Window Type)',
        'SAMSUNG' => 'Samsung (Window Type)',
        'CARRIER' => 'Carrier (Window Type)',
    ];

    // ── Mini Fridge brand keyword → variant name ──────────────────────────────
    private const MINIFRIDGE_BRANDS = [
        'EVEREST' => 'Everest',
        'LG'      => 'LG',
    ];

    // ── Shower Heater brand keyword → variant name ────────────────────────────
    private const HEATER_BRANDS = [
        'STIEBEL' => 'Stiebel Eltron',
    ];

    // ── Non-room hardcoded totals (gym, hallway, laundry) ─────────────────────
    // Derived directly from the NDB Excel reference file.
    private const EXTRA_TOTALS = [
        'TRM' => 2,   // Treadmill (TOBYS)
        'STB' => 4,   // Stationary Bike (TOBYS)
        'BNP' => 1,   // Bench Press (TOBYS)
        'DUM' => 4,   // Dumbbells 4 pairs (TOBYS)
        'KTB' => 5,   // Kettlebells (TOBYS)
        'WDP' => 2,   // Water Dispenser 1st floor (HANABISHI)
        'SF2' => 2,   // Sofa 2 Seater
        'ATM' => 1,   // ATM Machine (BDO)
        'TVM' => 5,   // TV Monitor: Changhong(1) + Fabriano(2) + Mitsutech(2)
        'WDR' => 1,   // Washing/Dryer (ALLIANCE)
        'IWM' => 1,   // Industrial Washing Machine (IPSO)
        'IDR' => 2,   // Industrial Dryer: Image(1) + IPSO(1)
    ];

    // ── Room locations ─────────────────────────────────────────────────────────
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

    public function run(): void
    {
        // ── 1. Categories ─────────────────────────────────────────────────────
        $catDefs = [
            'Furniture & Fixtures'     => 'Beds, closets, tables, chairs, curtains, doormats, and general room furniture',
            'Fixtures & Lighting'      => 'Lamps, lampshades, and fixed lighting fixtures',
            'Electronics & Appliances' => 'Air conditioners, mini fridges, remotes, and electronic appliances',
            'Bathroom & Fixtures'      => 'Shower curtains, shower heaters, and bathroom accessories',
            'Gym Equipment'            => 'Treadmills, stationary bikes, bench press, weights, and fitness equipment',
            'Laundry Equipment'        => 'Washing machines, dryers, and laundry appliances',
        ];

        $categoryMap = [];
        foreach ($catDefs as $name => $desc) {
            $categoryMap[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        // ── 2. Units ──────────────────────────────────────────────────────────
        $unitMap = [
            'pcs'  => Unit::firstOrCreate(['abbreviation' => 'pcs'],  ['name' => 'Piece']),
            'set'  => Unit::firstOrCreate(['abbreviation' => 'set'],   ['name' => 'Set']),
            'pair' => Unit::firstOrCreate(['abbreviation' => 'pair'],  ['name' => 'Pair']),
            'unit' => Unit::firstOrCreate(['abbreviation' => 'unit'],  ['name' => 'Unit']),
        ];

        // ── 3. Item definitions ───────────────────────────────────────────────
        $itemByCode = [];
        foreach (self::ITEMS as $def) {
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'category_id'     => $categoryMap[$def['cat']]->id,
                    'unit_id'         => $unitMap[$def['unit']]->id,
                    'item_type'       => 'fixed_asset',
                    'description'     => "NDB dormitory asset: {$def['name']}",
                    'min_stock_level' => 0,
                ],
            );
            $itemByCode[$def['code']] = $item;
        }

        // ── 4. Variants ───────────────────────────────────────────────────────
        $variantByNameMap = []; // [item_name][variant_name] => RoomFurnitureItemVariant
        foreach (self::VARIANTS as $itemName => $variantNames) {
            $item = collect($itemByCode)->first(fn($i) => $i->name === $itemName);
            if (! $item) {
                continue;
            }
            foreach ($variantNames as $vname) {
                $variant = RoomFurnitureItemVariant::firstOrCreate(
                    ['item_id' => $item->id, 'name' => $vname],
                );
                $variantByNameMap[$itemName][$vname] = $variant;
            }
        }

        // ── 5. Room locations + rooms ─────────────────────────────────────────
        foreach (self::LOCATIONS as $locDef) {
            $location = RoomLocation::firstOrCreate(
                ['name' => $locDef['name']],
                [
                    'location_type' => $locDef['type'],
                    'floor'         => $locDef['floor'],
                ],
            );
            foreach ($locDef['rooms'] as $roomNum) {
                Room::firstOrCreate(['room_number' => $roomNum, 'room_location_id' => $location->id]);
            }
        }

        // ── 6. Parse Excel for room-section totals ─────────────────────────────
        // Accumulate: code => total_qty, and brand => qty for variant items
        $totals         = array_fill_keys(array_column(self::ITEMS, 'code'), 0);
        $variantTotals  = []; // [item_name][variant_name] => qty

        $filePath = storage_path('app/public/references/NDB FURNITURE INVENTORY 2026 1.xlsx');

        if (file_exists($filePath)) {
            $rows      = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, false, false);
            $inDormAB  = false;

            foreach (array_slice($rows, 3) as $row) {
                $colA = trim((string) ($row[0] ?? ''));

                if ($colA === '') {
                    continue;
                }

                if (! $this->isDataRow($colA)) {
                    // Section header — detect Dorm A&B section
                    $inDormAB = str_contains(strtoupper($colA), 'DORM');
                    continue;
                }

                $colMap = $inDormAB ? self::DORM_AB_COL_TO_CODE : self::COL_TO_CODE;

                foreach ($colMap as $colIdx => $code) {
                    $cellVal = trim((string) ($row[$colIdx] ?? ''));
                    if ($cellVal === '' || $cellVal === '0') {
                        continue;
                    }

                    $qty = $this->parseQty($cellVal);
                    if ($qty <= 0) {
                        continue;
                    }

                    $totals[$code] = ($totals[$code] ?? 0) + $qty;
                }

                // Brand extraction for variant items (standard sections only)
                if (! $inDormAB) {
                    $this->accumulateBrand($row[16] ?? '', 'Mini Fridge',   self::MINIFRIDGE_BRANDS, $variantTotals);
                    $this->accumulateBrand($row[18] ?? '', 'Shower Heater', self::HEATER_BRANDS,     $variantTotals);
                }
                // Aircon brand extraction applies to both sections
                $this->accumulateBrand($row[17] ?? '', 'Aircon', self::AIRCON_BRANDS, $variantTotals);
            }
        } else {
            $this->command->warn('  RoomInventorySeeder: NDB reference file not found — stock totals set to 0.');
        }

        // ── 7. Add hardcoded gym/hallway/laundry totals ───────────────────────
        foreach (self::EXTRA_TOTALS as $code => $qty) {
            $totals[$code] = ($totals[$code] ?? 0) + $qty;
        }

        // ── 8. Clear existing room_furniture rows for NDB rooms ───────────────
        // This ensures deployed = 0 so the matrix is empty and deployed ≤ total.
        $ndbLocationIds = RoomLocation::whereNotIn('location_type', ['fdc', 'cdc'])->pluck('id');
        $ndbRoomIds     = Room::whereIn('room_location_id', $ndbLocationIds)->pluck('id');
        $deleted        = RoomFurniture::whereIn('room_id', $ndbRoomIds)->delete();

        // ── 9. Upsert room_furniture_stock totals ─────────────────────────────
        foreach ($itemByCode as $code => $item) {
            $total = $totals[$code] ?? 0;

            RoomFurnitureStock::updateOrCreate(
                ['item_id' => $item->id, 'sub_item_id' => null],
                ['total_quantity' => $total],
            );
        }

        // ── 10. Upsert variant stock records ──────────────────────────────────
        foreach ($variantTotals as $itemName => $brands) {
            foreach ($brands as $variantName => $qty) {
                $variant = $variantByNameMap[$itemName][$variantName] ?? null;
                if (! $variant) {
                    continue;
                }
                $item = collect($itemByCode)->first(fn($i) => $i->name === $itemName);
                if (! $item) {
                    continue;
                }
                RoomFurnitureStock::updateOrCreate(
                    ['item_id' => $item->id, 'sub_item_id' => $variant->id],
                    ['total_quantity' => $qty],
                );
            }
        }

        $totalItems   = collect($totals)->filter(fn($q) => $q > 0)->count();
        $totalVariant = collect($variantTotals)->flatMap(fn($b) => $b)->count();

        $this->command->info("  RoomInventorySeeder: {$totalItems} item types with stock, {$totalVariant} variant totals set. Matrix cleared ({$deleted} rows removed).");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isDataRow(string $value): bool
    {
        return (bool) preg_match('/^\d+[A-Za-z]?$/', $value)
            || (bool) preg_match('/^ROOM\s+[AB]$/i', $value);
    }

    /** Extract leading integer from a cell value like "1 (EVEREST)" or "2". */
    private function parseQty(string $val): int
    {
        if (preg_match('/^(\d+)/', $val, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Accumulate brand counts from cells like "1 (AUX SPLIT TYPE)" or "1 EVEREST(WINDOW TYPE)".
     *
     * @param  string                    $cellVal     Raw cell string
     * @param  string                    $itemName    Name of the parent item
     * @param  array<string, string>     $brandMap    KEYWORD => variant name
     * @param  array<string, array>      $totals      Reference accumulator [itemName][variantName] => qty
     */
    private function accumulateBrand(
        mixed $cellVal,
        string $itemName,
        array $brandMap,
        array &$totals
    ): void {
        $str = strtoupper(trim((string) $cellVal));
        if ($str === '' || $str === '0') {
            return;
        }

        $qty = $this->parseQty($str) ?: 1; // assume 1 if no leading number (e.g., "CARRIER(WINDOW TYPE)")

        foreach ($brandMap as $keyword => $variantName) {
            if (str_contains($str, $keyword)) {
                $totals[$itemName][$variantName] = ($totals[$itemName][$variantName] ?? 0) + $qty;

                return;
            }
        }
    }
}
