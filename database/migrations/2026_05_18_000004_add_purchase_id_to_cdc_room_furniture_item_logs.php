<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdc_room_furniture_item_logs', function (Blueprint $table) {
            $table->foreignId('cdc_room_purchase_id')
                ->nullable()
                ->after('notes')
                ->constrained('cdc_room_purchases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cdc_room_furniture_item_logs', function (Blueprint $table) {
            $table->dropForeign(['cdc_room_purchase_id']);
            $table->dropColumn('cdc_room_purchase_id');
        });
    }
};
