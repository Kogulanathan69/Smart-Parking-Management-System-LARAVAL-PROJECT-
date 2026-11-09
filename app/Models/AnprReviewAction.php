<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only verification/correction trail (immutable, like AuditLog).
 */
class AnprReviewAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['anpr_event_id', 'user_id', 'action', 'from_plate', 'to_plate', 'reason', 'notes'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('ANPR review actions are immutable.'));
        static::deleting(fn () => throw new LogicException('ANPR review actions are immutable.'));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AnprEvent::class, 'anpr_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
