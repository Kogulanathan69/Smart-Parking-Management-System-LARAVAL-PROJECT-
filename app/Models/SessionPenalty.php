<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPenalty extends Model
{
    public const APPLIED = 'applied';
    public const WAIVED = 'waived';

    protected $fillable = [
        'parking_session_id', 'penalty_type_id', 'amount_minor', 'reason', 'status',
        'applied_by', 'applied_at', 'waived_by', 'waived_at', 'waiver_reason', 'active_key',
    ];

    protected $hidden = ['active_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'applied_at' => 'datetime', 'waived_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PenaltyType::class, 'penalty_type_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}
