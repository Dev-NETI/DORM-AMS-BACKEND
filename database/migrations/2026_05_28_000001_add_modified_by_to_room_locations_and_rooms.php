<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_locations', function (Blueprint $table) {
            $table->string('modified_by')->nullable()->after('department_id');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('modified_by')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('room_locations', function (Blueprint $table) {
            $table->dropColumn('modified_by');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('modified_by');
        });
    }
};
