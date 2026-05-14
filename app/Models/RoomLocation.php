<?php

namespace App\Models;

use App\Traits\HasModifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomLocation extends Model
{
    use HasModifiedBy;

    protected $fillable = [
        'name',
        'location_type',
        'floor',
        'capacity',
        'description',
        'department_id',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
