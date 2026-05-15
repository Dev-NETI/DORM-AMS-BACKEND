<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the model column only if it exists (migration 000015 may not have applied it)
        if (Schema::hasColumn('room_furniture', 'model')) {
            Schema::table('room_furniture', function (Blueprint $table) {
                $table->dropColumn('model');
            });
        }

        // MySQL requires at least one single-column index on the FK columns
        // before it will allow dropping the composite unique that was supporting them.
        // Add plain indexes first, then drop the unique.
        Schema::table('room_furniture', function (Blueprint $table) {
            $table->index('room_id',  'rf_room_id_idx');
            $table->index('item_id',  'rf_item_id_idx');
        });

        Schema::table('room_furniture', function (Blueprint $table) {
            // Drop the simple (room_id, item_id) unique — variant items will have
            // multiple records per (room_id, item_id), one per sub-item.
            $table->dropUnique(['room_id', 'item_id']);

            // Add FK to the new variants table (null = item has no sub-items)
            $table->foreignId('sub_item_id')
                  ->nullable()
                  ->after('item_id')
                  ->constrained('room_furniture_item_variants')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_furniture', function (Blueprint $table) {
            $table->dropForeign(['sub_item_id']);
            $table->dropColumn('sub_item_id');
            $table->string('model')->nullable()->after('quantity');
            $table->unique(['room_id', 'item_id']);
        });
    }
};
