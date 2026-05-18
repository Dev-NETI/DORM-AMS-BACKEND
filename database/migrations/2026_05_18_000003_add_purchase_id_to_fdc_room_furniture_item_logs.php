<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fdc_room_furniture_item_logs', function (Blueprint $table) {
            $table->foreignId('fdc_room_purchase_id')
                ->nullable()
                ->after('notes')
                ->constrained('fdc_room_purchases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fdc_room_furniture_item_logs', function (Blueprint $table) {
            $table->dropForeign(['fdc_room_purchase_id']);
            $table->dropColumn('fdc_room_purchase_id');
        });
    }
};
