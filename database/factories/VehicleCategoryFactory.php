<?php

namespace Database\Factories;

use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCategory>
 */
class VehicleCategoryFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->lexify('cat???');

        return ['code' => $code, 'name' => ucfirst($code), 'requires_plate' => true, 'is_active' => true];
    }
}
