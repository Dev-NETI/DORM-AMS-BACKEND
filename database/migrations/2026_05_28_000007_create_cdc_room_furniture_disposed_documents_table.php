<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdc_room_furniture_disposed_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disposed_history_id');
            $table->foreign('disposed_history_id', 'cdc_disp_docs_history_fk')
                ->references('id')
                ->on('cdc_room_furniture_disposed_history')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdc_room_furniture_disposed_documents');
    }
};
