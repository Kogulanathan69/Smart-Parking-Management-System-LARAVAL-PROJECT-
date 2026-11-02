<?php

namespace App\Models;

use App\Enums\FuelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'identifier', 'plate_number', 'is_reference', 'vehicle_category_id',
        'owner_id', 'make', 'model', 'color', 'fuel_type', 'registered_by',
    ];

    protected function casts(): array
    {
        return ['is_reference' => 'boolean', 'fuel_type' => FuelType::class];
    }

    /**
     * Normalise a licence plate so "WP CAB-1234" and "wpcab1234" match.
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class);
    }

    public function openSession(): HasOne
    {
        return $this->hasOne(ParkingSession::class, 'open_vehicle_key');
    }
}
