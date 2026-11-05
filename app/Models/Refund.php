<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'reference', 'parking_session_id', 'payment_id', 'amount_minor', 'status',
        'reason', 'requested_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'approved_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }
}
