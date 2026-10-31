<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends Model
{
    use HasFactory;

    public const CAR = 'car';

    public const VAN = 'van';

    public const MOTORCYCLE = 'motorcycle';

    public const BICYCLE = 'bicycle';

    public const HEAVY = 'heavy';

    protected $fillable = ['code', 'name', 'requires_plate', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['requires_plate' => 'boolean', 'is_active' => 'boolean'];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
