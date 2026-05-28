<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdcRoomFurnitureDisposedDocument extends Model
{
    protected $table = 'fdc_room_furniture_disposed_documents';

    protected $fillable = [
        'disposed_history_id',
        'file_path',
        'file_name',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(FdcRoomFurnitureDisposedHistory::class, 'disposed_history_id');
    }
}
