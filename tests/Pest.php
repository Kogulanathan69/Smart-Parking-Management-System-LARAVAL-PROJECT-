<?php

use App\Models\ParkingPackage;
use App\Models\ParkingSession;
use App\Models\ParkingZone;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\ParkingLayoutSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function seedParking(): void
{
    (new ReferenceDataSeeder)->run();
    (new ParkingLayoutSeeder)->run();
}

function userWithRole(string $role, array $attributes = []): User
{
    return User::factory()->role($role)->create($attributes);
}

function category(string $code): VehicleCategory
{
    return VehicleCategory::where('code', $code)->firstOrFail();
}

function zone(string $code): ParkingZone
{
    return ParkingZone::where('code', $code)->firstOrFail();
}

function package(string $code): ParkingPackage
{
    return ParkingPackage::where('code', $code)->firstOrFail();
}

/**
 * Record an entry through the API and return [session, qr_payload].
 *
 * @return array{0: ParkingSession, 1: string}
 */
function recordEntry(User $staff, array $overrides = []): array
{
    $response = test()->actingAs($staff)->postJson('/api/v1/staff/entries', array_merge([
        'plate_number' => 'WP CAB-'.random_int(1000, 9999),
        'vehicle_category_id' => category('car')->id,
        'parking_zone_id' => zone('B')->id,
        'parking_package_id' => package('daily')->id,
    ], $overrides));
    $response->assertCreated();

    return [ParkingSession::where('uuid', $response->json('data.uuid'))->firstOrFail(), $response->json('ticket.qr_payload')];
}

/**
 * Settle an exit through the API (cash by default, fresh idempotency key unless given).
 */
function settle(string $payload, array $extra = []): TestResponse
{
    return test()->postJson('/api/v1/staff/exits/settle', array_merge([
        'qr_payload' => $payload,
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cash',
    ], $extra));
}
