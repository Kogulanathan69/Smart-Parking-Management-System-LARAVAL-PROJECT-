<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingReservation extends Model
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'vehicle_id',
        'customer_id',
        'parking_zone_id',
        'parking_slot_id',
        'parking_package_id',
        'package_rate_id',
        'status',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'pricing_snapshot',
        'active_vehicle_key',
        'active_slot_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'pricing_snapshot' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'parking_zone_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'parking_slot_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ParkingPackage::class, 'parking_package_id');
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(PackageRate::class, 'package_rate_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'parking_reservation_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ReservationPeriod::class, 'parking_reservation_id')->orderBy('sequence');
    }

    /**
     * The visit currently in progress (vehicle on site), if any.
     */
    public function openSession(): ?ParkingSession
    {
        return $this->sessions()->whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])->latest('id')->first();
    }

    public function isActiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === self::ACTIVE
            && $this->starts_at->lessThanOrEqualTo($at)
            && $this->expires_at->greaterThan($at)
            && $this->cancelled_at === null;
    }

    public function isExpiredAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->expires_at->lessThanOrEqualTo($at);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }
}
