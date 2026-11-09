<?php

use App\Models\ParkingZone;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\PortableDemoSeeder;

it('seeds only synthetic demo records in an approved demo-named fixture', function () {
    config()->set('database.connections.sqlite.database', 'parkflow_demo_test_fixture');
    (new PortableDemoSeeder)->run();

    expect(User::where('email', 'staff@smart-parking.test')->exists())->toBeTrue()
        ->and(User::where('email', 'customer@smart-parking.test')->exists())->toBeTrue()
        ->and(Vehicle::where('identifier', 'WPDEMO1001')->exists())->toBeTrue()
        ->and(Vehicle::where('identifier', 'WPDEMO2002')->exists())->toBeTrue()
        ->and(ParkingZone::count())->toBe(5);
});

it('refuses to seed a protected database name', function () {
    config()->set('database.connections.sqlite.database', 'smart_parking');

    expect(fn () => (new PortableDemoSeeder)->run())
        ->toThrow(RuntimeException::class, 'restricted to parkflow_demo_*');
});
