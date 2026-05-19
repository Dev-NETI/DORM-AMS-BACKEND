<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['consumable_item_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_opening_balances');
    }
};
