<?php

use App\Models\AuditLog;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReservationPeriod;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\ZoneType;
use App\Services\ReservationService;
use App\Services\SettingsService;
use App\Services\SlotAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
    $this->admin = userWithRole(User::ROLE_ADMIN);
    $this->customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0771119999']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00', 'Asia/Colombo'));
});

function monthlyEntry(User $staff, array $overrides = []): array
{
    return recordEntry($staff, array_merge([
        'plate_number' => 'WP CAR-0001', 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id, 'customer_phone' => '0771119999',
    ], $overrides));
}

function weeklyEntry(User $staff, array $overrides = []): array
{
    return recordEntry($staff, array_merge([
        'plate_number' => 'WP CAR-0002', 'parking_zone_id' => zone('C')->id, 'parking_package_id' => package('weekly')->id,
    ], $overrides));
}

function slotStatus(int $id): string
{
    return ParkingSlot::find($id)->status;
}

/* ---------------- creation & validity ---------------- */

it('creates a monthly reservation of exactly 30 days on first entry', function () {
    [$session, $payload] = monthlyEntry($this->staff);
    $reservation = $session->reservation;

    expect($reservation)->not->toBeNull()
        ->and($reservation->status)->toBe(ParkingReservation::ACTIVE)
        ->and($reservation->customer_id)->toBe($this->customer->id)
        ->and($reservation->parking_slot_id)->toBe($session->parking_slot_id)
        ->and($reservation->active_vehicle_key)->toBe($session->vehicle_id)
        ->and($reservation->active_slot_key)->toBe($session->parking_slot_id)
        ->and($reservation->starts_at->diffInMinutes($reservation->expires_at))->toEqual(43200)
        ->and($session->package_ends_at->equalTo($reservation->expires_at))->toBeTrue()
        ->and(slotStatus($session->parking_slot_id))->toBe('occupied')
        ->and($payload)->toStartWith('SPT1-');

    $period = $reservation->periods()->first();
    expect($period->kind)->toBe('initial')->and($period->price_minor)->toBe(3000000)->and($period->billed_session_id)->toBe($session->id);
    expect(AuditLog::where('action', 'reservation.created')->exists())->toBeTrue();
});

it('creates a weekly reservation of exactly 7 days', function () {
    [$session] = weeklyEntry($this->staff);
    $r = $session->reservation;

    expect($r->starts_at->diffInMinutes($r->expires_at))->toEqual(10080);
});

it('does not reserve slots for daily or short-time parking', function () {
    [$daily, $dailyQr] = recordEntry($this->staff, ['plate_number' => 'DAY-1']);
    [$short, $shortQr] = recordEntry($this->staff, ['plate_number' => 'SHORT-1', 'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);
    expect($daily->parking_reservation_id)->toBeNull()->and($short->parking_reservation_id)->toBeNull();

    $this->actingAs($this->staff);
    settle($dailyQr)->assertOk();
    settle($shortQr)->assertOk();
    expect(slotStatus($daily->parking_slot_id))->toBe('available')->and(slotStatus($short->parking_slot_id))->toBe('available')
        ->and(ParkingReservation::count())->toBe(0);
});

/* ---------------- temporary exit & re-entry ---------------- */

it('keeps the slot reserved after a temporary exit and gives it back on re-entry without a new base charge', function () {
    [$first, $qr1] = monthlyEntry($this->staff);
    $slotId = $first->parking_slot_id;
    $expires = $first->reservation->expires_at;
    $this->actingAs($this->staff);

    // First visit carries the full monthly price once.
    $this->travel(3)->hours();
    $settled = settle($qr1)->assertOk();
    expect($settled->json('receipt.total_minor'))->toBe(3000000)
        ->and($settled->json('receipt.credit_issued_minor'))->toBe(0)
        ->and(slotStatus($slotId))->toBe('reserved')
        ->and($first->reservation->fresh()->status)->toBe('active');

    // Another car cannot take the reserved slot.
    $this->postJson('/api/v1/staff/entries', [
        'plate_number' => 'OTHER-9', 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('D')->id,
        'parking_package_id' => package('monthly')->id, 'parking_slot_id' => $slotId,
    ])->assertStatus(409)->assertJsonPath('error_code', 'no_slot_available');

    // Re-entry, ten days later: same slot, same expiry, base price not charged again.
    $this->travel(10)->days();
    [$second, $qr2] = monthlyEntry($this->staff);
    expect($second->parking_slot_id)->toBe($slotId)
        ->and($second->parking_reservation_id)->toBe($first->parking_reservation_id)
        ->and($second->reservation->expires_at->equalTo($expires))->toBeTrue()
        ->and($second->package_ends_at->equalTo($expires))->toBeTrue()
        ->and(slotStatus($slotId))->toBe('occupied')
        ->and($qr2)->not->toBe($qr1);

    $this->travel(5)->hours();
    $bill = $this->getJson("/api/v1/staff/sessions/{$second->uuid}/bill")->assertOk()->json('data');
    expect($bill['total_payable_minor'])->toBe(0)->and($bill['base_charge_minor'])->toBe(0);
    settle($qr2, ['payment_method' => null])->assertOk()->assertJsonPath('receipt.total_minor', 0);
    expect(slotStatus($slotId))->toBe('reserved')->and(Payment::where('status', 'paid')->sum('amount_minor'))->toEqual(3000000);

    // The old QR from visit one stays used.
    settle($qr1)->assertStatus(409);
});

it('supports unlimited entry/exit cycles during validity', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $slotId = $s->parking_slot_id;
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    foreach (range(1, 4) as $visit) {
        $this->travel(1)->days();
        [$again, $qr] = weeklyEntry($this->staff);
        expect($again->parking_slot_id)->toBe($slotId);
        settle($qr, ['payment_method' => null])->assertOk();
        expect(slotStatus($slotId))->toBe('reserved');
    }
    expect(ParkingSession::where('parking_reservation_id', $s->parking_reservation_id)->count())->toBe(5)
        ->and(ReservationPeriod::count())->toBe(1);
});

it('rejects duplicate active entries and mismatched packages for reservation holders', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'WP CAR-0001', 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id,
    ])->assertStatus(409)->assertJsonPath('error_code', 'duplicate_active_entry');

    settle($qr)->assertOk();
    $this->postJson('/api/v1/staff/entries', [
        'plate_number' => 'WP CAR-0001', 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertStatus(409)->assertJsonPath('error_code', 'reservation_package_mismatch')->assertJsonPath('context.slot', $s->slot->code);

    $this->getJson('/api/v1/staff/vehicles/lookup?plate=WP CAR-0001')->assertOk()
        ->assertJsonPath('active_reservation.slot', $s->slot->code)->assertJsonPath('active_reservation.is_valid', true);
});

/* ---------------- gate-confirmed departure ---------------- */

it('only reserves the slot after gate-confirmed departure', function () {
    app(SettingsService::class)->set('exit.release_slot_on', 'gate', userWithRole(User::ROLE_SUPER_ADMIN));
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);

    settle($qr)->assertOk();
    expect(slotStatus($s->parking_slot_id))->toBe('occupied')->and($s->fresh()->status)->toBe('exit_authorized');

    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/confirm-left")->assertOk();
    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/confirm-left")->assertStatus(409);
    expect(slotStatus($s->parking_slot_id))->toBe('reserved');
});

/* ---------------- expiry ---------------- */

it('expires a vacant reservation after its validity and releases the slot', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    $this->travel(7 * 1440 - 1)->minutes();
    $this->artisan('parking:expire-reservations')->assertSuccessful();
    expect($s->reservation->fresh()->status)->toBe('active')->and(slotStatus($s->parking_slot_id))->toBe('reserved');

    $this->travel(1)->minutes();
    $this->artisan('parking:expire-reservations')->assertSuccessful();
    $this->artisan('parking:expire-reservations')->assertSuccessful(); // idempotent
    $r = $s->reservation->fresh();
    expect($r->status)->toBe('expired')->and($r->active_vehicle_key)->toBeNull()->and($r->active_slot_key)->toBeNull()
        ->and(slotStatus($s->parking_slot_id))->toBe('available');
});

it('never releases the slot of a vehicle still inside at expiry, and charges overtime from expiry', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);

    $this->travel(7 * 1440 + 120)->minutes(); // 2h past expiry, vehicle still inside
    $this->artisan('parking:expire-reservations')->assertSuccessful();
    expect($s->reservation->fresh()->status)->toBe('expired')->and(slotStatus($s->parking_slot_id))->toBe('occupied');

    $quote = $this->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $qr])->assertOk()->json('quote');
    expect(collect($quote['lines'])->firstWhere('code', 'overtime')['amount_minor'])->toBe(20000)
        ->and($quote['total_minor'])->toBe(900000 + 20000);

    settle($qr)->assertOk();
    expect(slotStatus($s->parking_slot_id))->toBe('available');
});

it('blocks re-entry on an expired reservation and starts a fresh purchase, preferring the previous slot', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();
    $this->travel(8)->days(); // scheduler has NOT run yet

    [$new] = weeklyEntry($this->staff);
    expect($new->parking_reservation_id)->not->toBe($s->parking_reservation_id)
        ->and($s->reservation->fresh()->status)->toBe('expired')
        ->and($new->parking_slot_id)->toBe($s->parking_slot_id)
        ->and($new->reservation->periods()->first()->price_minor)->toBe(900000);
});

it('releases at departure if the reservation expired but the scheduler had not run', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    $this->travel(8)->days();

    settle($qr)->assertOk();
    expect(slotStatus($s->parking_slot_id))->toBe('available')->and($s->reservation->fresh()->status)->toBe('expired');
});

/* ---------------- renewal ---------------- */

it('renews from the current expiry, keeps the slot and bills the renewal on the next visit', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();
    $oldExpiry = $s->reservation->expires_at;

    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertCreated()
        ->assertJsonPath('renewal.status', 'pending')->assertJsonPath('renewal.billed_on', 'next visit');
    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertStatus(409)->assertJsonPath('error_code', 'renewal_pending');

    $r = $s->reservation->fresh();
    expect($r->expires_at->equalTo($oldExpiry->addMinutes(43200)))->toBeTrue()->and($r->parking_slot_id)->toBe($s->parking_slot_id);

    [$again, $qr2] = monthlyEntry($this->staff);
    expect($again->parking_slot_id)->toBe($s->parking_slot_id);
    settle($qr2)->assertOk()->assertJsonPath('receipt.total_minor', 3000000);

    $this->travel(40)->days(); // within the renewed period
    $this->artisan('parking:expire-reservations');
    expect($r->fresh()->status)->toBe('active');
});

it('bills a renewal on the visit in progress when the vehicle is inside', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertCreated()->assertJsonPath('renewal.billed_on', 'current visit');

    expect($s->fresh()->package_ends_at->equalTo($s->reservation->fresh()->expires_at))->toBeTrue();
    settle($qr)->assertOk()->assertJsonPath('receipt.total_minor', 1800000);
});

it('refuses renewal of expired reservations and while a payment is pending', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr, ['payment_method' => 'online'])->assertStatus(202);
    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertStatus(409)->assertJsonPath('error_code', 'payment_pending');

    $this->travel(8)->days();
    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertStatus(409)->assertJsonPath('error_code', 'reservation_not_renewable');
});

/* ---------------- cancellation ---------------- */

it('cancels a vacant reservation, releases the slot and only refunds on explicit admin request', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    $this->actingAs($this->staff)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'Moving away'])->assertForbidden();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'Moving away', 'refund_minor' => 9999999])
        ->assertUnprocessable()->assertJsonPath('error_code', 'refund_exceeds_paid');
    expect($s->reservation->fresh()->status)->toBe('active');

    $this->actingAs($this->admin)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'Moving away', 'refund_minor' => 1500000])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(slotStatus($s->parking_slot_id))->toBe('available')
        ->and(Refund::first()->status)->toBe(Refund::PENDING)->and(Refund::first()->amount_minor)->toBe(1500000)
        ->and(AuditLog::where('action', 'reservation.cancelled')->exists())->toBeTrue();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'again'])->assertStatus(409);
});

it('keeps the vehicle physically parked when cancelled while inside', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'Customer request'])->assertOk();

    expect(slotStatus($s->parking_slot_id))->toBe('occupied')->and($s->fresh()->status)->toBe('active');
    $this->actingAs($this->staff);
    settle($qr)->assertOk()->assertJsonPath('receipt.total_minor', 3000000);
    expect(slotStatus($s->parking_slot_id))->toBe('available');
});

it('voids a not-yet-started renewal when the reservation is cancelled', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();
    $this->postJson("/api/v1/staff/reservations/{$s->parking_reservation_id}/renew")->assertCreated();

    $this->actingAs($this->admin)->postJson("/api/v1/admin/reservations/{$s->parking_reservation_id}/cancel", ['reason' => 'Changed mind'])->assertOk();
    expect(ReservationPeriod::where('kind', 'renewal')->value('status'))->toBe('void');
});

/* ---------------- payments, safety, slot protection ---------------- */

it('handles prepayment of the monthly package without credit on temporary exit', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$s->uuid}/payments", [
        'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'amount_minor' => 3000000,
    ])->assertCreated();

    $this->travel(1)->days();
    settle($qr, ['payment_method' => null])->assertOk()
        ->assertJsonPath('receipt.total_minor', 3000000)->assertJsonPath('receipt.credit_issued_minor', 0)->assertJsonPath('receipt.refund_minor', 0);
});

it('keeps per-visit safety charges for guaranteed parking in the safety zone', function () {
    package('monthly')->zoneTypes()->syncWithoutDetaching([ZoneType::where('code', 'safety')->value('id')]);
    [$s, $qr] = monthlyEntry($this->staff, ['parking_zone_id' => zone('E')->id]);
    $this->actingAs($this->staff);
    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/inspections", ['type' => 'exit', 'condition' => 'good'])->assertCreated();

    $lines = collect(settle($qr)->assertOk()->json('quote.lines'))->pluck('amount_minor', 'code');
    expect($lines['package'])->toBe(3000000)->and($lines['safety'])->toBe(50000);
    expect(slotStatus($s->parking_slot_id))->toBe('reserved');
});

it('stops admins changing a reserved slot or the vehicle category while the reservation is active', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    $this->actingAs($this->admin)->patchJson("/api/v1/admin/slots/{$s->parking_slot_id}/status", ['status' => 'available'])
        ->assertStatus(409)->assertJsonPath('error_code', 'slot_reserved_for_customer');
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/vehicles/{$s->vehicle_id}", ['vehicle_category_id' => category('van')->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_has_reservation');
    $this->actingAs($this->admin)->putJson('/api/v1/admin/zones/'.zone('D')->id.'/capacity', ['vehicle_category_id' => category('car')->id, 'capacity' => 0])
        ->assertUnprocessable()->assertJsonPath('error_code', 'capacity_below_occupied');
});

it('reports reserved slots in occupancy', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    $zoneD = collect($this->actingAs($this->admin)->getJson('/api/v1/reports/occupancy')->json('data'))->firstWhere('code', 'D');
    expect(collect($zoneD['by_vehicle_category'])->firstWhere('vehicle_category', 'car'))->toMatchArray(['reserved' => 1, 'occupied' => 0, 'available' => 7]);
});

it('lets packages be configured for guaranteed parking, but not hourly ones', function () {
    $this->actingAs($this->admin)->patchJson('/api/v1/admin/packages/'.package('night')->id, ['guarantees_slot' => true])->assertOk()->assertJsonPath('data.guarantees_slot', true);
    $this->actingAs($this->admin)->patchJson('/api/v1/admin/packages/'.package('short_time')->id, ['guarantees_slot' => true])->assertUnprocessable();
});

/* ---------------- API authorization ---------------- */

it('enforces reservation API permissions and customer ownership', function () {
    [$s] = monthlyEntry($this->staff);
    $id = $s->parking_reservation_id;
    $other = userWithRole(User::ROLE_CUSTOMER);

    $this->actingAs($this->staff)->getJson('/api/v1/staff/reservations?status=active')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.vehicle_on_site', true);
    $this->actingAs($this->staff)->getJson("/api/v1/staff/reservations/{$id}")->assertOk()->assertJsonCount(1, 'visits');
    $this->actingAs($this->customer)->getJson('/api/v1/customer/reservations')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.customer_id');
    $this->actingAs($this->customer)->getJson("/api/v1/customer/reservations/{$id}")->assertOk();
    $this->actingAs($other)->getJson("/api/v1/customer/reservations/{$id}")->assertForbidden();
    $this->actingAs($other)->getJson('/api/v1/customer/reservations')->assertJsonCount(0, 'data');
    $this->actingAs($this->customer)->getJson('/api/v1/staff/reservations')->assertForbidden();
    $this->actingAs($this->customer)->postJson("/api/v1/staff/reservations/{$id}/renew")->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_AUDITOR))->postJson("/api/v1/admin/reservations/{$id}/cancel", ['reason' => 'x x x'])->assertForbidden();
});

/* ---------------- service-level race guards ---------------- */

it('only one claimant can turn a reserved slot back to occupied', function () {
    [$s, $qr] = monthlyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();
    $allocator = app(SlotAllocator::class);

    expect($allocator->claimReserved($s->parking_slot_id))->toBeTrue()
        ->and($allocator->claimReserved($s->parking_slot_id))->toBeFalse();
});

it('database keys forbid two active reservations for one slot or vehicle', function () {
    [$s] = monthlyEntry($this->staff);
    $r = $s->reservation;

    expect(fn () => ParkingReservation::create(array_merge($r->only(['customer_id', 'parking_zone_id', 'parking_package_id', 'package_rate_id', 'starts_at', 'expires_at', 'pricing_snapshot']), [
        'vehicle_id' => Vehicle::factory()->create()->id, 'parking_slot_id' => $r->parking_slot_id, 'status' => 'active',
        'active_vehicle_key' => 999999, 'active_slot_key' => $r->parking_slot_id,
    ])))->toThrow(UniqueConstraintViolationException::class);
});

it('expiry and re-entry racing on the same instant never strand the slot', function () {
    [$s, $qr] = weeklyEntry($this->staff);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();
    $this->travel(7)->days(); // exactly at expiry

    // Re-entry at the expiry instant is a new purchase; the old reservation is expired exactly once.
    [$new] = weeklyEntry($this->staff);
    expect(app(ReservationService::class)->expire($s->reservation->fresh(), now()))->toBeFalse()
        ->and($new->parking_slot_id)->toBe($s->parking_slot_id)
        ->and(slotStatus($new->parking_slot_id))->toBe('occupied')
        ->and(ParkingReservation::where('status', 'active')->count())->toBe(1);
});
