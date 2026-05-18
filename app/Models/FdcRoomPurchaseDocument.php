<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdcRoomPurchaseDocument extends Model
{
    protected $fillable = [
        'fdc_room_purchase_id',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(FdcRoomPurchase::class, 'fdc_room_purchase_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
