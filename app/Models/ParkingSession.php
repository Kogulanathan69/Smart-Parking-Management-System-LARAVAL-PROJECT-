<?php

namespace App\Models;

use App\Enums\FuelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParkingSession extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const EXIT_AUTHORIZED = 'exit_authorized';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'reference', 'vehicle_id', 'vehicle_category_id', 'fuel_type', 'customer_id', 'customer_name', 'customer_phone', 'parking_zone_id', 'parking_slot_id',
        'parking_package_id', 'package_rate_id', 'safety_rate_id', 'has_safety', 'status',
        'entry_at', 'package_ends_at', 'exit_at', 'entry_staff_id', 'exit_staff_id',
        'pricing_snapshot', 'charge_breakdown', 'total_charge_minor',
        'open_vehicle_key', 'open_slot_key', 'entry_source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'has_safety' => 'boolean',
            'fuel_type' => FuelType::class,
            'entry_at' => 'datetime',
            'package_ends_at' => 'datetime',
            'exit_at' => 'datetime',
            'pricing_snapshot' => 'array',
            'charge_breakdown' => 'array',
            'total_charge_minor' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::ACTIVE, self::EXIT_AUTHORIZED], true);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * Vehicle category recorded at entry (historical; used for billing and filters).
     */
    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(SessionPenalty::class);
    }

    public function appliedPenalties(): HasMany
    {
        return $this->penalties()->where('status', SessionPenalty::APPLIED);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(SessionReminder::class);
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

    public function entryStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entry_staff_id');
    }

    public function exitStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exit_staff_id');
    }

    public function qrTicket(): HasOne
    {
        return $this->hasOne(QrTicket::class)->latestOfMany();
    }

    public function qrTickets(): HasMany
    {
        return $this->hasMany(QrTicket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(SafetyInspection::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function paidAmountMinor(): int
    {
        return (int) $this->payments()->where('status', Payment::PAID)->sum('amount_minor');
    }
}
