<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParkingSlot extends Model
{
    use HasFactory;

    public const AVAILABLE = 'available';

    public const OCCUPIED = 'occupied';

    public const RESERVED = 'reserved';

    public const UNAVAILABLE = 'unavailable';

    public const STATUSES = [self::AVAILABLE, self::OCCUPIED, self::RESERVED, self::UNAVAILABLE];

    protected $fillable = ['parking_zone_id', 'vehicle_category_id', 'code', 'status', 'notes', 'decommissioned_at'];

    protected function casts(): array
    {
        return ['decommissioned_at' => 'datetime'];
    }

    public function scopeInService($query)
    {
        return $query->whereNull('decommissioned_at');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'parking_zone_id');
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class);
    }

    public function openSession(): HasOne
    {
        return $this->hasOne(ParkingSession::class, 'open_slot_key');
    }
}
