<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('consumable_item_id');
        });

        // Seed from the existing inventory_stocks mirror so no data is lost.
        // For each consumable_item that has a mirror item_id, copy that stock quantity.
        DB::statement("
            INSERT INTO consumable_stocks (consumable_item_id, quantity, created_at, updated_at)
            SELECT ci.id,
                   COALESCE(ins.quantity, 0),
                   NOW(),
                   NOW()
            FROM consumable_items ci
            LEFT JOIN inventory_stocks ins ON ins.item_id = ci.item_id
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_stocks');
    }
};
