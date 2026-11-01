<?php

use App\Models\CustomerCredit;
use App\Models\NotificationLog;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\QrTicket;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
});

function settle(string $payload, array $extra = []): TestResponse
{
    return test()->postJson('/api/v1/staff/exits/settle', array_merge([
        'qr_payload' => $payload,
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cash',
    ], $extra));
}

it('previews the full calculation before payment', function () {
    // Freeze the clock before entry so the 25-hour duration is exact.
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-01 08:00:00', 'Asia/Colombo'));
    [, $payload] = recordEntry($this->staff);
    $this->travel(25)->hours();

    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $payload])
        ->assertOk()
        ->assertJsonPath('quote.total_minor', 160000)
        ->assertJsonPath('quote.balance_minor', 160000)
        ->assertJsonPath('quote.lines.1.code', 'overtime');
});

it('settles a cash exit: payment, receipt, slot released, QR locked', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0770000001']);
    [$session, $payload] = recordEntry($this->staff, ['customer_phone' => '0770000001']);
    $this->travel(3)->hours();

    $response = $this->actingAs($this->staff);
    $response = settle($payload, ['tendered_minor' => 200000])->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('exit_authorized', true)
        ->assertJsonPath('payment.change_minor', 50000)
        ->assertJsonPath('gate.status', 'simulated');

    $session->refresh();
    expect($session->status)->toBe(ParkingSession::COMPLETED)
        ->and($session->exit_staff_id)->toBe($this->staff->id)
        ->and($session->open_slot_key)->toBeNull()
        ->and(ParkingSlot::find($session->parking_slot_id)->status)->toBe('available')
        ->and($session->qrTicket->status)->toBe(QrTicket::USED)
        ->and(Receipt::where('parking_session_id', $session->id)->value('total_minor'))->toBe(150000);

    // Customer can see the receipt and got a notification.
    $number = $response->json('receipt.number');
    $this->actingAs($customer)->getJson("/api/v1/customer/receipts/{$number}")->assertOk()->assertJsonPath('data.total_minor', 150000);
    expect($customer->notifications()->count())->toBe(1);
    expect(NotificationLog::where('user_id', $customer->id)->where('status', 'sent')->exists())->toBeTrue();
});

it('never lets a used QR authorise a second exit', function () {
    [, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);
    settle($payload)->assertOk();

    settle($payload)->assertStatus(409)->assertJsonPath('error_code', 'ticket_already_used');
    expect(Payment::count())->toBe(1);
});

it('is idempotent: repeating a settle request never charges twice', function () {
    [$session, $payload] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $this->actingAs($this->staff);

    settle($payload, ['idempotency_key' => $key])->assertOk()->assertJsonPath('replayed', false);
    settle($payload, ['idempotency_key' => $key])->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('status', 'completed');

    expect(Payment::where('parking_session_id', $session->id)->count())->toBe(1);
});

it('rejects reuse of an exit idempotency key for another parking session', function () {
    [$firstSession, $firstPayload] = recordEntry($this->staff);
    [$secondSession, $secondPayload] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $this->actingAs($this->staff);

    settle($firstPayload, ['idempotency_key' => $key])->assertOk()
        ->assertJsonPath('status', 'completed');

    settle($secondPayload, ['idempotency_key' => $key])->assertStatus(409)
        ->assertJsonPath('error_code', 'idempotency_conflict');

    expect(Payment::where('parking_session_id', $firstSession->id)->count())->toBe(1)
        ->and(Payment::where('parking_session_id', $secondSession->id)->count())->toBe(0)
        ->and($secondSession->refresh()->status)->toBe(ParkingSession::ACTIVE)
        ->and($secondSession->qrTicket->status)->toBe(QrTicket::ACTIVE)
        ->and($secondSession->receipt)->toBeNull();
});

it('rejects an invalid QR when replaying an existing exit payment key', function () {
    [$session, $payload] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $this->actingAs($this->staff);

    settle($payload, ['idempotency_key' => $key])->assertOk();
    settle('SPT1-nothex', ['idempotency_key' => $key])->assertNotFound();

    expect(Payment::where('parking_session_id', $session->id)->count())->toBe(1)
        ->and(Receipt::where('parking_session_id', $session->id)->count())->toBe(1);
});

it('rejects cross-session replay of a pending online payment', function () {
    [$firstSession, $firstPayload] = recordEntry($this->staff);
    [$secondSession, $secondPayload] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $this->actingAs($this->staff);

    settle($firstPayload, [
        'idempotency_key' => $key,
        'payment_method' => 'online',
    ])->assertStatus(202)->assertJsonPath('status', 'payment_pending');

    settle($secondPayload, ['idempotency_key' => $key])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'idempotency_conflict');

    expect(Payment::where('parking_session_id', $firstSession->id)->count())->toBe(1)
        ->and(Payment::where('parking_session_id', $firstSession->id)->value('status'))->toBe(Payment::PENDING)
        ->and(Payment::where('parking_session_id', $secondSession->id)->count())->toBe(0)
        ->and($secondSession->refresh()->status)->toBe(ParkingSession::ACTIVE)
        ->and($secondSession->receipt)->toBeNull();
});

it('rejects insufficient cash and keeps the session open', function () {
    [$session, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);

    settle($payload, ['tendered_minor' => 100])->assertUnprocessable()->assertJsonPath('error_code', 'insufficient_tender');
    expect($session->refresh()->status)->toBe('active')->and(Payment::count())->toBe(0);
});

it('requires a reference for card payments', function () {
    [, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);

    settle($payload, ['payment_method' => 'card'])->assertUnprocessable()->assertJsonPath('error_code', 'reference_required');
    settle($payload, ['payment_method' => 'card', 'external_reference' => 'POS-4411'])->assertOk();
});

it('blocks manual confirmation of pending online payments without gateway verification', function () {
    [$session, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);

    $pending = settle($payload, ['payment_method' => 'online'])->assertStatus(202)
        ->assertJsonPath('status', 'payment_pending')
        ->assertJsonPath('exit_authorized', false)
        ->assertJsonPath('gate', null);
    expect($session->refresh()->status)->toBe('active');

    settle($payload)->assertStatus(409)->assertJsonPath('error_code', 'payment_pending');

    $paymentId = $pending->json('payment.id');

    $this->postJson("/api/v1/staff/payments/{$paymentId}/confirm", ['external_reference' => 'GW-1'])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'gateway_verification_required');

    expect(Payment::findOrFail($paymentId)->status)->toBe(Payment::PENDING)
        ->and($session->refresh()->status)->toBe(ParkingSession::ACTIVE)
        ->and($session->receipt)->toBeNull()
        ->and($session->qrTicket->status)->toBe(QrTicket::ACTIVE);
});

it('keeps the session open when an online payment fails', function () {
    [$session, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);
    $id = settle($payload, ['payment_method' => 'online'])->json('payment.id');

    $this->postJson("/api/v1/staff/payments/{$id}/fail", ['reason' => 'Declined'])->assertOk()->assertJsonPath('data.status', 'failed');
    expect($session->refresh()->status)->toBe('active');

    settle($payload)->assertOk()->assertJsonPath('status', 'completed');
});

it('rejects prepayment retries that change amount or payment method', function () {
    [$session] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $url = "/api/v1/staff/sessions/{$session->uuid}/payments";
    $input = ['idempotency_key' => $key, 'payment_method' => 'cash', 'amount_minor' => 900000];
    $this->actingAs($this->staff);

    $this->postJson($url, $input)->assertCreated();
    $this->postJson($url, $input)->assertSuccessful();
    $this->postJson($url, array_merge($input, ['amount_minor' => 800000]))
        ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
    $this->postJson($url, array_merge($input, ['payment_method' => 'card']))
        ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');

    expect(Payment::where('parking_session_id', $session->id)->count())->toBe(1)
        ->and(Payment::where('parking_session_id', $session->id)->value('amount_minor'))->toBe(900000);
});

it('turns unused prepaid weekly time into customer credit on early exit', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0770000002']);
    [$session, $payload] = recordEntry($this->staff, [
        'parking_zone_id' => zone('C')->id, 'parking_package_id' => package('weekly')->id, 'customer_phone' => '0770000002',
    ]);
    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/payments", [
        'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'amount_minor' => 900000,
    ])->assertCreated();

    $this->travel(2)->days();
    settle($payload, ['payment_method' => null])->assertOk()->assertJsonPath('receipt.credit_issued_minor', 900000 - 257143);

    expect(CustomerCredit::balanceFor($customer->id))->toBe(642857);
    $this->actingAs($customer)->getJson('/api/v1/customer/credits')->assertJsonPath('balance_minor', 642857);
});

it('creates a pending refund when refund is chosen above the auto-approve limit', function () {
    [$session, $payload] = recordEntry($this->staff, ['parking_zone_id' => zone('C')->id, 'parking_package_id' => package('weekly')->id]);
    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/payments", [
        'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'amount_minor' => 900000,
    ]);
    $this->travel(1)->days();

    settle($payload, ['overpayment_resolution' => 'refund'])->assertOk();
    $refund = Refund::firstOrFail();
    expect($refund->status)->toBe(Refund::PENDING);

    $admin = userWithRole(User::ROLE_ADMIN);
    $this->actingAs($admin)->postJson("/api/v1/admin/refunds/{$refund->id}/decision", ['decision' => 'approve'])->assertOk()->assertJsonPath('data.status', 'approved');
    $this->actingAs($admin)->postJson("/api/v1/admin/refunds/{$refund->id}/decision", ['decision' => 'approve'])->assertStatus(409);
});

it('holds the slot until the vehicle leaves when release is set to the gate', function () {
    app(SettingsService::class)->set('exit.release_slot_on', 'gate', userWithRole(User::ROLE_SUPER_ADMIN));
    [$session, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff);

    settle($payload)->assertOk();
    expect($session->refresh()->status)->toBe(ParkingSession::EXIT_AUTHORIZED)
        ->and($session->slot->status)->toBe('occupied');

    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/confirm-left")->assertOk()->assertJsonPath('data.status', 'completed');
    expect($session->slot->refresh()->status)->toBe('available');
});

it('produces a financial report from paid payments only', function () {
    [, $p1] = recordEntry($this->staff);
    [, $p2] = recordEntry($this->staff);
    $this->actingAs($this->staff);
    settle($p1)->assertOk();
    settle($p2, ['payment_method' => 'online'])->assertStatus(202);

    $this->actingAs(userWithRole(User::ROLE_AUDITOR))->getJson('/api/v1/reports/financial')
        ->assertOk()
        ->assertJsonPath('data.revenue_minor', 150000)
        ->assertJsonPath('data.payment_count', 1)
        ->assertJsonPath('data.pending_payments', 1);
});
