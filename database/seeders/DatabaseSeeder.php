<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is seeded everywhere; example layout, prices and demo accounts only locally.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call([ParkingLayoutSeeder::class, DemoUserSeeder::class]);
        }
    }
}
