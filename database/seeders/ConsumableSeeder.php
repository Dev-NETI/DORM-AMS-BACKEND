<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ConsumableCategory;
use App\Models\ConsumableItem;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ConsumableSeeder extends Seeder
{
    public function run(): void
    {
        $cleaningCatId = Category::firstOrCreate(
            ['name' => 'Cleaning Supplies'],
            ['name' => 'Cleaning Supplies'],
        )->id;

        // ── NDB Cleaning Supplies ─────────────────────────────────────────────
        $ndb = ConsumableCategory::firstOrCreate(
            ['name' => 'NDB Cleaning Supplies'],
            ['description' => 'Cleaning materials for NDB (National Dormitory Building)'],
        );

        $ndbItems = [
            ['name' => '2 In 1 Yellow Sponge',                    'unit' => 'pcs'],
            ['name' => 'Acid Rain Remover',                        'unit' => 'gal'],
            ['name' => 'All Purpose Cleaner',                      'unit' => 'cby'],
            ['name' => 'Chlorine Bleach & Sanitizer',              'unit' => 'cby'],
            ['name' => 'Declogger',                                'unit' => 'gal'],
            ['name' => 'Fabric Conditioner',                       'unit' => 'cby'],
            ['name' => 'Floor Finished Sealer',                    'unit' => 'gal'],
            ['name' => 'Floor Finished Stripper',                  'unit' => 'gal'],
            ['name' => 'Floor Finished Wax',                       'unit' => 'gal'],
            ['name' => 'Floor Squeegee',                           'unit' => 'pcs'],
            ['name' => 'Garbage Bag (Black) XXL',                  'unit' => 'Pack / 100'],
            ['name' => 'Garbage Bag (Black) Med.',                 'unit' => 'Pack / 100'],
            ['name' => 'Garbage Bag (Clear) Med.',                 'unit' => 'Pack / 100'],
            ['name' => 'Garbage Bag (Clear) Small',                'unit' => 'Pack / 100'],
            ['name' => 'Laundry Plastic for Linen (Clear) XXL',   'unit' => 'Pack / 100'],
            ['name' => 'Hand Soap',                                'unit' => '3.8 L'],
            ['name' => 'L-fold Towel (30pcs /Box)',               'unit' => 'box'],
            ['name' => 'Liquid Laundry Detergent',                 'unit' => 'cby'],
            ['name' => 'Metered Aerosol Air Freshener',            'unit' => 'can'],
            ['name' => 'Microfiber Wipes (Chamois) 3M',           'unit' => 'pcs'],
            ['name' => 'Mop Bucket Squeezer',                      'unit' => 'pcs'],
            ['name' => 'Mop Head',                                 'unit' => 'pcs'],
            ['name' => 'Mop Head w/ Handle',                       'unit' => 'sets'],
            ['name' => 'Push Brush',                               'unit' => 'pcs'],
            ['name' => 'Round Rugs White',                         'unit' => 'bld'],
            ['name' => 'Rubber Hand Gloves',                       'unit' => 'pair/s'],
            ['name' => 'Scented Finishing Spray',                  'unit' => 'cont'],
            ['name' => 'Scrubbing Pad Green',                      'unit' => 'pcs'],
            ['name' => 'Soft Broom',                               'unit' => 'pcs'],
            ['name' => 'Spray Bottle',                             'unit' => 'pcs'],
            ['name' => 'Tissue Roll',                              'unit' => '96 roll/case'],
            ['name' => 'Toilet Bowl Cleaner',                      'unit' => 'cby'],
            ['name' => 'Yellow Caution Sign (Wet Floor Caution)', 'unit' => 'pcs'],
            ['name' => 'Amenity Kits',                             'unit' => 'box'],
            ['name' => 'Buffing Pad (Red) 16"',                   'unit' => 'pad'],
            ['name' => 'Stripping Pad (Black) 16"',               'unit' => 'pad'],
            ['name' => 'Buffing Pad (White) 16"',                  'unit' => 'pad'],
            ['name' => 'Stick Broom',                              'unit' => 'pcs'],
            ['name' => 'Ceiling Broom',                            'unit' => 'pcs'],
            ['name' => 'Hand Brush',                               'unit' => 'pcs'],
            ['name' => 'Window Squeegee',                          'unit' => 'sets'],
        ];

        $this->seedItems($ndb->id, $ndbItems, $cleaningCatId);

        // ── NGH Cleaning Supplies ─────────────────────────────────────────────
        $ngh = ConsumableCategory::firstOrCreate(
            ['name' => 'NGH Cleaning Supplies'],
            ['description' => 'Cleaning materials for NGH (New Guest House)'],
        );

        $nghItems = [
            ['name' => '2 In 1 Yellow Sponge',                         'unit' => 'pcs'],
            ['name' => 'Acid Rain Remover',                             'unit' => 'gal'],
            ['name' => 'All Purpose Cleaner',                           'unit' => 'cby'],
            ['name' => 'Chlorine Bleach & Sanitizer',                   'unit' => 'cby'],
            ['name' => 'Declogger',                                     'unit' => 'gal'],
            ['name' => 'Floor Finished Sealer',                         'unit' => 'cont'],
            ['name' => 'Floor Finished Stripper',                       'unit' => 'cont'],
            ['name' => 'Floor Finished Wax',                            'unit' => 'cont'],
            ['name' => 'Floor Polisher Brush w/ Bracket 16"',          'unit' => 'pcs'],
            ['name' => 'Floor Polisher Pad Holder w/ Bracket 16"',     'unit' => 'pcs'],
            ['name' => 'Floor Polisher Red Buffer 16"',                 'unit' => 'pcs'],
            ['name' => 'Floor Polisher Stripping Pad 16" Black',       'unit' => 'pcs'],
            ['name' => 'Linen Plastic XXL',                             'unit' => 'pcs'],
            ['name' => 'Garbage Bag (Black) XXL',                       'unit' => 'Pack / 100'],
            ['name' => 'Garbage Bag (Clear) Med.',                      'unit' => 'Pack / 100'],
            ['name' => 'Hand Soap',                                     'unit' => 'cby'],
            ['name' => 'Laundry Fabric Conditioner',                    'unit' => 'cby'],
            ['name' => 'Laundry Liquid Detergent',                      'unit' => 'cby'],
            ['name' => 'Laundry Scented Finishing Spray',               'unit' => 'cont'],
            ['name' => 'L-fold Towel (30pcs /Box)',                    'unit' => 'box'],
            ['name' => 'Metered Aerosol Air Freshener',                 'unit' => 'can'],
            ['name' => 'Microfiber Cloth',                              'unit' => 'pcs'],
            ['name' => 'Mop Bucket Squeezer',                           'unit' => 'pcs'],
            ['name' => 'Mop Head',                                      'unit' => 'pcs'],
            ['name' => 'Mop Head w/ Handle',                            'unit' => 'sets'],
            ['name' => 'Push Brush',                                    'unit' => 'pcs'],
            ['name' => 'Round Rugs White',                              'unit' => 'bdl'],
            ['name' => 'Rubber Hand Gloves',                            'unit' => 'pair/s'],
            ['name' => 'Scrubbing Pad Green',                           'unit' => 'pcs'],
            ['name' => 'Soft Broom',                                    'unit' => 'pcs'],
            ['name' => 'Spray Bottle',                                  'unit' => 'pcs'],
            ['name' => 'Tissue Roll',                                   'unit' => '96 roll/case'],
            ['name' => 'Toilet Bowl Cleaner',                           'unit' => 'cby'],
            ['name' => 'Hand Brush',                                    'unit' => 'pcs'],
            ['name' => 'Window Squeegee w/ Foam',                       'unit' => 'pcs'],
            ['name' => 'Yellow Caution Sign (Wet Floor Caution)',      'unit' => 'pcs'],
            ['name' => 'Stick Broom',                                   'unit' => 'pcs'],
            ['name' => 'Ceiling Broom',                                 'unit' => 'pcs'],
            ['name' => 'Amenity Kits',                                  'unit' => 'box'],
        ];

        $this->seedItems($ngh->id, $nghItems, $cleaningCatId);
    }

    private function seedItems(int $categoryId, array $items, int $cleaningCatId): void
    {
        foreach ($items as $item) {
            $unit = Unit::firstOrCreate(
                ['abbreviation' => $item['unit']],
                ['name' => $item['unit']],
            );

            $existing = ConsumableItem::where('consumable_category_id', $categoryId)
                ->where('name', $item['name'])
                ->first();

            if ($existing) continue;

            $mirrorItem = Item::create([
                'name'        => $item['name'],
                'category_id' => $cleaningCatId,
                'unit_id'     => $unit->id,
                'item_type'   => 'consumable',
            ]);

            ConsumableItem::create([
                'consumable_category_id' => $categoryId,
                'name'                   => $item['name'],
                'unit_id'                => $unit->id,
                'item_id'                => $mirrorItem->id,
                'is_active'              => true,
            ]);
        }
    }
}
