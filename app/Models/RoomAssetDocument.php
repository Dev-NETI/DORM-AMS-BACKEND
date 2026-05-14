<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssetDocument extends Model
{
    protected $fillable = [
        'room_asset_id',
        'file_path',
        'original_name',
        'document_type',
        'uploaded_by',
    ];

    public function roomAsset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
