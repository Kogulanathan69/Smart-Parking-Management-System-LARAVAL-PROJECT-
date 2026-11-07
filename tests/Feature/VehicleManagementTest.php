<?php

use App\Models\AuditLog;
use App\Models\ParkingSession;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    seedParking();
    $this->customer = userWithRole(User::ROLE_CUSTOMER);
    $this->admin = userWithRole(User::ROLE_ADMIN);
    $this->staff = userWithRole(User::ROLE_STAFF);
});

it('registers petrol and diesel vehicles with category and fuel kept separate', function (string $fuel) {
    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => "CAR-{$fuel}", 'vehicle_category_id' => category('car')->id, 'fuel_type' => $fuel,
    ])->assertCreated()
        ->assertJsonPath('data.vehicle_category', 'car')
        ->assertJsonPath('data.fuel_type', $fuel);
})->with(['petrol', 'diesel', 'electric', 'hybrid']);

it('rejects customer claims of existing unowned, owned, and soft-deleted vehicles', function () {
    $unowned = Vehicle::factory()->create(['identifier' => 'CLAIM1000', 'plate_number' => 'CLAIM-1000', 'owner_id' => null]);
    $owned = Vehicle::factory()->create(['identifier' => 'CLAIM2000', 'plate_number' => 'CLAIM-2000', 'owner_id' => userWithRole(User::ROLE_CUSTOMER)->id]);
    $deleted = Vehicle::factory()->create(['identifier' => 'CLAIM3000', 'plate_number' => 'CLAIM-3000', 'owner_id' => null]);
    $deleted->delete();

    foreach (['CLAIM-1000', 'CLAIM-2000', 'CLAIM-3000'] as $plate) {
        $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
            'plate_number' => $plate, 'vehicle_category_id' => category('car')->id,
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'vehicle_already_registered')
            ->assertJsonMissingPath('context.owner_id');
    }

    expect($unowned->refresh()->owner_id)->toBeNull()
        ->and($owned->refresh()->owner_id)->not->toBe($this->customer->id)
        ->and(Vehicle::withTrashed()->find($deleted->id)->trashed())->toBeTrue();
});

it('keeps one identifier and owner when registration requests conflict', function () {
    $payload = ['plate_number' => 'RACE-1000', 'vehicle_category_id' => category('car')->id];

    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', $payload)->assertCreated();
    $this->postJson('/api/v1/customer/vehicles', $payload)
        ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_already_registered');

    expect(Vehicle::where('identifier', 'RACE1000')->count())->toBe(1)
        ->and(Vehicle::where('identifier', 'RACE1000')->value('owner_id'))->toBe($this->customer->id);
});

it('validates fuel types and their compatibility with the vehicle category', function () {
    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'CAR-1', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'kerosene',
    ])->assertUnprocessable()->assertJsonValidationErrors('fuel_type');

    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'CAR-2', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'not_applicable',
    ])->assertUnprocessable()->assertJsonPath('error_code', 'fuel_type_not_allowed');

    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'vehicle_category_id' => category('bicycle')->id, 'fuel_type' => 'petrol',
    ])->assertUnprocessable()->assertJsonPath('error_code', 'fuel_type_not_allowed');
});

it('defaults the fuel type for old clients that do not send one', function () {
    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'OLD-1', 'vehicle_category_id' => category('car')->id])
        ->assertCreated()->assertJsonPath('data.fuel_type', 'other');
    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', ['vehicle_category_id' => category('bicycle')->id])
        ->assertCreated()->assertJsonPath('data.fuel_type', 'not_applicable');
});

it('searches and filters own vehicles by plate, category and fuel type', function () {
    $this->actingAs($this->customer);
    $this->postJson('/api/v1/customer/vehicles', ['plate_number' => 'WP CAA-1111', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol']);
    $this->postJson('/api/v1/customer/vehicles', ['plate_number' => 'WP CAB-2222', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'diesel']);
    $this->postJson('/api/v1/customer/vehicles', ['plate_number' => 'WP LV-3333', 'vehicle_category_id' => category('van')->id, 'fuel_type' => 'diesel']);
    Vehicle::factory()->create(['identifier' => 'OTHER1', 'fuel_type' => 'diesel']); // someone else's / unowned

    expect($this->getJson('/api/v1/customer/vehicles')->json('data'))->toHaveCount(3);
    expect($this->getJson('/api/v1/customer/vehicles?fuel_type=diesel')->json('data'))->toHaveCount(2);
    expect($this->getJson('/api/v1/customer/vehicles?fuel_type=diesel&vehicle_category_id='.category('van')->id)->json('data.0.identifier'))->toBe('WPLV3333');
    expect($this->getJson('/api/v1/customer/vehicles?q=cab 2222')->json('data.0.identifier'))->toBe('WPCAB2222');
    $this->getJson('/api/v1/customer/vehicles?fuel_type=steam')->assertUnprocessable();
});

it('lets customers change category and fuel, but not while the vehicle is parked', function () {
    $id = $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'VAN-777', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol',
    ])->json('data.id');

    $this->patchJson("/api/v1/customer/vehicles/{$id}", ['vehicle_category_id' => category('van')->id, 'fuel_type' => 'diesel'])
        ->assertOk()->assertJsonPath('data.vehicle_category', 'van')->assertJsonPath('data.fuel_type', 'diesel');

    [$session] = recordEntry($this->staff, ['plate_number' => 'VAN-777', 'vehicle_category_id' => category('van')->id]);
    expect($session->vehicle_category_id)->toBe(category('van')->id)->and($session->fuel_type->value)->toBe('diesel');

    $this->actingAs($this->customer)->patchJson("/api/v1/customer/vehicles/{$id}", ['vehicle_category_id' => category('car')->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_in_active_session');
    $this->patchJson("/api/v1/customer/vehicles/{$id}", ['fuel_type' => 'hybrid'])->assertOk();

    // The session keeps the category and fuel recorded at entry.
    expect($session->refresh()->fuel_type->value)->toBe('diesel')
        ->and($session->pricing_snapshot['vehicle'])->toBe(['category' => 'van', 'fuel_type' => 'diesel']);
});

it('prevents customers from editing or viewing other customers vehicles', function () {
    $id = $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'MINE-1', 'vehicle_category_id' => category('car')->id])->json('data.id');
    $other = userWithRole(User::ROLE_CUSTOMER);

    $this->actingAs($other)->patchJson("/api/v1/customer/vehicles/{$id}", ['fuel_type' => 'diesel'])->assertForbidden();
    $this->actingAs($other)->getJson("/api/v1/customer/vehicles/{$id}")->assertForbidden();
    $this->actingAs($other)->deleteJson("/api/v1/customer/vehicles/{$id}")->assertForbidden();
    $this->actingAs($other)->getJson('/api/v1/staff/vehicles')->assertForbidden();
});

it('blocks unlinking while a vehicle is parked or holds an active reservation', function () {
    $parkedId = $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'PARKED-1', 'vehicle_category_id' => category('car')->id,
    ])->json('data.id');
    recordEntry($this->staff, ['plate_number' => 'PARKED-1']);

    $this->actingAs($this->customer)->deleteJson("/api/v1/customer/vehicles/{$parkedId}")
        ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_in_active_session');
    expect(Vehicle::find($parkedId)->owner_id)->toBe($this->customer->id);

    $reservedId = $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'RESERVED-1', 'vehicle_category_id' => category('car')->id,
    ])->json('data.id');
    [$visit] = recordEntry($this->staff, [
        'plate_number' => 'RESERVED-1', 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id,
    ]);
    $visit->forceFill(['status' => ParkingSession::COMPLETED, 'open_vehicle_key' => null, 'open_slot_key' => null, 'exit_at' => now()])->save();

    $this->actingAs($this->customer)->deleteJson("/api/v1/customer/vehicles/{$reservedId}")
        ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_has_reservation');
    expect(Vehicle::find($reservedId)->owner_id)->toBe($this->customer->id);
});

it('keeps parking history when a vehicle is removed from an account', function () {
    $owner = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0771112222']);
    $id = $this->actingAs($owner)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'HIST-1', 'vehicle_category_id' => category('car')->id])->json('data.id');
    [$session] = recordEntry($this->staff, ['plate_number' => 'HIST-1']);
    $session->forceFill(['status' => ParkingSession::COMPLETED, 'open_vehicle_key' => null, 'open_slot_key' => null, 'exit_at' => now()])->save();

    $this->actingAs($owner)->deleteJson("/api/v1/customer/vehicles/{$id}")->assertNoContent();

    expect(Vehicle::find($id))->not->toBeNull()
        ->and($session->refresh()->vehicle_id)->toBe($id);
    $this->actingAs($this->staff)->getJson("/api/v1/staff/vehicles/{$id}")->assertOk()->assertJsonCount(1, 'recent_sessions');
});

it('records fuel type at entry and lets staff correct it', function () {
    [$session] = recordEntry($this->staff, ['plate_number' => 'GATE-1', 'fuel_type' => 'electric']);
    expect($session->vehicle->fuel_type->value)->toBe('electric')->and($session->fuel_type->value)->toBe('electric');

    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'GATE-2', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'not_applicable',
        'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertUnprocessable()->assertJsonPath('error_code', 'fuel_type_not_allowed');
});

it('gives staff a filtered, paginated vehicle register and admins the ability to link owners', function () {
    Vehicle::factory()->count(3)->create(['fuel_type' => 'petrol']);
    $diesel = Vehicle::factory()->create(['fuel_type' => 'diesel']);

    $this->actingAs($this->staff)->getJson('/api/v1/staff/vehicles?fuel_type=diesel')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    $this->actingAs($this->staff)->patchJson("/api/v1/admin/vehicles/{$diesel->id}", ['owner_id' => $this->customer->id])->assertForbidden();

    $this->actingAs($this->admin)->patchJson("/api/v1/admin/vehicles/{$diesel->id}", ['owner_id' => $this->customer->id])->assertOk();
    expect($diesel->refresh()->owner_id)->toBe($this->customer->id);
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/vehicles/{$diesel->id}", ['owner_id' => $this->staff->id])->assertUnprocessable();
    expect(AuditLog::where('action', 'vehicle.updated')->where('auditable_id', $diesel->id)->exists())->toBeTrue();
});

it('lets admins manage vehicle categories with validation', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/vehicle-categories', ['code' => 'tuk', 'name' => 'Three-wheeler', 'requires_plate' => true])
        ->assertCreated()->assertJsonPath('data.is_motorized', true)->assertJsonPath('data.is_active', true)->assertJsonPath('data.requires_plate', true);
    $this->actingAs($this->admin)->postJson('/api/v1/admin/vehicle-categories', ['code' => 'car', 'name' => 'Duplicate'])->assertUnprocessable();

    $van = category('van');
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/vehicle-categories/{$van->id}", ['code' => 'lorry'])->assertUnprocessable();
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/vehicle-categories/{$van->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

    // Deactivated categories cannot be used for new vehicles or entries.
    $this->actingAs($this->customer)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'VAN-9', 'vehicle_category_id' => $van->id])->assertUnprocessable();
    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'VAN-9', 'vehicle_category_id' => $van->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertNotFound();

    $this->actingAs($this->staff)->patchJson("/api/v1/admin/vehicle-categories/{$van->id}", ['is_active' => true])->assertForbidden();
});

it('refuses category changes that would invalidate existing vehicles', function () {
    Vehicle::factory()->create(['vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol']);
    $this->actingAs($this->admin)->patchJson('/api/v1/admin/vehicle-categories/'.category('car')->id, ['is_motorized' => false])
        ->assertStatus(409)->assertJsonPath('error_code', 'category_change_conflict');

    recordEntry($this->staff, ['plate_number' => null, 'vehicle_category_id' => category('bicycle')->id, 'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);
    $this->actingAs($this->admin)->patchJson('/api/v1/admin/vehicle-categories/'.category('bicycle')->id, ['requires_plate' => true])
        ->assertStatus(409);
});

it('publishes fuel types and category fuel rules in reference data', function () {
    $data = $this->actingAs($this->customer)->getJson('/api/v1/reference-data')->assertOk()->json('data');

    expect(collect($data['fuel_types'])->pluck('value')->all())->toBe(['petrol', 'diesel', 'electric', 'hybrid', 'other', 'not_applicable'])
        ->and(collect($data['vehicle_categories'])->firstWhere('code', 'bicycle')['allowed_fuel_types'])->toBe(['not_applicable']);
});
