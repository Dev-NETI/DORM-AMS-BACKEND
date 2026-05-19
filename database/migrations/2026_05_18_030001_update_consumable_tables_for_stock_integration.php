<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add department_id to consumable_categories (for stock-tracking scope)
        Schema::table('consumable_categories', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('description')
                ->constrained('departments')
                ->nullOnDelete();
        });

        // Add unit_id and item_id FKs to consumable_items
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('name')
                ->constrained('units')
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['item_id', 'unit_id']);
        });

        Schema::table('consumable_categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
