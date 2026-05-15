<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_purchases', function (Blueprint $table) {
            $table->foreignId('sub_item_id')
                ->nullable()
                ->after('item_id')
                ->constrained('room_furniture_item_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_item_id');
        });
    }
};
