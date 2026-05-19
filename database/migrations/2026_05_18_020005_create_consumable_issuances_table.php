<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->date('issued_date');
            $table->text('usage_history')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modified_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_issuances');
    }
};
