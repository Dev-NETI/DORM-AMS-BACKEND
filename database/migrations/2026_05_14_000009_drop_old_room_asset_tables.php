<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove old individual-asset-based tables replaced by the quantity model
        Schema::dropIfExists('room_asset_transfers');
        Schema::dropIfExists('room_asset_documents');
        Schema::dropIfExists('room_assets');
    }

    public function down(): void
    {
        // Restoration is handled by re-running the original 000002–000004 migrations
    }
};
