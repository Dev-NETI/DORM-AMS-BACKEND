<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_furniture_disposed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disposed_history_id')
                ->constrained('room_furniture_disposed_history')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furniture_disposed_documents');
    }
};
