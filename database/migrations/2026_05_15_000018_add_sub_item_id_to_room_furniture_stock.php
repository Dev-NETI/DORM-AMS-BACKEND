<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add a plain index so the FK is still backed after the unique is dropped
        Schema::table('room_furniture_stock', function (Blueprint $table) {
            $table->index('item_id', 'rfs_item_id_idx');
        });

        // Step 2: Drop the single-column unique
        Schema::table('room_furniture_stock', function (Blueprint $table) {
            $table->dropUnique('room_furniture_stock_item_id_unique');
        });

        // Step 3: Add nullable sub_item_id FK
        Schema::table('room_furniture_stock', function (Blueprint $table) {
            $table->foreignId('sub_item_id')
                ->nullable()
                ->after('item_id')
                ->constrained('room_furniture_item_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_furniture_stock', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_item_id');
        });

        Schema::table('room_furniture_stock', function (Blueprint $table) {
            $table->dropIndex('rfs_item_id_idx');
            $table->unique('item_id');
        });
    }
};
