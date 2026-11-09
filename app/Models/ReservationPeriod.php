<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPeriod extends Model
{
    public const KIND_INITIAL = 'initial';

    public const KIND_RENEWAL = 'renewal';

    public const PENDING = 'pending';

    public const BILLED = 'billed';

    public const VOID = 'void';

    protected $fillable = [
        'parking_reservation_id', 'sequence', 'kind', 'starts_at', 'ends_at', 'package_rate_id',
        'price_minor', 'currency', 'billed_session_id', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'price_minor' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }

    public function billedSession(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'billed_session_id');
    }

    /**
     * Paid = billed on a session that has been settled (completed or exit authorised after payment).
     */
    public function isPaid(): bool
    {
        return $this->status === self::BILLED
            && $this->billedSession !== null
            && in_array($this->billedSession->status, [ParkingSession::COMPLETED, ParkingSession::EXIT_AUTHORIZED], true);
    }
}
