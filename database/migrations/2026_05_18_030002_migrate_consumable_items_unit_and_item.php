<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Ensure every distinct unit string has a Unit record ────────────
        $unitStrings = DB::table('consumable_items')
            ->whereNotNull('unit')
            ->distinct()
            ->pluck('unit');

        foreach ($unitStrings as $str) {
            if (blank($str)) continue;
            DB::table('units')->insertOrIgnore([
                'name'         => $str,
                'abbreviation' => $str,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // ── 2. Populate unit_id on consumable_items ───────────────────────────
        $unitMap = DB::table('units')->pluck('id', 'abbreviation');

        DB::table('consumable_items')
            ->whereNotNull('unit')
            ->orderBy('id')
            ->each(function ($ci) use ($unitMap) {
                $unitId = $unitMap[$ci->unit] ?? null;
                if ($unitId) {
                    DB::table('consumable_items')
                        ->where('id', $ci->id)
                        ->update(['unit_id' => $unitId]);
                }
            });

        // ── 3. Ensure "Cleaning Supplies" category exists in categories ───────
        $cleaningCatId = DB::table('categories')->where('name', 'Cleaning Supplies')->value('id');

        if (! $cleaningCatId) {
            $cleaningCatId = DB::table('categories')->insertGetId([
                'name'       => 'Cleaning Supplies',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 4. Create items entries and populate item_id ──────────────────────
        DB::table('consumable_items')
            ->orderBy('id')
            ->each(function ($ci) use ($cleaningCatId) {
                if (! $ci->unit_id) return; // skip if unit couldn't be resolved

                $itemId = DB::table('items')->insertGetId([
                    'name'        => $ci->name,
                    'category_id' => $cleaningCatId,
                    'unit_id'     => $ci->unit_id,
                    'item_type'   => 'consumable',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                DB::table('consumable_items')
                    ->where('id', $ci->id)
                    ->update(['item_id' => $itemId]);
            });

        // ── 5. Drop the old unit string column ────────────────────────────────
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        // Re-add the unit column and restore from items.name (rough restore)
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('name');
        });

        // Restore unit strings from units table
        DB::table('consumable_items')
            ->whereNotNull('unit_id')
            ->orderBy('id')
            ->each(function ($ci) {
                $abbr = DB::table('units')->where('id', $ci->unit_id)->value('abbreviation');
                DB::table('consumable_items')
                    ->where('id', $ci->id)
                    ->update(['unit' => $abbr]);
            });
    }
};
