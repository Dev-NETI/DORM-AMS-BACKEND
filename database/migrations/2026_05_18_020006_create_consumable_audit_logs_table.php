<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action_type');        // created | updated | deleted
            $table->string('entity_type');        // category | item | receival | issuance | opening_balance
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description');
            $table->json('meta')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_audit_logs');
    }
};
