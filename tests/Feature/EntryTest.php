<?php

use App\Models\AuditLog;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\QrTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SlotAllocator;
use App\Services\ZoneService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
});

it('records an entry, allocates a slot and issues a secure QR ticket', function () {
    $response = $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'wp cab-1234',
        'vehicle_category_id' => category('car')->id,
        'parking_zone_id' => zone('B')->id,
        'parking_package_id' => package('daily')->id,
        'customer_name' => 'Walk-in', 'customer_phone' => '0771234567',
    ])->assertCreated();

    $session = ParkingSession::where('uuid', $response->json('data.uuid'))->first();
    expect($session->status)->toBe('active')
        ->and($session->slot->status)->toBe(ParkingSlot::OCCUPIED)
        ->and($session->vehicle->identifier)->toBe('WPCAB1234')
        ->and($session->package_ends_at->diffInMinutes($session->entry_at, true))->toEqual(1440);

    $payload = $response->json('ticket.qr_payload');
    expect($payload)->toStartWith('SPT1-')->not->toContain('WPCAB1234')
        ->and($response->json('ticket.qr_svg'))->toContain('<svg');

    $ticket = QrTicket::first();
    expect($ticket->token_hash)->toBe(hash('sha256', substr($payload, 5)))
        ->and(DB::table('qr_tickets')->value('token_encrypted'))->not->toContain(substr($payload, 5));
});

it('prevents duplicate active entries for the same vehicle', function () {
    recordEntry($this->staff, ['plate_number' => 'CAB-1234']);

    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'cab 1234', 'vehicle_category_id' => category('car')->id,
        'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id,
    ])->assertStatus(409)->assertJsonPath('error_code', 'duplicate_active_entry');
});

it('reports a full zone and allocates nothing more', function () {
    app(ZoneService::class)->setCapacity(zone('B'), category('van'), 2);
    recordEntry($this->staff, ['vehicle_category_id' => category('van')->id]);
    recordEntry($this->staff, ['vehicle_category_id' => category('van')->id]);

    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'VAN-9999', 'vehicle_category_id' => category('van')->id,
        'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertStatus(409)->assertJsonPath('error_code', 'no_slot_available');

    $availability = collect($this->actingAs($this->staff)->getJson('/api/v1/staff/availability?vehicle_category_id='.category('van')->id)->json('data'))->firstWhere('code', 'B');
    expect($availability['is_full'])->toBeTrue();
});

it('enforces zone vehicle category and package restrictions', function () {
    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'vehicle_category_id' => category('bicycle')->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertUnprocessable()->assertJsonPath('error_code', 'category_not_allowed');

    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => 'CAB-1', 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('monthly')->id,
    ])->assertUnprocessable()->assertJsonPath('error_code', 'package_zone_mismatch');

    $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->assertUnprocessable()->assertJsonPath('error_code', 'plate_required');
});

it('gives plate-less bicycles a unique vehicle reference', function () {
    [$session] = recordEntry($this->staff, [
        'plate_number' => null, 'vehicle_category_id' => category('bicycle')->id,
        'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id,
    ]);

    expect($session->vehicle->is_reference)->toBeTrue()->and($session->vehicle->identifier)->toStartWith('BICY-');
});

it('only lets one concurrent claimant take a slot (compare-and-set)', function () {
    $slot = ParkingSlot::where('status', 'available')->first();
    $allocator = app(SlotAllocator::class);

    expect($allocator->claim($slot))->toBeTrue()
        ->and($allocator->claim($slot))->toBeFalse();
});

it('skips a slot taken by a concurrent transaction and allocates the next one', function () {
    $zone = zone('B');
    $car = category('car');
    $first = ParkingSlot::where('parking_zone_id', $zone->id)->where('vehicle_category_id', $car->id)->orderBy('code')->first();

    // Simulate another worker winning the race right after our SELECT: the first CAS fails, retry picks the next slot.
    $allocator = new class extends SlotAllocator
    {
        public bool $raced = false;

        public function claim(ParkingSlot $slot): bool
        {
            if (! $this->raced) {
                $this->raced = true;
                ParkingSlot::whereKey($slot->id)->update(['status' => 'occupied']);

                return false;
            }

            return parent::claim($slot);
        }
    };

    $slot = DB::transaction(fn () => $allocator->allocate($zone, $car->id));
    expect($slot->id)->not->toBe($first->id)->and($slot->status)->toBe('occupied');
});

it('allocates every slot exactly once under a burst of entries', function () {
    app(ZoneService::class)->setCapacity(zone('B'), category('van'), 3);
    $results = collect(range(1, 5))->map(fn ($i) => $this->actingAs($this->staff)->postJson('/api/v1/staff/entries', [
        'plate_number' => "VAN-{$i}00", 'vehicle_category_id' => category('van')->id,
        'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])->status());

    expect($results->filter(fn ($s) => $s === 201))->toHaveCount(3)
        ->and($results->filter(fn ($s) => $s === 409))->toHaveCount(2)
        ->and(ParkingSession::distinct()->count('parking_slot_id'))->toBe(3);
});

it('has database constraints that forbid two open sessions on one slot or vehicle', function () {
    [$session] = recordEntry($this->staff);
    $other = Vehicle::factory()->create();

    expect(fn () => ParkingSession::create(array_merge($session->only([
        'parking_zone_id', 'parking_package_id', 'package_rate_id', 'pricing_snapshot', 'entry_at',
    ]), ['uuid' => (string) str()->uuid(), 'reference' => 'PS-X', 'vehicle_id' => $other->id, 'parking_slot_id' => $session->parking_slot_id, 'open_slot_key' => $session->parking_slot_id, 'open_vehicle_key' => $other->id])))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('records the entry in the audit log and links registered owners', function () {
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0712345678']);
    [$session] = recordEntry($this->staff, ['customer_phone' => '0712345678']);

    expect($session->customer_id)->toBe($customer->id);
    expect(AuditLog::where('action', 'session.entry_recorded')->where('actor_id', $this->staff->id)->exists())->toBeTrue();
});

it('lets staff print and reissue tickets, voiding the old QR', function () {
    [$session, $payload] = recordEntry($this->staff);

    $this->actingAs($this->staff)->getJson("/api/v1/staff/sessions/{$session->uuid}/ticket")->assertOk()->assertJsonPath('ticket.qr_payload', $payload);
    $new = $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/ticket/reissue")->assertOk()->json('ticket.qr_payload');

    expect($new)->not->toBe($payload);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $payload])->assertStatus(409);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $new])->assertOk();
});

it('rejects malformed and unknown QR payloads', function (string $payload) {
    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $payload])
        ->assertNotFound()->assertJsonPath('error_code', 'invalid_ticket');
})->with(['SPT1-nothex', 'SPT1-'.str_repeat('a', 48), "' OR 1=1 --"]);
