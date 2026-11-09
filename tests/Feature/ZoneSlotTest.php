<?php

use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\ZoneType;

beforeEach(function () {
    seedParking();
    $this->admin = userWithRole(User::ROLE_ADMIN);
});

it('reports live occupancy per zone and vehicle category', function () {
    [$session] = recordEntry(userWithRole(User::ROLE_STAFF));

    $zoneB = collect($this->actingAs($this->admin)->getJson('/api/v1/reports/occupancy')->assertOk()->json('data'))->firstWhere('code', 'B');
    $car = collect($zoneB['by_vehicle_category'])->firstWhere('vehicle_category', 'car');

    expect($car)->toMatchArray(['total' => 10, 'occupied' => 1, 'available' => 9]);
});

it('increases and decreases zone capacity', function () {
    $zone = zone('B');

    $this->actingAs($this->admin)->putJson("/api/v1/admin/zones/{$zone->id}/capacity", ['vehicle_category_id' => category('car')->id, 'capacity' => 12])
        ->assertOk()->assertJsonPath('data.total', 12);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/zones/{$zone->id}/capacity", ['vehicle_category_id' => category('car')->id, 'capacity' => 3])
        ->assertOk()->assertJsonPath('data.total', 3)->assertJsonPath('data.available', 3);

    expect(ParkingSlot::where('parking_zone_id', $zone->id)->whereNotNull('decommissioned_at')->count())->toBe(9);
});

it('never reduces capacity below occupied slots', function () {
    $staff = userWithRole(User::ROLE_STAFF);
    recordEntry($staff);
    recordEntry($staff);

    $this->actingAs($this->admin)->putJson('/api/v1/admin/zones/'.zone('B')->id.'/capacity', ['vehicle_category_id' => category('car')->id, 'capacity' => 1])
        ->assertUnprocessable()->assertJsonPath('error_code', 'capacity_below_occupied');
});

it('rejects capacity for a vehicle category the zone does not accept', function () {
    $this->actingAs($this->admin)->putJson('/api/v1/admin/zones/'.zone('B')->id.'/capacity', ['vehicle_category_id' => category('bicycle')->id, 'capacity' => 3])
        ->assertUnprocessable()->assertJsonPath('error_code', 'category_not_allowed');
});

it('lets admins reserve or disable available slots but not occupied ones', function () {
    $slot = ParkingSlot::where('parking_zone_id', zone('B')->id)->orderBy('code')->first();

    $this->actingAs($this->admin)->patchJson("/api/v1/admin/slots/{$slot->id}/status", ['status' => 'reserved'])->assertOk()->assertJsonPath('data.status', 'reserved');
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/slots/{$slot->id}/status", ['status' => 'occupied'])->assertUnprocessable();

    $slot->forceFill(['status' => 'available'])->save();
    [$session] = recordEntry(userWithRole(User::ROLE_STAFF));
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/slots/{$session->parking_slot_id}/status", ['status' => 'unavailable'])
        ->assertStatus(409)->assertJsonPath('error_code', 'slot_locked');
});

it('creates zones with configurable types and allowed vehicle categories', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/zones', [
        'code' => 'F', 'name' => 'Overflow', 'zone_type_id' => ZoneType::where('code', 'daily')->value('id'),
        'vehicle_category_ids' => [category('car')->id],
    ])->assertCreated()->assertJsonPath('data.vehicle_categories', ['car']);
});
