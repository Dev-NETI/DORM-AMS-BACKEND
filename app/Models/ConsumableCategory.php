<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumableCategory extends Model
{
    protected $fillable = ['name', 'description', 'department_id'];

    public function items(): HasMany
    {
        return $this->hasMany(ConsumableItem::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
