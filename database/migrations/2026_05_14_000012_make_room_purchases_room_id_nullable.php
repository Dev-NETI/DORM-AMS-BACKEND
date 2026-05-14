<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_purchases', function (Blueprint $table) {
            // Allow purchases that go to storage (no room assigned yet)
            $table->foreignId('room_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('room_purchases', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable(false)->change();
        });
    }
};
