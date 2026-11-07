<?php

use App\Models\User;
use App\Services\EntryService;

beforeEach(function () {
    seedParking();
});

it('gives customers availability counts and published prices, and nothing else', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER);
    $staff = userWithRole(User::ROLE_STAFF);
    app(EntryService::class)->recordEntry($staff, ['plate_number' => 'WP CAB-1234', 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id, 'fuel_type' => 'petrol']);

    $r = $this->actingAs($customer)->getJson('/api/v1/customer/parking-info')->assertOk()
        ->assertJsonPath('data.currency', 'LKR')
        ->assertJsonStructure(['data' => ['zones' => [['code', 'name', 'zone_type', 'available', 'by_vehicle_category' => [['vehicle_category', 'available', 'total']]]],
            'packages' => [['code', 'name', 'billing_mode', 'duration_minutes', 'description', 'allowed_zone_types', 'prices' => [['vehicle_category', 'price_minor', 'overtime_hourly_minor', 'grace_minutes']]]]]]);

    $shortTime = collect($r->json('data.packages'))->firstWhere('code', 'short_time');
    expect($shortTime['allowed_zone_types'])->toContain('short_time', 'safety');

    $zoneB = collect($r->json('data.zones'))->firstWhere('code', 'B');
    $car = collect($zoneB['by_vehicle_category'])->firstWhere('vehicle_category', 'car');
    expect($car['available'])->toBe($car['total'] - 1);
    // No slot codes, plates, sessions or people leak into the payload.
    expect(json_encode($r->json()))->not->toContain('B-CAR-')->not->toContain('CAB')->not->toContain('"slot"')->not->toContain('slot_id')->not->toContain('customer');
});

it('restricts the customer parking info to customers', function () {
    foreach ([User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_AUDITOR, User::ROLE_SUPER_ADMIN] as $role) {
        $this->actingAs(userWithRole($role))->getJson('/api/v1/customer/parking-info')->assertForbidden();
    }
});

it('requires sign-in for the customer parking info', function () {
    $this->getJson('/api/v1/customer/parking-info')->assertUnauthorized();
});

it('reports driver modes honestly to admins without exposing secrets', function () {
    config(['parking.anpr.service_token' => 'super-secret-token-value', 'parking.anpr.service_url' => 'http://127.0.0.1:8765']);
    $r = $this->actingAs(userWithRole(User::ROLE_ADMIN))->getJson('/api/v1/admin/system/status')->assertOk()
        ->assertJsonPath('data.drivers.gate_controller', ['driver' => 'log', 'simulated' => true])
        ->assertJsonPath('data.drivers.payment_gateway.simulated', true)
        ->assertJsonPath('data.anpr_engine.driver', 'disabled')
        ->assertJsonPath('data.anpr_engine.available', false)
        ->assertJsonPath('data.gate.release_slot_on', 'settlement')
        ->assertJsonPath('data.backups', null);
    expect(json_encode($r->json()))->not->toContain('super-secret-token-value')->not->toContain('8765');

    $this->actingAs(userWithRole(User::ROLE_SUPER_ADMIN))->getJson('/api/v1/admin/system/status')->assertOk();
    foreach ([User::ROLE_STAFF, User::ROLE_AUDITOR, User::ROLE_CUSTOMER] as $role) {
        $this->actingAs(userWithRole($role))->getJson('/api/v1/admin/system/status')->assertForbidden();
    }
});
