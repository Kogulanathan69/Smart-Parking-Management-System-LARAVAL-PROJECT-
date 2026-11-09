<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingZone extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'zone_type_id', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function zoneType(): BelongsTo
    {
        return $this->belongsTo(ZoneType::class);
    }

    public function vehicleCategories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class);
    }

    public function acceptsCategory(int $vehicleCategoryId): bool
    {
        return $this->vehicleCategories()->whereKey($vehicleCategoryId)->exists();
    }
}
