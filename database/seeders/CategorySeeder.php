<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $dept = fn (string $code) => Department::where('code', $code)->first()?->id;

        $categories = [
            // NOD — IT Hardware
            ['name' => 'Laptops',             'description' => 'Portable computers',                     'dept' => 'NOD'],
            ['name' => 'Desktops',            'description' => 'Desktop computers',                      'dept' => 'NOD'],
            ['name' => 'Tablets',             'description' => 'Tablet computers',                       'dept' => 'NOD'],
            ['name' => 'Printers',            'description' => 'Printers and scanners',                  'dept' => 'NOD'],
            ['name' => 'Monitors',            'description' => 'Computer monitors and displays',         'dept' => 'NOD'],
            ['name' => 'Servers',             'description' => 'Physical servers',                       'dept' => 'NOD'],
            ['name' => 'Switches',            'description' => 'Network switches',                       'dept' => 'NOD'],
            ['name' => 'Routers',             'description' => 'Network routers',                        'dept' => 'NOD'],
            ['name' => 'UPS / Power',         'description' => 'Uninterruptible power supplies',         'dept' => 'NOD'],
            ['name' => 'Access Points',       'description' => 'Wireless access points and repeaters',   'dept' => 'NOD'],
            // BOD — Clothing
            ['name' => 'T-Shirts',            'description' => 'Staff t-shirts',                         'dept' => 'BOD'],
            ['name' => 'Polo Shirts',         'description' => 'Polo shirts',                            'dept' => 'BOD'],
            ['name' => 'Work Pants',          'description' => 'Work trousers',                          'dept' => 'BOD'],
            // PRPD — Office Supplies
            ['name' => 'Paper & Forms',       'description' => 'Bond paper, forms',                      'dept' => 'PRPD'],
            ['name' => 'Writing Materials',   'description' => 'Pens, pencils, markers',                 'dept' => 'PRPD'],
            ['name' => 'Binders & Folders',   'description' => 'Folders, binders, clips',                'dept' => 'PRPD'],
            ['name' => 'Ink & Toner',         'description' => 'Printer ink cartridges and toner',       'dept' => 'PRPD'],
            // HRAD — Furniture & Medical
            ['name' => 'Furniture',           'description' => 'Tables, chairs, desks, and other furniture', 'dept' => 'HRAD'],
            ['name' => 'Medical & First Aid', 'description' => 'First aid supplies and basic medical items',  'dept' => 'HRAD'],
            // GOD — Kitchen & Food
            ['name' => 'Cooking Appliances',  'description' => 'Gas range, oven, deep fryer',            'dept' => 'GOD'],
            ['name' => 'Food Storage',        'description' => 'Refrigerators, freezers',                'dept' => 'GOD'],
            ['name' => 'Cookware & Utensils', 'description' => 'Pots, pans, knives, ladles',             'dept' => 'GOD'],
            ['name' => 'Meat & Poultry',      'description' => 'Beef, pork, chicken',                    'dept' => 'GOD'],
            ['name' => 'Seafood',             'description' => 'Fish, shrimp, squid',                    'dept' => 'GOD'],
            ['name' => 'Vegetables & Fruits', 'description' => 'Fresh produce',                          'dept' => 'GOD'],
            ['name' => 'Dry Goods',           'description' => 'Rice, flour, sugar, canned goods',       'dept' => 'GOD'],
            ['name' => 'Condiments & Spices', 'description' => 'Oil, soy sauce, spices',                 'dept' => 'GOD'],
            ['name' => 'Cleaning Supplies',   'description' => 'Detergents, disinfectants, cleaning tools', 'dept' => 'GOD'],
            // DOD — Bedding & Facilities
            ['name' => 'Blankets',            'description' => 'Sleeping blankets',                      'dept' => 'DOD'],
            ['name' => 'Pillows',             'description' => 'Sleeping pillows',                       'dept' => 'DOD'],
            ['name' => 'Bed Sheets',          'description' => 'Bed sheets and pillow cases',            'dept' => 'DOD'],
            ['name' => 'Towels',              'description' => 'Bath and hand towels',                   'dept' => 'DOD'],
            ['name' => 'Building Equipment',  'description' => 'Air conditioning, fixtures, and building assets', 'dept' => 'DOD'],
        ];

        foreach ($categories as $cat) {
            $deptId = $dept($cat['dept']);
            Category::firstOrCreate(
                ['name' => $cat['name'], 'department_id' => $deptId],
                [
                    'description'   => $cat['description'],
                    'department_id' => $deptId,
                ]
            );
        }
    }
}
