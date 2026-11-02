<?php

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PenaltyType;
use App\Models\SessionPenalty;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
    $this->admin = userWithRole(User::ROLE_ADMIN);
    $this->lost = PenaltyType::where('code', 'lost_ticket')->firstOrFail();
});

function applyPenalty(User $by, $session, PenaltyType $type, string $reason = 'Customer lost the ticket'): TestResponse
{
    return test()->actingAs($by)->postJson("/api/v1/staff/sessions/{$session->uuid}/penalties", ['penalty_type_id' => $type->id, 'reason' => $reason]);
}

it('applies a penalty with reason, actor and time, and shows it separately from overtime on the bill', function () {
    $this->freezeSecond();
    [$session, $payload] = recordEntry($this->staff, ['plate_number' => 'WP CAB-1234', 'fuel_type' => 'diesel']);
    $this->travel(25)->hours();

    applyPenalty($this->staff, $session, $this->lost)->assertCreated()
        ->assertJsonPath('data.amount_minor', 50000)
        ->assertJsonPath('data.applied_by', $this->staff->id)
        ->assertJsonPath('data.status', 'applied');

    $bill = $this->actingAs($this->staff)->getJson("/api/v1/staff/sessions/{$session->uuid}/bill")->assertOk()->json('data');
    expect($bill)->toMatchArray([
        'vehicle_number' => 'WPCAB1234',
        'vehicle_category' => 'car',
        'fuel_type' => 'diesel',
        'zone' => 'B',
        'duration_minutes' => 1500,
        'duration_human' => '1d 1h 0m',
        'base_charge_minor' => 150000,
        'overtime_charge_minor' => 10000,
        'penalty_charge_minor' => 50000,
        'safety_charge_minor' => 0,
        'tax_minor' => 0,
        'total_payable_minor' => 210000,
        'amount_paid_minor' => 0,
        'balance_minor' => 210000,
    ]);
    expect(collect($bill['lines'])->pluck('code')->all())->toBe(['package', 'overtime', 'penalty']);

    $settled = settle($payload, ['tendered_minor' => 210000])->assertOk();
    $receiptBill = $settled->json('receipt.breakdown.bill');
    expect($receiptBill['total_payable_minor'])->toBe(210000)
        ->and($receiptBill['payment_methods'])->toBe(['cash'])
        ->and($receiptBill['receipt_number'])->toBe($settled->json('receipt.number'))
        ->and($receiptBill['balance_minor'])->toBe(0);

    // After settlement the stored bill is returned unchanged.
    $this->travel(5)->hours();
    $this->getJson("/api/v1/staff/sessions/{$session->uuid}/bill")->assertJsonPath('data.total_payable_minor', 210000)->assertJsonPath('data.status', 'completed');
});

it('prevents duplicate penalties, including concurrent ones', function () {
    [$session] = recordEntry($this->staff);
    applyPenalty($this->staff, $session, $this->lost)->assertCreated();
    applyPenalty($this->staff, $session, $this->lost)->assertStatus(409)->assertJsonPath('error_code', 'duplicate_penalty');

    // Database-level guard: even bypassing the service, a second active row is rejected.
    expect(fn () => SessionPenalty::create([
        'parking_session_id' => $session->id, 'penalty_type_id' => $this->lost->id, 'amount_minor' => 1, 'reason' => 'x',
        'applied_at' => now(), 'active_key' => $session->id.':'.$this->lost->id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // A different penalty type is fine.
    applyPenalty($this->staff, $session, PenaltyType::where('code', 'rule_violation')->first())->assertCreated();
});

it('lets only admins waive a penalty with a reason, removing it from the bill but keeping the record', function () {
    [$session] = recordEntry($this->staff);
    $id = applyPenalty($this->staff, $session, $this->lost)->json('data.id');

    $this->actingAs($this->staff)->postJson("/api/v1/admin/penalties/{$id}/waive", ['reason' => 'Found it'])->assertForbidden();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/penalties/{$id}/waive", [])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/penalties/{$id}/waive", ['reason' => 'Ticket found in car'])
        ->assertOk()->assertJsonPath('data.status', 'waived')->assertJsonPath('data.waived_by', $this->admin->id);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/penalties/{$id}/waive", ['reason' => 'again'])->assertStatus(409);

    expect($this->actingAs($this->staff)->getJson("/api/v1/staff/sessions/{$session->uuid}/bill")->json('data.penalty_charge_minor'))->toBe(0);
    expect(SessionPenalty::find($id))->not->toBeNull();

    // After a waiver the same penalty can legitimately be applied again.
    applyPenalty($this->staff, $session, $this->lost, 'Lost again')->assertCreated();
    expect(AuditLog::whereIn('action', ['penalty.applied', 'penalty.waived'])->count())->toBe(3);
});

it('freezes penalties once the session is closed or a payment is pending', function () {
    [$session, $payload] = recordEntry($this->staff);
    $id = applyPenalty($this->staff, $session, $this->lost)->json('data.id');

    settle($payload, ['payment_method' => 'online'])->assertStatus(202);
    applyPenalty($this->staff, $session, PenaltyType::where('code', 'rule_violation')->first())->assertStatus(409)->assertJsonPath('error_code', 'payment_pending');
    $this->actingAs($this->admin)->postJson("/api/v1/admin/penalties/{$id}/waive", ['reason' => 'x x x'])->assertStatus(409);

    [$closed, $closedPayload] = recordEntry($this->staff);
    settle($closedPayload)->assertOk();
    applyPenalty($this->staff, $closed, $this->lost)->assertStatus(409)->assertJsonPath('error_code', 'invalid_session_state');
});

it('keeps applied penalty amounts when the catalogue price changes', function () {
    [$session] = recordEntry($this->staff);
    applyPenalty($this->staff, $session, $this->lost);

    $this->actingAs($this->admin)->patchJson("/api/v1/admin/penalty-types/{$this->lost->id}", ['amount_minor' => 99900])->assertOk();
    $this->actingAs($this->staff)->patchJson("/api/v1/admin/penalty-types/{$this->lost->id}", ['amount_minor' => 1])->assertForbidden();

    expect($this->actingAs($this->staff)->getJson("/api/v1/staff/sessions/{$session->uuid}/bill")->json('data.penalty_charge_minor'))->toBe(50000);
});

it('does not apply penalties from inactive types and validates input', function () {
    [$session] = recordEntry($this->staff);
    $this->lost->update(['is_active' => false]);

    applyPenalty($this->staff, $session, $this->lost)->assertUnprocessable()->assertJsonPath('error_code', 'penalty_type_inactive');
    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/penalties", ['penalty_type_id' => 999])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson('/api/v1/admin/penalty-types', ['code' => 'late', 'name' => 'Late', 'amount_minor' => 0])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson('/api/v1/admin/penalty-types', ['code' => 'damage_fee', 'name' => 'Damage fee', 'amount_minor' => 250000])->assertCreated();
});

it('reports penalties in the financial report', function () {
    [$s1] = recordEntry($this->staff);
    [$s2] = recordEntry($this->staff);
    applyPenalty($this->staff, $s1, $this->lost);
    $id = applyPenalty($this->staff, $s2, $this->lost)->json('data.id');
    $this->actingAs($this->admin)->postJson("/api/v1/admin/penalties/{$id}/waive", ['reason' => 'Goodwill']);

    $this->actingAs(userWithRole(User::ROLE_AUDITOR))->getJson('/api/v1/reports/financial')->assertOk()
        ->assertJsonPath('data.penalties.applied_count', 1)
        ->assertJsonPath('data.penalties.applied_minor', 50000)
        ->assertJsonPath('data.penalties.waived_count', 1)
        ->assertJsonPath('data.penalties.by_type.0.code', 'lost_ticket');
});

it('lets customers see their own bill only', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0770001111']);
    [$session] = recordEntry($this->staff, ['customer_phone' => '0770001111']);

    $this->actingAs($customer)->getJson("/api/v1/customer/sessions/{$session->uuid}/bill")->assertOk()->assertJsonPath('data.vehicle_category', 'car');
    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->getJson("/api/v1/customer/sessions/{$session->uuid}/bill")->assertForbidden();
});

it('rejects an exit retry that reuses an idempotency key with a different payment method', function () {
    [$session, $payload] = recordEntry($this->staff);
    $key = (string) Str::uuid();
    $this->actingAs($this->staff);

    settle($payload, ['idempotency_key' => $key, 'payment_method' => 'cash'])->assertOk();
    settle($payload, ['idempotency_key' => $key, 'payment_method' => 'card', 'external_reference' => 'POS-1'])
        ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
    settle($payload, ['idempotency_key' => $key, 'payment_method' => 'cash'])->assertOk()->assertJsonPath('replayed', true);

    expect(Payment::where('parking_session_id', $session->id)->count())->toBe(1);
});

it('records penalty-inclusive balance when part was prepaid', function () {
    [$session, $payload] = recordEntry($this->staff);
    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/payments", [
        'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'amount_minor' => 100000,
    ])->assertCreated();
    applyPenalty($this->staff, $session, $this->lost);

    $bill = $this->getJson("/api/v1/staff/sessions/{$session->uuid}/bill")->json('data');
    expect($bill['total_payable_minor'])->toBe(200000)->and($bill['amount_paid_minor'])->toBe(100000)->and($bill['balance_minor'])->toBe(100000);

    settle($payload)->assertOk()->assertJsonPath('payment.amount_minor', 100000);
});
