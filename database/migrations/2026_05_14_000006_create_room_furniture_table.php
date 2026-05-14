<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantity-based inventory: how many of each item type is currently in each room
        Schema::create('room_furniture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furniture');
    }
};
