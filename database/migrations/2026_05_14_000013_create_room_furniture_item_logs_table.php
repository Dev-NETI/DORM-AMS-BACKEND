<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_furniture_item_logs', function (Blueprint $table) {
            $table->id();
            // Store the name so the log survives item deletion
            $table->string('item_name');
            // Nullable: will be null after the item is deleted
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('action_type', 30); // 'created' | 'deleted'
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furniture_item_logs');
    }
};
