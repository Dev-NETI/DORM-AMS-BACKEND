<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_item_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('remarks', 1000)->nullable();
            $table->timestamps();

            $table->unique(['consumable_item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_remarks');
    }
};
