<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_furniture_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('name'); // brand/model name, e.g. "AUX SPLIT TYPE", "LG", "STIEBEL ELTRON"
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furniture_item_variants');
    }
};
