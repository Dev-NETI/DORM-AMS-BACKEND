<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDocument extends Model
{
    protected $fillable = [
        'item_asset_id',
        'file_path',
        'original_name',
    ];

    public function itemAsset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItemAsset::class);
    }
}
