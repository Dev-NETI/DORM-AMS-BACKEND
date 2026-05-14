<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location_type')->nullable(); // e.g., Senior Officers Room, Junior Officers Room, Gym, etc.
            $table->string('floor')->nullable();         // e.g., 1st Floor, 2nd Floor, Ground Floor
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_locations');
    }
};
