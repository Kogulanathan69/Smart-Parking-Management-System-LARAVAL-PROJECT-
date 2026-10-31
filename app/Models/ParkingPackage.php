<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingPackage extends Model
{
    use HasFactory;

    public const MODE_HOURLY = 'hourly';

    public const MODE_FIXED = 'fixed';

    public const MODE_NIGHT = 'night';

    public const MODE_ADDON = 'addon';

    public const MODES = [self::MODE_HOURLY, self::MODE_FIXED, self::MODE_NIGHT, self::MODE_ADDON];

    protected $fillable = [
        'code', 'name', 'billing_mode', 'duration_minutes',
        'allows_early_exit_credit', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return ['allows_early_exit_credit' => 'boolean', 'is_active' => 'boolean', 'duration_minutes' => 'integer'];
    }

    public function zoneTypes(): BelongsToMany
    {
        return $this->belongsToMany(ZoneType::class, 'package_zone_type');
    }

    public function allowedInZoneType(int $zoneTypeId): bool
    {
        $ids = $this->relationLoaded('zoneTypes') ? $this->zoneTypes->pluck('id') : $this->zoneTypes()->pluck('zone_types.id');

        return $ids->isEmpty() || $ids->contains($zoneTypeId);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(PackageRate::class);
    }

    public function isAddon(): bool
    {
        return $this->billing_mode === self::MODE_ADDON;
    }

    /**
     * The rate in force at a moment for a vehicle category.
     */
    public function rateFor(int $vehicleCategoryId, ?CarbonInterface $at = null): ?PackageRate
    {
        $at ??= now();

        return $this->rates()
            ->where('vehicle_category_id', $vehicleCategoryId)
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version')
            ->first();
    }
}
