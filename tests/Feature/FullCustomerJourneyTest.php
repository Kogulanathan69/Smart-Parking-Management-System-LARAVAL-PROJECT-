<?php

use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\QrTicket;
use App\Models\Receipt;
use App\Models\User;

beforeEach(fn () => seedParking());

it('completes the customer journey from vehicle check-in to receipt and parking history', function () {
    // Step 1: Customer registers through the real registration API
    $this->postJson('/register', [
        'name' => 'Nimal Perera',
        'email' => 'journey@example.com',
        'phone' => '0771234567',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertCreated()->assertJsonPath('redirect', '/customer/dashboard');

    $customer = User::where('email', 'journey@example.com')->firstOrFail();

    expect($customer->role)->toBe(User::ROLE_CUSTOMER);

    // Log out after registration to test a fresh login
    $this->post('/logout')->assertRedirect();
    $this->assertGuest();

    // Step 2: Customer logs in through the real login API
    $this->postJson('/login', [
        'email' => 'journey@example.com',
        'password' => 'secret-pass-123',
    ])->assertOk()->assertJsonPath('redirect', '/customer/dashboard');

    $staff = userWithRole(User::ROLE_STAFF);

    // Step 2: Staff registers vehicle and checks it in
    [$session, $qrPayload] = recordEntry($staff, [
        'plate_number' => 'WP CAB-5678',
        'customer_phone' => '0771234567',
    ]);

    expect($session->status)->toBe(ParkingSession::ACTIVE)
        ->and($qrPayload)->not->toBeEmpty()
        ->and(ParkingSlot::find($session->parking_slot_id)->status)->toBe('occupied');

    // Step 3: Customer sees the active parking session
    $this->actingAs($customer)
        ->getJson('/api/v1/customer/sessions')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $session->uuid);

    // Step 4: Vehicle remains parked for three hours
    $this->travel(3)->hours();

    // Step 5: Staff scans QR and collects payment
    $response = $this->actingAs($staff);

    $response = settle($qrPayload, [
        'tendered_minor' => 200000,
    ])->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('exit_authorized', true)
        ->assertJsonPath('gate.status', 'simulated');

    // Step 6: Check-out and slot release
    $session->refresh();

    expect($session->status)->toBe(ParkingSession::COMPLETED)
        ->and(ParkingSlot::find($session->parking_slot_id)->status)->toBe('available')
        ->and($session->qrTicket->status)->toBe(QrTicket::USED)
        ->and(Payment::where('parking_session_id', $session->id)->count())->toBe(1)
        ->and(Receipt::where('parking_session_id', $session->id)->count())->toBe(1);

    // Step 7: Customer views receipt
    $receiptNumber = $response->json('receipt.number');

    $this->actingAs($customer)
        ->getJson("/api/v1/customer/receipts/{$receiptNumber}")
        ->assertOk()
        ->assertJsonPath('data.total_minor', 150000);

    // Step 8: Customer views parking history
    $this->actingAs($customer)
        ->getJson('/api/v1/customer/sessions')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $session->uuid);
});
