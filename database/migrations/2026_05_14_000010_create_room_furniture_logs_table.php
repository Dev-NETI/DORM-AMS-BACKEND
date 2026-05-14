<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_furniture_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // What kind of movement generated this entry
            $table->string('action_type', 30); // 'purchase' | 'adjustment'

            // Quantity snapshot
            $table->unsignedInteger('qty_before')->default(0);
            $table->unsignedInteger('qty_after')->default(0);
            $table->integer('qty_change');          // qty_after - qty_before (signed)

            // Optional link back to a purchase record
            $table->foreignId('reference_id')
                ->nullable()
                ->constrained('room_purchases')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Useful indexes for filtering
            $table->index(['room_id', 'item_id']);
            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furniture_logs');
    }
};
