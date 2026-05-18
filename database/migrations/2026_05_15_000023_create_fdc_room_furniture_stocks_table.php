<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fdc_room_furniture_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('sub_item_id')->nullable()->constrained('room_furniture_item_variants')->nullOnDelete();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'sub_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fdc_room_furniture_stocks');
    }
};
