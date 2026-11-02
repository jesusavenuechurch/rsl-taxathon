<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the tamper-evident audit log. Written only through
 * App\Domain\Audit\AuditLog; rows can never be changed or removed
 * (enforced here and by database triggers).
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
