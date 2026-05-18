<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdc_room_furniture_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            $table->string('action_type', 30); // 'purchase' | 'adjustment'

            $table->unsignedInteger('qty_before')->default(0);
            $table->unsignedInteger('qty_after')->default(0);
            $table->integer('qty_change');

            $table->foreignId('reference_id')
                ->nullable()
                ->constrained('cdc_room_purchases')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['room_id', 'item_id']);
            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdc_room_furniture_logs');
    }
};
