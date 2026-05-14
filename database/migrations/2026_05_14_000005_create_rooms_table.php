<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 50);  // "1", "2", "ROOM A", "ROOM B"
            $table->foreignId('room_location_id')->constrained('room_locations')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_number', 'room_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
