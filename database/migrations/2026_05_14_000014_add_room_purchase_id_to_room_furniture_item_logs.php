<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_furniture_item_logs', function (Blueprint $table) {
            $table->foreignId('room_purchase_id')
                  ->nullable()
                  ->after('notes')
                  ->constrained('room_purchases')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_furniture_item_logs', function (Blueprint $table) {
            $table->dropForeign(['room_purchase_id']);
            $table->dropColumn('room_purchase_id');
        });
    }
};
