<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $pcs  = Unit::where('abbreviation', 'pcs')->first()->id;
        $kg   = Unit::where('abbreviation', 'kg')->first()->id;
        $L    = Unit::where('abbreviation', 'L')->first()->id;
        $set  = Unit::where('abbreviation', 'set')->first()->id;
        $ream = Unit::where('abbreviation', 'ream')->first()->id;
        $box  = Unit::where('abbreviation', 'box')->first()->id;
        $sack = Unit::where('abbreviation', 'sack')->first()->id;
        $btl  = Unit::where('abbreviation', 'btl')->first()->id;

        $dept = fn(string $code) => Department::where('code', $code)->first()?->id;

        // Resolve category by name AND department
        $cat = fn(string $catName, string $deptCode) => Category::where('name', $catName)
            ->where('department_id', $dept($deptCode))
            ->first()?->id;

        $items = [
            // ── FIXED ASSETS ──────────────────────────────────────────────

            // NOD – Laptops
            [
                'name'            => 'ASUS ROG Strix G15',
                'description'     => 'High-performance laptop for engineering and heavy workloads',
                'category_id'     => $cat('Laptops', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'ASUS',
                'model'           => 'ROG Strix G15 G513QM',
                'specifications'  => ['cpu' => 'AMD Ryzen 9 5900HX', 'gpu' => 'NVIDIA RTX 3070 8GB', 'ram' => '16GB DDR4', 'storage' => '512GB NVMe SSD', 'display' => '15.6" FHD 144Hz', 'os' => 'Windows 11 Pro'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Desktops
            [
                'name'            => 'Lenovo ThinkCentre M720',
                'description'     => 'Business-class tower desktop workstation',
                'category_id'     => $cat('Desktops', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Lenovo',
                'model'           => 'ThinkCentre M720 Tower',
                'specifications'  => ['cpu' => 'Intel Core i7-8700', 'ram' => '16GB DDR4', 'storage' => '1TB SSD', 'os' => 'Windows 11 Pro'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Tablets
            [
                'name'            => 'Apple iPad Pro 11"',
                'description'     => 'Tablet for mobile and field use with Cellular connectivity',
                'category_id'     => $cat('Tablets', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Apple',
                'model'           => 'iPad Pro 11" (4th Gen)',
                'specifications'  => ['chip' => 'Apple M2', 'storage' => '256GB', 'connectivity' => 'Wi-Fi + Cellular', 'display' => '11" Liquid Retina'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Printers
            [
                'name'            => 'HP LaserJet Pro M428fdn',
                'description'     => 'Multifunction monochrome laser printer with fax and auto-duplex',
                'category_id'     => $cat('Printers', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'HP',
                'model'           => 'LaserJet Pro M428fdn',
                'specifications'  => ['print_speed' => '38 ppm', 'functions' => 'Print, Copy, Scan, Fax', 'duplex' => 'Automatic', 'connectivity' => 'Ethernet, USB'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Monitors
            [
                'name'            => 'Dell UltraSharp U2722D',
                'description'     => '27-inch 4K IPS monitor with USB-C hub',
                'category_id'     => $cat('Monitors', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Dell',
                'model'           => 'UltraSharp U2722D',
                'specifications'  => ['size' => '27"', 'resolution' => '3840x2160 (4K UHD)', 'panel' => 'IPS', 'ports' => 'USB-C 90W, HDMI, DisplayPort'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Servers
            [
                'name'            => 'Dell PowerEdge R750',
                'description'     => '2U rack-mount server for applications and file services',
                'category_id'     => $cat('Servers', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Dell',
                'model'           => 'PowerEdge R750',
                'specifications'  => ['cpu' => '2x Intel Xeon Gold 6330', 'ram' => '128GB DDR4 ECC', 'storage' => '4x 2.4TB SAS 10K', 'raid' => 'PERC H755'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Switches
            [
                'name'            => 'Cisco Catalyst 2960-X 24-Port',
                'description'     => '24-port managed Gigabit PoE+ switch with 4 SFP uplinks',
                'category_id'     => $cat('Switches', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Cisco',
                'model'           => 'WS-C2960X-24PS-L',
                'specifications'  => ['ports' => '24x GbE PoE+', 'uplinks' => '4x SFP', 'poe_budget' => '370W', 'management' => 'Full managed'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Routers
            [
                'name'            => 'Cisco ISR 4331 Router',
                'description'     => 'Integrated services router for branch-office networking',
                'category_id'     => $cat('Routers', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Cisco',
                'model'           => 'ISR4331/K9',
                'specifications'  => ['throughput' => '100–300 Mbps', 'wan_ports' => '3', 'lan_ports' => '2x GbE', 'expansion' => '2x NIM slots'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – UPS / Power
            [
                'name'            => 'APC Smart-UPS SRT 1.5kVA',
                'description'     => '1.5kVA online double-conversion rack-mount UPS',
                'category_id'     => $cat('UPS / Power', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'APC',
                'model'           => 'Smart-UPS SRT1500RMXLI',
                'specifications'  => ['capacity' => '1500VA / 1350W', 'runtime' => '~9 min at full load', 'form_factor' => '2U Rack', 'type' => 'Online Double-conversion'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],
            // NOD – Access Points
            [
                'name'            => 'Aruba AP-515',
                'description'     => 'Wi-Fi 6 dual-radio indoor wireless access point with PoE+',
                'category_id'     => $cat('Access Points', 'NOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Aruba (HPE)',
                'model'           => 'AP-515 (JZ332A)',
                'specifications'  => ['standard' => '802.11ax (Wi-Fi 6)', 'radios' => 'Dual (2.4GHz + 5GHz)', 'max_speed' => '3.55 Gbps', 'poe' => '802.3at PoE+'],
                'min_stock_level' => 0,
                'department_id'   => $dept('NOD'),
            ],

            // GOD – Commercial Refrigerator
            [
                'name'            => 'Toshiba Inverter Refrigerator',
                'description'     => 'Commercial upright inverter refrigerator for food storage',
                'category_id'     => $cat('Food Storage', 'GOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Toshiba',
                'model'           => 'GR-A28 Inverter',
                'specifications'  => ['capacity' => '268L', 'cooling' => 'Inverter Compressor', 'configuration' => '2-door Top-freezer'],
                'min_stock_level' => 0,
                'department_id'   => $dept('GOD'),
            ],
            // GOD – Gas Cooking Range
            [
                'name'            => 'Modena Commercial Gas Range',
                'description'     => '6-burner commercial gas range with oven and auto-ignition',
                'category_id'     => $cat('Cooking Appliances', 'GOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Modena',
                'model'           => 'CS 6640 EX',
                'specifications'  => ['burners' => '6 cast-iron', 'ignition' => 'Auto-ignition', 'oven' => 'Yes, electric', 'grate' => 'Heavy-duty cast iron'],
                'min_stock_level' => 0,
                'department_id'   => $dept('GOD'),
            ],
            // GOD – Cookware Set
            [
                'name'            => 'Meyer Accolade Cookware Set',
                'description'     => '14-piece tri-ply stainless steel commercial cookware set',
                'category_id'     => $cat('Cookware & Utensils', 'GOD'),
                'unit_id'         => $set,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Meyer',
                'model'           => 'Accolade Series 14-Piece',
                'specifications'  => ['pieces' => 14, 'material' => 'Tri-ply stainless steel', 'induction_ready' => true],
                'min_stock_level' => 0,
                'department_id'   => $dept('GOD'),
            ],

            // HRAD – Office Desk
            [
                'name'            => 'Executive Office Desk 1500mm',
                'description'     => '1500mm executive desk with 3-drawer pedestal',
                'category_id'     => $cat('Furniture', 'HRAD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => null,
                'model'           => 'Executive Series 1500mm',
                'specifications'  => ['dimensions' => '150x75x75 cm', 'material' => 'Engineered wood, melamine finish', 'drawers' => '3-drawer pedestal'],
                'min_stock_level' => 0,
                'department_id'   => $dept('HRAD'),
            ],
            // HRAD – Office Chair
            [
                'name'            => 'SIDIZ T50 Ergonomic Chair',
                'description'     => 'High-back ergonomic mesh office chair with 4D armrests and lumbar support',
                'category_id'     => $cat('Furniture', 'HRAD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'SIDIZ',
                'model'           => 'T50 HLDA',
                'specifications'  => ['type' => 'High-back mesh', 'lumbar' => 'Adjustable', 'armrests' => '4D adjustable', 'seat_depth' => 'Adjustable'],
                'min_stock_level' => 0,
                'department_id'   => $dept('HRAD'),
            ],

            // DOD – Air Conditioning Unit
            [
                'name'            => 'Carrier Aura Inverter Split-Type AC',
                'description'     => '1.5HP inverter split-type air conditioner for dormitory rooms',
                'category_id'     => $cat('Building Equipment', 'DOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'fixed_asset',
                'brand'           => 'Carrier',
                'model'           => 'Aura Series 53QHG012N8',
                'specifications'  => ['capacity' => '1.5HP', 'type' => 'Inverter Split-type', 'refrigerant' => 'R32', 'coverage' => '15–20 sqm'],
                'min_stock_level' => 0,
                'department_id'   => $dept('DOD'),
            ],

            // ── CONSUMABLES ───────────────────────────────────────────────

            // BOD – Clothing
            [
                'name'            => 'Staff T-Shirt',
                'description'     => 'Company-branded round-neck staff t-shirt',
                'category_id'     => $cat('T-Shirts', 'BOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => ['material' => '100% Combed Cotton 180gsm', 'sizes' => 'XS–3XL'],
                'min_stock_level' => 20,
                'department_id'   => $dept('BOD'),
            ],
            [
                'name'            => 'Staff Polo Shirt',
                'description'     => 'Company-branded polo shirt with embroidered logo',
                'category_id'     => $cat('Polo Shirts', 'BOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => ['material' => 'Polycotton 65/35 blend', 'sizes' => 'XS–3XL'],
                'min_stock_level' => 15,
                'department_id'   => $dept('BOD'),
            ],
            [
                'name'            => 'Dickies 874 Work Pants',
                'description'     => 'Durable straight-fit work trousers for field staff',
                'category_id'     => $cat('Work Pants', 'BOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'consumable',
                'brand'           => 'Dickies',
                'model'           => '874 Original Work Pant',
                'specifications'  => ['material' => '65% Polyester / 35% Cotton', 'sizes' => '28–44'],
                'min_stock_level' => 10,
                'department_id'   => $dept('BOD'),
            ],

            // GOD – Food & Consumables
            [
                'name'            => 'Chicken Breast',
                'description'     => 'Fresh boneless skinless chicken breast, per kilogram',
                'category_id'     => $cat('Meat & Poultry', 'GOD'),
                'unit_id'         => $kg,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => null,
                'min_stock_level' => 10,
                'department_id'   => $dept('GOD'),
            ],
            [
                'name'            => 'Pork Liempo (Belly)',
                'description'     => 'Fresh pork belly, per kilogram',
                'category_id'     => $cat('Meat & Poultry', 'GOD'),
                'unit_id'         => $kg,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => null,
                'min_stock_level' => 10,
                'department_id'   => $dept('GOD'),
            ],
            [
                'name'            => 'Tilapia Fillet',
                'description'     => 'Fresh tilapia fish fillet, per kilogram',
                'category_id'     => $cat('Seafood', 'GOD'),
                'unit_id'         => $kg,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => null,
                'min_stock_level' => 8,
                'department_id'   => $dept('GOD'),
            ],
            [
                'name'            => 'Sinandomeng White Rice',
                'description'     => 'Premium Sinandomeng variety white rice, 50kg sack',
                'category_id'     => $cat('Dry Goods', 'GOD'),
                'unit_id'         => $sack,
                'item_type'       => 'consumable',
                'brand'           => 'Sinandomeng',
                'model'           => null,
                'specifications'  => ['weight_per_sack' => '50kg', 'variety' => 'Sinandomeng'],
                'min_stock_level' => 5,
                'department_id'   => $dept('GOD'),
            ],
            [
                'name'            => 'Golden Fiesta Cooking Oil',
                'description'     => 'Refined palm cooking oil, per liter',
                'category_id'     => $cat('Condiments & Spices', 'GOD'),
                'unit_id'         => $L,
                'item_type'       => 'consumable',
                'brand'           => 'Golden Fiesta',
                'model'           => null,
                'specifications'  => ['type' => 'Refined palm oil', 'volume' => '1L'],
                'min_stock_level' => 10,
                'department_id'   => $dept('GOD'),
            ],
            [
                'name'            => 'Joy Antibacterial Dishwashing Liquid',
                'description'     => 'Antibacterial dishwashing liquid for commercial kitchen use',
                'category_id'     => $cat('Cleaning Supplies', 'GOD'),
                'unit_id'         => $btl,
                'item_type'       => 'consumable',
                'brand'           => 'Joy',
                'model'           => 'Antibacterial Lemon',
                'specifications'  => ['volume' => '1L', 'variant' => 'Antibacterial'],
                'min_stock_level' => 5,
                'department_id'   => $dept('GOD'),
            ],

            // DOD – Bedding & Linen
            [
                'name'            => 'Single Fleece Blanket',
                'description'     => 'Single-size fleece sleeping blanket for dormitory use',
                'category_id'     => $cat('Blankets', 'DOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => ['size' => '150x200 cm', 'material' => 'Fleece', 'weight' => '1.2kg'],
                'min_stock_level' => 30,
                'department_id'   => $dept('DOD'),
            ],
            [
                'name'            => 'Standard Foam Pillow',
                'description'     => 'Standard single sleeping pillow for dormitory use',
                'category_id'     => $cat('Pillows', 'DOD'),
                'unit_id'         => $pcs,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => ['fill' => 'High-density foam fiber', 'size' => '50x70 cm'],
                'min_stock_level' => 30,
                'department_id'   => $dept('DOD'),
            ],
            [
                'name'            => 'Single Bed Sheet Set',
                'description'     => 'Single-bed sheet set with pillowcase — microfiber cotton',
                'category_id'     => $cat('Bed Sheets', 'DOD'),
                'unit_id'         => $set,
                'item_type'       => 'consumable',
                'brand'           => null,
                'model'           => null,
                'specifications'  => ['includes' => '1 flat sheet, 1 fitted sheet, 1 pillow case', 'material' => '100% Microfiber Cotton', 'thread_count' => '300TC'],
                'min_stock_level' => 20,
                'department_id'   => $dept('DOD'),
            ],

            // PRPD – Office Supplies
            [
                'name'            => 'Navigator Bond Paper A4',
                'description'     => 'A4 80gsm premium bond paper, per ream of 500 sheets',
                'category_id'     => $cat('Paper & Forms', 'PRPD'),
                'unit_id'         => $ream,
                'item_type'       => 'consumable',
                'brand'           => 'Navigator',
                'model'           => 'Navigator One A4 80gsm',
                'specifications'  => ['size' => 'A4 (210x297mm)', 'gsm' => '80', 'sheets_per_ream' => 500, 'brightness' => '146 CIE'],
                'min_stock_level' => 5,
                'department_id'   => $dept('PRPD'),
            ],
            [
                'name'            => 'Pilot BP-S Fine Ballpen',
                'description'     => 'Black fine-tip ballpen, sold per box of 12',
                'category_id'     => $cat('Writing Materials', 'PRPD'),
                'unit_id'         => $box,
                'item_type'       => 'consumable',
                'brand'           => 'Pilot',
                'model'           => 'BP-S Fine',
                'specifications'  => ['color' => 'Black', 'tip' => 'Fine 0.7mm', 'per_box' => 12],
                'min_stock_level' => 2,
                'department_id'   => $dept('PRPD'),
            ],
            [
                'name'            => 'Epson 003 Black Ink Bottle',
                'description'     => 'Genuine Epson 003 black ink bottle for EcoTank printers',
                'category_id'     => $cat('Ink & Toner', 'PRPD'),
                'unit_id'         => $btl,
                'item_type'       => 'consumable',
                'brand'           => 'Epson',
                'model'           => '003 Black (C13T00V198)',
                'specifications'  => ['color' => 'Black', 'volume' => '65ml', 'compatible' => 'L3110, L3210, L3250, L5290'],
                'min_stock_level' => 3,
                'department_id'   => $dept('PRPD'),
            ],
        ];

        foreach ($items as $itemData) {
            if ($itemData['category_id'] && $itemData['department_id']) {
                Item::firstOrCreate(
                    ['name' => $itemData['name'], 'department_id' => $itemData['department_id']],
                    $itemData
                );
            }
        }
    }
}
