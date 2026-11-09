<?php

use App\Models\User;

beforeEach(fn () => seedParking());

it('lets customers manage their vehicles', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER);
    $this->actingAs($customer);

    $id = $this->postJson('/api/v1/customer/vehicles', ['plate_number' => 'KX-4455', 'vehicle_category_id' => category('van')->id, 'make' => 'Toyota'])
        ->assertCreated()->assertJsonPath('data.identifier', 'KX4455')->json('data.id');
    $this->patchJson("/api/v1/customer/vehicles/{$id}", ['color' => 'Blue'])->assertOk()->assertJsonPath('data.color', 'Blue');
    $this->getJson('/api/v1/customer/vehicles')->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/customer/vehicles/{$id}")->assertNoContent();
    $this->getJson('/api/v1/customer/vehicles')->assertJsonCount(0, 'data');
});

it('shows an owned vehicle details page and rejects cross-customer access', function () {
    $owner = userWithRole(User::ROLE_CUSTOMER);
    $other = userWithRole(User::ROLE_CUSTOMER);
    $id = $this->actingAs($owner)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'DETAIL-1', 'vehicle_category_id' => category('car')->id,
    ])->assertCreated()->json('data.id');

    $this->get("/my/vehicles/{$id}")->assertOk()->assertSee('Vehicle details')->assertSee('Parking history');
    $this->getJson("/api/v1/customer/vehicles/{$id}")->assertOk()->assertJsonPath('data.identifier', 'DETAIL1');

    $this->actingAs($other)->getJson("/api/v1/customer/vehicles/{$id}")->assertForbidden();
    $this->get("/my/vehicles/{$id}")->assertForbidden();
});

it('returns a usable vehicle id only after valid customer vehicle creation', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER);

    $this->actingAs($customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'NAV-100', 'vehicle_category_id' => category('car')->id,
    ])->assertCreated()->assertJsonPath('data.id', fn ($id) => is_int($id) && $id > 0);

    $this->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'NAV-INVALID', 'vehicle_category_id' => 999999,
    ])->assertUnprocessable()->assertJsonMissingPath('data.id');
});

it('filters parking history by an owned vehicle and returns active session data', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER);
    $staff = userWithRole(User::ROLE_STAFF);
    $firstId = $this->actingAs($customer)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'HISTORY-1', 'vehicle_category_id' => category('car')->id,
    ])->json('data.id');
    $secondId = $this->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'HISTORY-2', 'vehicle_category_id' => category('car')->id,
    ])->json('data.id');

    [$active] = recordEntry($staff, ['plate_number' => 'HISTORY-1']);

    $this->actingAs($customer)->getJson("/api/v1/customer/sessions?vehicle_id={$firstId}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $active->uuid)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.vehicle.id', $firstId)
        ->assertJsonStructure(['data' => [['zone', 'slot', 'package', 'entry_at', 'time']]]);

    $this->getJson("/api/v1/customer/sessions?vehicle_id={$secondId}")->assertOk()->assertJsonCount(0, 'data');
});

it('forbids filtering customer history by another customers vehicle id', function () {
    $owner = userWithRole(User::ROLE_CUSTOMER);
    $intruder = userWithRole(User::ROLE_CUSTOMER);
    $vehicleId = $this->actingAs($owner)->postJson('/api/v1/customer/vehicles', [
        'plate_number' => 'PRIVATE-1', 'vehicle_category_id' => category('car')->id,
    ])->json('data.id');

    $this->actingAs($intruder)->getJson("/api/v1/customer/sessions?vehicle_id={$vehicleId}")->assertForbidden();
});

it('shows parking history and notifications, and accepts complaints', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0779999999']);
    [$session] = recordEntry(userWithRole(User::ROLE_STAFF), ['customer_phone' => '0779999999']);

    $this->actingAs($customer)->getJson('/api/v1/customer/sessions')->assertOk()->assertJsonPath('data.0.uuid', $session->uuid)
        ->assertJsonMissingPath('data.0.customer_phone');

    $id = $this->actingAs($customer)->postJson('/api/v1/customer/complaints', ['subject' => 'Gate slow', 'description' => 'Waited 10 min', 'parking_session_uuid' => $session->uuid])
        ->assertCreated()->json('data.id');

    $this->actingAs(userWithRole(User::ROLE_ADMIN))->patchJson("/api/v1/admin/complaints/{$id}", ['status' => 'resolved', 'resolution' => 'Fixed'])->assertOk();

    $this->actingAs($customer)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.data.kind', 'complaint_update');
    $this->actingAs($customer)->postJson('/api/v1/notifications/read-all')->assertOk();
    expect($customer->unreadNotifications()->count())->toBe(0);
});

it('does not let customers attach complaints to other peoples sessions', function () {
    [$session] = recordEntry(userWithRole(User::ROLE_STAFF));

    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->postJson('/api/v1/customer/complaints', [
        'subject' => 'x', 'description' => 'y', 'parking_session_uuid' => $session->uuid,
    ])->assertUnprocessable();
});

it('returns reference data for forms', function () {
    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->getJson('/api/v1/reference-data')
        ->assertOk()->assertJsonPath('data.currency', 'LKR')->assertJsonCount(5, 'data.vehicle_categories');
});
