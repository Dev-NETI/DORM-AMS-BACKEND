<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add min_stock_level to consumable_items
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->decimal('min_stock_level', 12, 2)->default(0)->after('is_active');
        });

        // Create consumable_stocks entries for any items that don't have one yet
        DB::statement("
            INSERT INTO consumable_stocks (consumable_item_id, quantity, created_at, updated_at)
            SELECT ci.id, 0, NOW(), NOW()
            FROM consumable_items ci
            LEFT JOIN consumable_stocks cs ON cs.consumable_item_id = ci.id
            WHERE cs.id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->dropColumn('min_stock_level');
        });
    }
};
