<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZoneType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_safety', 'is_active'];

    protected function casts(): array
    {
        return ['is_safety' => 'boolean', 'is_active' => 'boolean'];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ParkingZone::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ParkingPackage::class, 'package_zone_type');
    }
}
