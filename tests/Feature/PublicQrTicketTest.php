<?php

use App\Models\PublicTicketToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('parking.qr.public_base_url', 'https://tickets.parkflow.test');
    config()->set('parking.qr.allow_insecure_http', false);
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
});

it('runs only on an isolated SQLite memory database', function () {
    $databases = DB::select('PRAGMA database_list');

    expect(config('database.default'))->toBe('sqlite')
        ->and(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(collect($databases)->firstWhere('name', 'main')->file)->toBe('')
        ->and(collect($databases)->pluck('file')->filter()->isEmpty())->toBeTrue();
});

it('prints a separate view-only URL while preserving the legacy checkout credential', function () {
    [, $legacy] = recordEntry($this->staff, ['customer_phone' => '0771234567']);
    $ticket = $this->actingAs($this->staff)->getJson('/api/v1/staff/sessions/'.\App\Models\ParkingSession::first()->uuid.'/ticket')
        ->assertOk()->json('ticket');

    expect($ticket['qr_payload'])->toBe($legacy)
        ->and($ticket['qr_content'])->toBe($ticket['public_url'])
        ->and($ticket['public_url'])->toStartWith('https://tickets.parkflow.test/t/')
        ->and($ticket['qr_svg'])->toContain('<svg')
        ->and(PublicTicketToken::count())->toBe(1);

    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $legacy])->assertOk();
    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $ticket['public_url']])->assertOk();
});

it('serves a private view-only ticket without customer contact details', function () {
    [$session] = recordEntry($this->staff, [
        'plate_number' => 'WP QR-2048',
        'customer_name' => 'Private Person',
        'customer_phone' => '0771234567',
    ]);
    $token = PublicTicketToken::firstOrFail()->token_encrypted;

    $this->get('/t/'.$token)->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('Digital Parking Ticket')
        ->assertSee('WP QR-2048')
        ->assertSee($session->zone->name)
        ->assertSee($session->slot->code)
        ->assertSee($session->package->name)
        ->assertDontSee('Private Person')
        ->assertDontSee('0771234567');

    $this->post('/t/'.$token)->assertStatus(405);
});

it('uses the existing server quote for live duration and estimated charges', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00:00', 'Asia/Colombo'));
    recordEntry($this->staff);
    $this->travel(125)->minutes();
    $token = PublicTicketToken::firstOrFail()->token_encrypted;

    $this->get('/t/'.$token)->assertOk()
        ->assertSee('2h 5m')
        ->assertSee('Estimated parking charges')
        ->assertSee('1,500.00')
        ->assertSee('This estimate may change until checkout.');
});

it('rejects arbitrary URLs and revokes the old public URL on reissue', function () {
    [$session] = recordEntry($this->staff);
    $oldToken = PublicTicketToken::firstOrFail()->token_encrypted;

    $this->actingAs($this->staff)->postJson('/api/v1/staff/exits/preview', [
        'qr_payload' => 'https://evil.example/t/'.str_repeat('a', 64),
    ])->assertNotFound()->assertJsonPath('error_code', 'invalid_ticket');

    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/ticket/reissue")->assertOk();
    $this->get('/t/'.$oldToken)->assertStatus(410);
    expect(PublicTicketToken::where('status', PublicTicketToken::ACTIVE)->count())->toBe(1);
});

it('shows the stored final status and receipt after checkout', function () {
    [, $legacy] = recordEntry($this->staff);
    $token = PublicTicketToken::firstOrFail()->token_encrypted;
    $this->actingAs($this->staff);

    settle($legacy)->assertOk();

    $this->get('/t/'.$token)->assertOk()
        ->assertSee('Checked Out')
        ->assertSee('Final parking charges')
        ->assertSee('Payment status: Paid')
        ->assertSee('Receipt:');
});

it('returns a generic not-found response for unknown public tokens', function () {
    $this->get('/t/'.str_repeat('a', 64))->assertNotFound()
        ->assertSee('Ticket unavailable')
        ->assertDontSee(str_repeat('a', 64));
});

it('does not let a public token call privileged staff operations', function () {
    [$session] = recordEntry($this->staff);
    $publicUrl = 'https://tickets.parkflow.test/t/'.PublicTicketToken::firstOrFail()->token_encrypted;
    auth()->logout();

    $this->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $publicUrl])->assertUnauthorized();
    $this->postJson('/api/v1/staff/exits/settle', [
        'qr_payload' => $publicUrl,
        'idempotency_key' => (string) str()->uuid(),
        'payment_method' => 'cash',
    ])->assertUnauthorized();
    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/gate/exit")->assertUnauthorized();
});
