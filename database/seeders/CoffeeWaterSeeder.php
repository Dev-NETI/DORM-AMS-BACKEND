<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ConsumableCategory;
use App\Models\ConsumableItem;
use App\Models\ConsumableStock;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class CoffeeWaterSeeder extends Seeder
{
    public function run(): void
    {
        // General category for mirror items
        $generalCatId = Category::firstOrCreate(
            ['name' => 'Coffee & Bottled Water'],
            ['name' => 'Coffee & Bottled Water'],
        )->id;

        // NGH Coffee & Bottled Water consumable category
        $nghCat = ConsumableCategory::firstOrCreate(
            ['name' => 'NGH Coffee & Bottled Water'],
            [
                'description' => 'Coffee and bottled water inventory for NGH (New Guest House)',
                'module'      => 'coffee_water',
            ]
        );

        // Ensure module is set correctly in case record already existed without it
        if ($nghCat->module !== 'coffee_water') {
            $nghCat->update(['module' => 'coffee_water']);
        }

        $items = [
            ['name' => 'Coffee',        'unit' => 'pcs'],
            ['name' => 'Bottled Water', 'unit' => 'pcs'],
        ];

        foreach ($items as $item) {
            $unit = Unit::firstOrCreate(
                ['abbreviation' => $item['unit']],
                ['name'         => $item['unit']],
            );

            $existing = ConsumableItem::where('consumable_category_id', $nghCat->id)
                ->where('name', $item['name'])
                ->first();

            if ($existing) {
                continue;
            }

            $mirrorItem = Item::create([
                'name'        => $item['name'],
                'category_id' => $generalCatId,
                'unit_id'     => $unit->id,
                'item_type'   => 'consumable',
            ]);

            $consumableItem = ConsumableItem::create([
                'consumable_category_id' => $nghCat->id,
                'name'                   => $item['name'],
                'unit_id'                => $unit->id,
                'item_id'                => $mirrorItem->id,
                'is_active'              => true,
            ]);

            ConsumableStock::firstOrCreate(
                ['consumable_item_id' => $consumableItem->id],
                ['quantity' => 0]
            );
        }
    }
}
