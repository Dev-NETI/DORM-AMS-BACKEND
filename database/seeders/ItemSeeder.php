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
    }
}
