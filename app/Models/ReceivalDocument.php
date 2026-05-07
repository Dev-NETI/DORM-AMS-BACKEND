<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivalDocument extends Model
{
    protected $fillable = [
        'stock_receival_id',
        'file_path',
        'original_name',
    ];

    public function stockReceival(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockReceival::class);
    }
}
