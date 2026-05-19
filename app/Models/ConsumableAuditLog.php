<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableAuditLog extends Model
{
    protected $fillable = [
        'action_type',
        'entity_type',
        'entity_id',
        'description',
        'meta',
        'performed_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Convenience method for creating an audit entry.
     */
    public static function log(
        string $actionType,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $meta = null,
        ?int $userId = null,
    ): self {
        return self::create([
            'action_type'  => $actionType,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'description'  => $description,
            'meta'         => $meta,
            'performed_by' => $userId,
        ]);
    }
}
