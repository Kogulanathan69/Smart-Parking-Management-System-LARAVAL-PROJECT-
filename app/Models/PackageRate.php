<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'parking_package_id', 'vehicle_category_id', 'version', 'price_minor',
        'overtime_hourly_minor', 'overtime_daily_cap_minor',
        'grace_minutes', 'currency', 'effective_from', 'effective_to', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'overtime_hourly_minor' => 'integer',
            'overtime_daily_cap_minor' => 'integer',
            'grace_minutes' => 'integer',
            'version' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ParkingPackage::class, 'parking_package_id');
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }
}
