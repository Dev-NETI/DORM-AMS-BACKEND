<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdc_room_furniture_item_logs', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('action_type', 30); // 'created' | 'deleted' | 'purchased'
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdc_room_furniture_item_logs');
    }
};
