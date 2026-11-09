<?php

use App\Contracts\AnprEngine;
use App\Contracts\GateController;
use App\Models\AnprCamera;
use App\Models\AnprCameraHealthLog;
use App\Models\AnprEvent;
use App\Models\AnprEventImage;
use App\Models\AnprReviewAction;
use App\Models\AuditLog;
use App\Models\GateCommand;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Anpr\AnprCameraService;
use App\Services\Anpr\AnprEventService;
use App\Services\Integrations\FakeAnprEngine;
use App\Services\SettingsService;
use App\Support\Anpr\SriLankaPlateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    seedParking();
    config(['parking.drivers.anpr_engine' => 'fake']);
    FakeAnprEngine::reset();
    Storage::fake('local');
    $this->staff = userWithRole(User::ROLE_STAFF);
    $this->admin = userWithRole(User::ROLE_ADMIN);
    $this->auditor = userWithRole(User::ROLE_AUDITOR);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00', 'Asia/Colombo'));
    [$this->entryCam, $this->entryToken] = app(AnprCameraService::class)->create(['code' => 'CAM-IN-1', 'name' => 'Entry 1', 'direction' => 'entry', 'camera_type' => 'ip_rtsp', 'stream_url' => 'rtsp://192.168.1.20:554/stream1', 'stream_username' => 'viewer', 'stream_password' => 'S3cret!pw'], $this->admin);
    [$this->exitCam, $this->exitToken] = app(AnprCameraService::class)->create(['code' => 'CAM-OUT-1', 'name' => 'Exit 1', 'direction' => 'exit', 'camera_type' => 'ip_rtsp'], $this->admin);
});

afterEach(fn () => FakeAnprEngine::reset());

function anprPng(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('frame.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
}

/** Push an event as the camera agent would. */
function devicePush(AnprCamera $camera, string $token, ?string $text, float $confidence = 0.97, ?string $key = null, array $extra = []): TestResponse
{
    return test()->withHeaders(['X-Anpr-Camera' => $camera->code, 'Authorization' => 'Bearer '.$token])
        ->post('/api/v1/anpr/device/events', array_merge([
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'status' => $text === null ? 'unreadable' : 'recognized',
            'candidates' => $text === null ? [] : [['text' => $text, 'confidence' => $confidence]],
            'processing_ms' => 120,
            'plate_crop' => anprPng(),
        ], $extra), ['Accept' => 'application/json']);
}

function anprEntry(User $staff, string $eventUuid, array $extra = []): TestResponse
{
    return test()->actingAs($staff)->postJson('/api/v1/staff/anpr/entry/confirm', array_merge([
        'event_uuid' => $eventUuid, 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol',
        'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ], $extra));
}

function verifyEvent(User $staff, string $uuid): void
{
    test()->actingAs($staff)->postJson("/api/v1/staff/anpr/events/{$uuid}/verify", ['action' => 'confirm'])->assertOk();
}

/* =========================== cameras & security =========================== */

it('lets only admins manage cameras and never exposes stream credentials', function () {
    $payload = ['code' => 'CAM-X', 'name' => 'X', 'direction' => 'dual', 'camera_type' => 'ip_rtsp', 'stream_url' => 'rtsp://192.168.5.9/live', 'stream_password' => 'TopSecret99'];
    $this->actingAs($this->staff)->postJson('/api/v1/admin/anpr/cameras', $payload)->assertForbidden();
    $this->actingAs($this->auditor)->postJson('/api/v1/admin/anpr/cameras', $payload)->assertForbidden();

    $created = $this->actingAs($this->admin)->postJson('/api/v1/admin/anpr/cameras', $payload)->assertCreated();
    expect($created->json('device_token'))->toStartWith('anpr_')
        ->and($created->getContent())->not->toContain('TopSecret99')->not->toContain('rtsp://')
        ->and($created->json('data.stream_host'))->toBe('192.168.5.9')
        ->and($created->json('data.credentials_configured'))->toBeTrue();

    $raw = DB::table('anpr_cameras')->where('code', 'CAM-X')->first();
    expect($raw->stream_password_encrypted)->not->toContain('TopSecret99')
        ->and($raw->ingest_token_hash)->toBe(hash('sha256', $created->json('device_token')));

    $id = $created->json('data.id');
    $this->actingAs($this->admin)->getJson("/api/v1/admin/anpr/cameras/{$id}")->assertOk()->assertJsonMissingPath('data.stream_url');
    $this->actingAs($this->staff)->getJson('/api/v1/staff/anpr/cameras')->assertOk()->assertJsonMissingPath('data.0.stream_host');
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/anpr/cameras/{$id}", ['is_enabled' => false])->assertOk()->assertJsonPath('data.is_enabled', false);
    expect(AuditLog::where('action', 'anpr.camera_updated')->value('new_values'))->not->toHaveKey('stream_password');
});

it('blocks SSRF-style and credential-bearing stream URLs', function (string $url, string $code) {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/anpr/cameras', ['code' => 'C'.Str::random(4), 'name' => 'n', 'direction' => 'entry', 'camera_type' => 'ip_rtsp', 'stream_url' => $url])
        ->assertUnprocessable()->assertJsonPath('error_code', $code);
})->with([
    ['rtsp://127.0.0.1:554/x', 'stream_host_not_allowed'],
    ['http://169.254.169.254/latest/meta-data', 'stream_host_not_allowed'],
    ['rtsp://8.8.8.8/stream', 'stream_host_not_allowed'],
    ['rtsp://admin:pw@192.168.1.20/s', 'credentials_in_url'],
    ['file:///etc/passwd', 'invalid_stream_url'],
    ['gopher://192.168.1.5/', 'invalid_stream_url'],
    ['rtsp://evil.example.com/s', 'stream_host_not_allowed'],
]);

it('requires device authentication for event ingestion, separate from user sessions', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/anpr/device/events', ['idempotency_key' => 'abcdefgh1', 'status' => 'unreadable'])->assertUnauthorized();
    $this->withHeaders(['X-Anpr-Camera' => 'CAM-IN-1', 'Authorization' => 'Bearer wrong-token-wrong-token-wrong-token'])
        ->postJson('/api/v1/anpr/device/events', ['idempotency_key' => 'abcdefgh2', 'status' => 'unreadable'])->assertUnauthorized();
    // A valid token for a different camera does not work.
    $this->withHeaders(['X-Anpr-Camera' => 'CAM-IN-1', 'Authorization' => 'Bearer '.$this->exitToken])
        ->postJson('/api/v1/anpr/device/events', ['idempotency_key' => 'abcdefgh3', 'status' => 'unreadable'])->assertUnauthorized();

    devicePush($this->entryCam, $this->entryToken, 'WP CAB-1234')->assertCreated();

    $this->entryCam->update(['is_enabled' => false]);
    devicePush($this->entryCam, $this->entryToken, 'WP CAB-1234')->assertForbidden();

    $new = app(AnprCameraService::class)->rotateToken($this->exitCam->fresh(), $this->admin);
    devicePush($this->exitCam, $this->exitToken, 'CAB-1')->assertUnauthorized();
    devicePush($this->exitCam, $new, 'CAB-1111')->assertCreated();
});

it('gives the authenticated agent its decrypted stream configuration only', function () {
    $this->withHeaders(['X-Anpr-Camera' => 'CAM-IN-1', 'Authorization' => 'Bearer '.$this->entryToken])->getJson('/api/v1/anpr/device/config')
        ->assertOk()->assertJsonPath('data.stream.url', 'rtsp://192.168.1.20:554/stream1')->assertJsonPath('data.stream.password', 'S3cret!pw');
    expect(AuditLog::where('action', 'anpr.device_config_read')->exists())->toBeTrue();
});

/* =========================== recognition results =========================== */

it('records a valid recognition with raw and normalised text kept separate', function () {
    $r = devicePush($this->entryCam, $this->entryToken, 'wp cab 1234', 0.96)->assertCreated();
    $event = AnprEvent::where('uuid', $r->json('event_uuid'))->first();

    expect($event->raw_text)->toBe('wp cab 1234')
        ->and($event->normalized_plate)->toBe('WPCAB1234')
        ->and($event->plate_format)->toBe('modern_province')
        ->and($event->confidence_bp)->toBe(9600)
        ->and($event->recognition_status)->toBe('recognized')
        ->and($event->verification_status)->toBe('pending')
        ->and($event->images()->first()->category)->toBe('plate_crop');
});

it('stores region OCR review suggestions separately without verifying or opening a gate', function () {
    $key = (string) Str::uuid();

    $suggestions = [[
        'text' => 'NW KL-6036',
        'confidence' => 0.1911,
        'review_required' => true,
        'source' => 'region_ocr',
    ]];

    $response = devicePush(
        $this->entryCam,
        $this->entryToken,
        'KL6036',
        0.9997,
        $key,
        ['review_suggestions' => $suggestions]
    )->assertCreated();

    $event = AnprEvent::where('uuid', $response->json('event_uuid'))->firstOrFail();

    expect($event->raw_text)->toBe('KL6036')
        ->and($event->normalized_plate)->toBe('KL6036')
        ->and($event->review_suggestions)->toBe($suggestions)
        ->and($event->verification_status)->toBe('pending')
        ->and($event->verified_plate)->toBeNull()
        ->and(ParkingSession::count())->toBe(0)
        ->and(GateCommand::count())->toBe(0);

    $retry = devicePush(
        $this->entryCam,
        $this->entryToken,
        'KL6036',
        0.9997,
        $key,
        ['review_suggestions' => $suggestions]
    )->assertOk()->assertJsonPath('replayed', true);

    expect($retry->json('event_uuid'))->toBe($event->uuid)
        ->and(AnprEvent::where('idempotency_key', $key)->count())->toBe(1);
});
it('marks unreadable plates without inventing a reading', function () {
    $r = devicePush($this->entryCam, $this->entryToken, null)->assertCreated();
    $event = AnprEvent::where('uuid', $r->json('event_uuid'))->first();

    expect($event->recognition_status)->toBe('unreadable')->and($event->normalized_plate)->toBeNull()->and($event->candidates)->toBeNull();
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $event->uuid])->assertStatus(409)->assertJsonPath('error_code', 'anpr_no_reading');
});

it('flags low confidence and confusion-corrected readings for mandatory verification', function () {
    app(SettingsService::class)->set('anpr.verification_mode', 'below_threshold', $this->admin);
    $low = AnprEvent::where('uuid', devicePush($this->entryCam, $this->entryToken, 'CAB-1234', 0.55)->json('event_uuid'))->first();
    $fixed = AnprEvent::where('uuid', devicePush($this->entryCam, $this->entryToken, 'CA8-I235', 0.99)->json('event_uuid'))->first();

    expect($low->recognition_status)->toBe('low_confidence')
        ->and($fixed->normalized_plate)->toBe('CAB1235')
        ->and($fixed->recognition_status)->toBe('low_confidence') // confusion-corrected readings never count as clean, even at 99%
        ->and($this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $low->uuid])->json('data.requires_verification'))->toBeTrue();

    anprEntry($this->staff, $low->uuid, ['confirm_plate' => 'CAB-1234'])->assertStatus(409)->assertJsonPath('error_code', 'verification_required');
});

it('normalises Sri Lankan plate formats', function (string $raw, ?string $normalized, string $format, bool $corrected) {
    $n = app(SriLankaPlateNormalizer::class)->normalize($raw);
    expect($n['normalized'])->toBe($normalized)->and($n['format'])->toBe($format)->and($n['confusion_corrected'])->toBe($corrected);
})->with([
    ['WP CAB-1234', 'WPCAB1234', 'modern_province', false],
    ['cab 1234', 'CAB1234', 'modern', false],
    ['KX-4455', 'KX4455', 'modern', false],
    ['NW BBC 1O23', 'NWBBC1023', 'modern_province', true],
    ['C4B-1234', 'C4B1234', 'unknown', false],
    ['19-1234', '191234', 'legacy_numeric', false],
    ['250 à·à·Šâ€à¶»à·“ 1234', '2501234', 'legacy_numeric', false],
    ['CD-1234-X', 'CD1234X', 'unknown', false],
    ['  ', null, 'unreadable', false],
]);

/* =========================== verification & manual fallback =========================== */

it('lets staff correct a reading with a reason, keeping an immutable trail', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1284', 0.91)->json('event_uuid');

    $this->actingAs($this->staff)->postJson("/api/v1/staff/anpr/events/{$uuid}/verify", ['action' => 'correct', 'plate_number' => 'CAB-1234'])->assertUnprocessable();
    $this->actingAs($this->staff)->postJson("/api/v1/staff/anpr/events/{$uuid}/verify", ['action' => 'correct', 'plate_number' => 'CAB-1234', 'reason' => 'incorrect_result', 'notes' => '8 read instead of 3'])
        ->assertOk()->assertJsonPath('data.verification_status', 'corrected')->assertJsonPath('data.verified_plate', 'CAB1234')->assertJsonPath('data.raw_text', 'CAB-1284');

    $review = AnprReviewAction::where('action', 'correct')->first();
    expect($review->from_plate)->toBe('CAB1284')->and($review->to_plate)->toBe('CAB1234')->and($review->user_id)->toBe($this->staff->id);
    expect(fn () => $review->update(['to_plate' => 'X']))->toThrow(LogicException::class);
    $this->actingAs($this->auditor)->getJson('/api/v1/audit/anpr/corrections')->assertOk()->assertJsonPath('data.0.to_plate', 'CAB1234');
});

it('supports manual identification when the camera is offline, with mandatory reason', function () {
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'entry', 'plate_number' => 'CAB-5555'])->assertUnprocessable();
    $this->actingAs($this->auditor)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'entry', 'plate_number' => 'CAB-5555', 'reason' => 'camera_offline'])->assertForbidden();

    $uuid = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'entry', 'plate_number' => 'CAB-5555', 'reason' => 'camera_offline'])
        ->assertCreated()->assertJsonPath('data.recognition_status', 'manual')->assertJsonPath('data.verified_by', $this->staff->id)->json('data.uuid');

    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-5555'])->assertCreated()->assertJsonPath('data.status', 'active');
    expect(ParkingSession::first()->entry_source)->toBe('manual');
});

it('restricts overrides to configured roles', function () {
    app(SettingsService::class)->set('anpr.manual_override_roles', ['admin', 'super_admin'], $this->admin);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'entry', 'plate_number' => 'CAB-5555', 'reason' => 'camera_offline'])
        ->assertForbidden()->assertJsonPath('error_code', 'override_not_permitted');
    $this->actingAs($this->admin)->putJson('/api/v1/admin/settings/anpr.manual_override_roles', ['value' => ['customer']])->assertUnprocessable();
});

/* =========================== entry =========================== */

it('looks up the vehicle exactly and creates an entry from a verified ANPR event', function () {
    $owner = userWithRole(User::ROLE_CUSTOMER);
    Vehicle::factory()->create(['identifier' => 'WPCAB1234', 'plate_number' => 'WP CAB-1234', 'owner_id' => $owner->id]);
    $uuid = devicePush($this->entryCam, $this->entryToken, 'WP CAB-1234')->json('event_uuid');

    $preview = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $uuid])->assertOk()->json('data');
    expect($preview['is_new_vehicle'])->toBeFalse()->and($preview['vehicle']['identifier'])->toBe('WPCAB1234')->and($preview['blocked'])->toBeNull()
        ->and(collect($preview['zones'])->firstWhere('code', 'B')['available_for_vehicle'])->toBe(10);

    verifyEvent($this->staff, $uuid);
    $r = anprEntry($this->staff, $uuid, ['confirm_plate' => 'WP CAB-1234'])->assertCreated()->assertJsonPath('gate.status', 'not_requested');
    $session = ParkingSession::where('uuid', $r->json('data.uuid'))->first();
    expect($session->customer_id)->toBe($owner->id)->and($session->entry_source)->toBe('anpr')
        ->and(AnprEvent::where('uuid', $uuid)->first()->parking_session_id)->toBe($session->id);
});

it('registers an unknown vehicle through the staff entry flow', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CBA-9876')->json('event_uuid');
    expect($this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $uuid])->json('data.is_new_vehicle'))->toBeTrue();

    verifyEvent($this->staff, $uuid);
    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CBA9876', 'fuel_type' => 'diesel'])->assertCreated();
    expect(Vehicle::where('identifier', 'CBA9876')->first()->fuel_type->value)->toBe('diesel');
});

it('rejects an entry for a vehicle that is already parked and never allocates a second slot', function () {
    recordEntry($this->staff, ['plate_number' => 'CAB-1234']);
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');

    expect($this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $uuid])->json('data.blocked.error_code'))->toBe('duplicate_active_entry');
    verifyEvent($this->staff, $uuid);
    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-1234'])->assertStatus(409)->assertJsonPath('error_code', 'duplicate_active_entry');
    expect(ParkingSlot::where('status', 'occupied')->count())->toBe(1)->and(AnprEvent::where('uuid', $uuid)->first()->consumed_at)->toBeNull();
});

it('requires the staff confirmation to match the verified plate', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');
    verifyEvent($this->staff, $uuid);
    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-9999'])->assertStatus(409)->assertJsonPath('error_code', 'plate_confirmation_mismatch');
    anprEntry($this->staff, $uuid)->assertStatus(409)->assertJsonPath('error_code', 'plate_confirmation_mismatch');
});

it('re-enters a guaranteed reservation through ANPR into the same slot', function () {
    [$s, $qr] = recordEntry($this->staff, ['plate_number' => 'MON-1111', 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id]);
    $this->actingAs($this->staff);
    settle($qr)->assertOk();

    $this->travel(1)->days();
    $uuid = devicePush($this->entryCam, $this->entryToken, 'MON-1111')->json('event_uuid');
    $preview = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $uuid])->json('data');
    expect($preview['active_reservation']['slot'])->toBe($s->slot->code);

    verifyEvent($this->staff, $uuid);
    $r = anprEntry($this->staff, $uuid, ['confirm_plate' => 'MON-1111', 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id])->assertCreated();
    expect($r->json('data.slot.id'))->toBe($s->parking_slot_id);
});

/* =========================== exit =========================== */

it('finds the session, previews the authoritative bill and settles before opening the gate', function () {
    $in = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');
    verifyEvent($this->staff, $in);
    anprEntry($this->staff, $in, ['confirm_plate' => 'CAB-1234'])->assertCreated();

    $this->travel(25 * 60)->minutes();
    $out = devicePush($this->exitCam, $this->exitToken, 'CAB-1234')->json('event_uuid');
    $preview = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/preview', ['event_uuid' => $out])->assertOk()->json('data');
    expect($preview['match']['result'])->toBe('matched')
        ->and($preview['session']['zone'])->toBe('B')
        ->and($preview['bill']['total_payable_minor'])->toBe(160000)
        ->and($preview['bill']['overtime_charge_minor'])->toBe(10000)
        ->and(GateCommand::count())->toBe(0); // preview and recognition never touch the gate

    verifyEvent($this->staff, $out);
    $key = (string) Str::uuid();
    $settled = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-1234', 'idempotency_key' => $key, 'payment_method' => 'cash', 'tendered_minor' => 200000])
        ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('gate.status', 'simulated')->assertJsonPath('receipt.total_minor', 160000);
    expect(GateCommand::where('status', 'success')->count())->toBe(1)
        ->and(AnprEvent::where('uuid', $out)->first()->consumed_at)->not->toBeNull()
        ->and(ParkingSession::first()->status)->toBe('completed');

    // Replay with the same key: same result, no second charge, no second gate command.
    $this->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-1234', 'idempotency_key' => $key, 'payment_method' => 'cash'])
        ->assertOk()->assertJsonPath('replayed', true);
    expect(Payment::count())->toBe(1)->and(GateCommand::count())->toBe(1);

    // A different key on a used event is refused.
    $this->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-1234', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertStatus(409)->assertJsonPath('error_code', 'anpr_event_already_used');
});

it('never opens the gate before payment is confirmed', function () {
    [$s] = recordEntry($this->staff, ['plate_number' => 'CAB-2222']);
    $out = devicePush($this->exitCam, $this->exitToken, 'CAB-2222')->json('event_uuid');
    verifyEvent($this->staff, $out);

    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-2222', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'online'])
        ->assertStatus(202)->assertJsonPath('status', 'payment_pending')->assertJsonPath('gate', null)->assertJsonPath('exit_authorized', false);
    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/gate/exit")->assertStatus(409)->assertJsonPath('error_code', 'exit_not_settled');
    expect(GateCommand::count())->toBe(0);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-2222', 'idempotency_key' => (string) Str::uuid()])
        ->assertStatus(409)->assertJsonPath('error_code', 'payment_pending');
});

it('handles zero matches and never matches by similarity', function () {
    Vehicle::factory()->create(['identifier' => 'WPCAB1234']);
    recordEntry($this->staff, ['plate_number' => 'WP CAB-1234']);
    $out = devicePush($this->exitCam, $this->exitToken, 'CAB-1234')->json('event_uuid'); // province missing on reading
    $preview = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/preview', ['event_uuid' => $out])->json('data');

    expect($preview['match']['result'])->toBe('unknown_vehicle')
        ->and($preview['match']['suggestions'][0]['identifier'])->toBe('WPCAB1234')
        ->and($preview['session'])->toBeNull();

    verifyEvent($this->staff, $out);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'CAB-1234', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertNotFound()->assertJsonPath('error_code', 'no_active_session');
    expect(ParkingSession::where('status', 'active')->count())->toBe(1);
});

it('handles a gate-controller failure safely and allows one authorised retry', function () {
    app()->bind(GateController::class, fn () => new class implements GateController
    {
        public static int $calls = 0;

        public function open(ParkingSession $session, string $direction): array
        {
            if (self::$calls++ === 0) {
                throw new RuntimeException('relay timeout');
            }

            return ['status' => 'opened', 'driver' => 'test-relay'];
        }
    });
    [$s, $qr] = recordEntry($this->staff, ['plate_number' => 'CAB-3333']);
    $this->actingAs($this->staff);
    settle($qr)->assertOk()->assertJsonPath('gate.status', 'failed');
    expect($s->fresh()->status)->toBe('completed');

    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/gate/exit")->assertOk()->assertJsonPath('gate.status', 'opened');
    $this->postJson("/api/v1/staff/sessions/{$s->uuid}/gate/exit")->assertStatus(409)->assertJsonPath('error_code', 'gate_already_opened');
    expect(GateCommand::pluck('status')->all())->toBe(['failed', 'success']);
});

it('exits a plate-less vehicle through authorised manual lookup', function () {
    [$s] = recordEntry($this->staff, ['plate_number' => null, 'vehicle_category_id' => category('bicycle')->id, 'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);
    $uuid = $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'exit', 'reason' => 'no_plate', 'notes' => 'Bicycle'])->assertCreated()->json('data.uuid');

    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/preview', ['event_uuid' => $uuid])->assertOk()->assertJsonPath('data.match.result', 'no_plate');
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $uuid, 'parking_session_uuid' => $s->uuid, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertOk()->assertJsonPath('status', 'completed');
});

/* =========================== duplicates, replay, concurrency =========================== */

it('marks repeated camera reads as duplicates within the window and returns the same event on retry', function () {
    $key = (string) Str::uuid();
    $first = devicePush($this->entryCam, $this->entryToken, 'CAB-1234', 0.97, $key)->assertCreated()->json('event_uuid');
    devicePush($this->entryCam, $this->entryToken, 'CAB-1234', 0.97, $key)->assertOk()->assertJsonPath('event_uuid', $first)->assertJsonPath('replayed', true);

    $this->travel(10)->seconds();
    $dup = devicePush($this->entryCam, $this->entryToken, 'CAB 1234')->assertCreated();
    expect($dup->json('recognition_status'))->toBe('duplicate');
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $dup->json('event_uuid')])
        ->assertStatus(409)->assertJsonPath('error_code', 'anpr_event_duplicate')->assertJsonPath('context.original_event', $first);

    $this->travel(60)->seconds();
    expect(devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('recognition_status'))->toBe('recognized');
    expect(AnprEvent::count())->toBe(3);
});

it('prevents a replayed entry event from creating a second session', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');
    verifyEvent($this->staff, $uuid);
    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-1234'])->assertCreated();
    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-1234'])->assertStatus(409);

    expect(ParkingSession::count())->toBe(1)->and(ParkingSlot::where('status', 'occupied')->count())->toBe(1);
});

it('rejects two different entry events racing for one vehicle (second sees the active session)', function () {
    $a = devicePush($this->entryCam, $this->entryToken, 'CAB-7777')->json('event_uuid');
    $this->travel(40)->seconds();
    $b = devicePush($this->entryCam, $this->entryToken, 'CAB-7777')->json('event_uuid');
    verifyEvent($this->staff, $a);
    verifyEvent($this->staff, $b);

    anprEntry($this->staff, $a, ['confirm_plate' => 'CAB-7777'])->assertCreated();
    anprEntry($this->staff, $b, ['confirm_plate' => 'CAB-7777'])->assertStatus(409)->assertJsonPath('error_code', 'duplicate_active_entry');
});

it('rejects a second exit settlement for the same session from another event', function () {
    [$s] = recordEntry($this->staff, ['plate_number' => 'CAB-8888']);
    $a = devicePush($this->exitCam, $this->exitToken, 'CAB-8888')->json('event_uuid');
    $this->travel(40)->seconds();
    $b = devicePush($this->exitCam, $this->exitToken, 'CAB-8888')->json('event_uuid');
    verifyEvent($this->staff, $a);
    verifyEvent($this->staff, $b);

    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $a, 'confirm_plate' => 'CAB-8888', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])->assertOk();
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $b, 'confirm_plate' => 'CAB-8888', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertNotFound()->assertJsonPath('error_code', 'no_active_session');
    expect(Payment::count())->toBe(1)->and(GateCommand::count())->toBe(1);
});

/* =========================== engine, health, images =========================== */

it('falls back safely when the ANPR engine is unavailable or times out', function () {
    FakeAnprEngine::$available = false;
    $r = $this->actingAs($this->staff)->post('/api/v1/staff/anpr/recognize', ['direction' => 'entry', 'camera_code' => 'CAM-IN-1', 'image' => anprPng()], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('data.recognition_status', 'engine_error')->assertJsonPath('data.error_code', 'anpr_engine_unavailable');
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/entry/preview', ['event_uuid' => $r->json('data.uuid')])->assertStatus(409);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/manual-review', ['direction' => 'entry', 'plate_number' => 'CAB-1234', 'reason' => 'engine_unavailable', 'original_event_uuid' => $r->json('data.uuid')])->assertCreated();
    expect(AnprCameraHealthLog::where('component', 'engine')->exists())->toBeTrue();
});

it('maps HTTP engine timeouts to a safe error without leaking details', function () {
    config(['parking.drivers.anpr_engine' => 'http_local', 'parking.anpr.service_token' => 'svc-token']);
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);
    $result = app(AnprEngine::class)->recognize('bytes', 'image/jpeg');
    expect($result['status'])->toBe('engine_error')->and($result['error_code'])->toBe('anpr_engine_unavailable');
});

it('converts HTTP engine confidences to basis points', function () {
    config(['parking.drivers.anpr_engine' => 'http_local', 'parking.anpr.service_token' => 'svc-token']);
    Http::fake(['*/v1/recognize' => Http::response(['status' => 'recognized', 'candidates' => [['text' => 'CAB-1234', 'confidence' => 0.93]], 'processing_ms' => 88])]);
    $ok = app(AnprEngine::class)->recognize('bytes', 'image/jpeg');
    expect($ok['candidates'][0])->toBe(['text' => 'CAB-1234', 'confidence_bp' => 9300, 'detector_confidence_bp' => null]);
});

it('persists HTTP region suggestions from staff image upload', function () {
    config([
        'parking.drivers.anpr_engine' => 'http_local',
        'parking.anpr.service_token' => 'svc-token',
    ]);

    $suggestion = [
        'text' => 'NW KL-6036',
        'confidence' => 0.7882,
        'review_required' => true,
        'source' => 'region_ocr',
    ];

    Http::fake([
        '*/v1/recognize' => Http::response([
            'status' => 'recognized',
            'candidates' => [
                ['text' => 'KL6036', 'confidence' => 0.93],
            ],
            'review_suggestions' => [$suggestion],
            'processing_ms' => 88,
        ]),
    ]);

    $response = $this->actingAs($this->staff)
        ->post('/api/v1/staff/anpr/recognize', [
            'direction' => 'entry',
            'camera_code' => 'CAM-IN-1',
            'image' => anprPng(),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $event = AnprEvent::where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($event->review_suggestions)
        ->toBe([$suggestion])
        ->and($event->verification_status)
        ->toBe('pending')
        ->and(ParkingSession::count())
        ->toBe(0)
        ->and(GateCommand::count())
        ->toBe(0);
});
it('persists adaptive OCR partial clues without verifying or opening a gate', function () {
    config([
        'parking.drivers.anpr_engine' => 'http_local',
        'parking.anpr.service_token' => 'svc-token',
    ]);

    $clues = [
        'province_clues' => [
            ['text' => 'SP', 'confidence' => 0.5724, 'angle' => 10],
        ],
        'registration_clues' => [
            ['text' => 'XJ2284', 'confidence' => 0.9515, 'angle' => 30],
        ],
        'review_required' => true,
        'verified' => false,
    ];

    Http::fake([
        '*/v1/recognize' => Http::response([
            'status' => 'recognized',
            'candidates' => [
                ['text' => 'SPX7228', 'confidence' => 0.542],
            ],
            'review_suggestions' => [],
            'partial_review_clues' => $clues,
            'processing_ms' => 431,
        ]),
    ]);

    $response = $this->actingAs($this->staff)
        ->post('/api/v1/staff/anpr/recognize', [
            'direction' => 'entry',
            'camera_code' => 'CAM-IN-1',
            'image' => anprPng(),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $event = AnprEvent::where('uuid', $response->json('data.uuid'))
        ->firstOrFail();

    expect($event->partial_review_clues)
        ->toBe($clues)
        ->and($response->json('data.partial_review_clues'))
        ->toBe($clues)
        ->and($event->verification_status)
        ->toBe('pending')
        ->and($event->verified_plate)
        ->toBeNull()
        ->and(ParkingSession::count())
        ->toBe(0)
        ->and(GateCommand::count())
        ->toBe(0);
});
it('preserves HTTP engine region OCR review suggestions', function () {
    config([
        'parking.drivers.anpr_engine' => 'http_local',
        'parking.anpr.service_token' => 'svc-token',
    ]);

    $suggestion = [
        'text' => 'NW KL-6036',
        'confidence' => 0.7882,
        'review_required' => true,
        'source' => 'region_ocr',
    ];

    Http::fake([
        '*/v1/recognize' => Http::sequence()
            ->push([
                'status' => 'recognized',
                'candidates' => [
                    ['text' => 'KL6036', 'confidence' => 0.93],
                ],
                'review_suggestions' => [$suggestion],
            ])
            ->push([
                'status' => 'recognized',
                'candidates' => [
                    ['text' => 'KL6036', 'confidence' => 0.93],
                ],
            ]),
    ]);

    $engine = app(AnprEngine::class);

    $first = $engine->recognize('test-image', 'image/jpeg');

    expect($first['review_suggestions'])
        ->toBe([$suggestion])
        ->and($first['candidates'][0]['confidence_bp'])
        ->toBe(9300);

    $second = $engine->recognize('test-image', 'image/jpeg');

    expect($second['review_suggestions'])->toBe([]);
});
it('keeps the detector score separate from the OCR confidence and never uses it for the status', function () {
    config(['parking.drivers.anpr_engine' => 'http_local', 'parking.anpr.service_token' => 'svc-token']);
    Http::fake(['*/v1/recognize' => Http::response(['status' => 'recognized', 'candidates' => [['text' => 'CAB-1234', 'confidence' => 0.62, 'detector_confidence' => 0.99]]])]);
    expect(app(AnprEngine::class)->recognize('bytes', 'image/jpeg')['candidates'][0])
        ->toBe(['text' => 'CAB-1234', 'confidence_bp' => 6200, 'detector_confidence_bp' => 9900]);

    $r = devicePush($this->entryCam, $this->entryToken, null, 0.97, null, [
        'status' => 'recognized', 'candidates' => json_encode([['text' => 'KX-4455', 'confidence' => 0.55, 'detector_confidence' => 0.98]]),
    ])->assertCreated();
    $event = AnprEvent::where('uuid', $r->json('event_uuid'))->first();
    expect($event->candidates[0]['confidence_bp'])->toBe(5500)
        ->and($event->candidates[0]['detector_confidence_bp'])->toBe(9800)
        ->and($event->recognition_status)->toBe('low_confidence'); // a high detector score does not lift a low OCR reading

    devicePush($this->entryCam, $this->entryToken, null, 0.97, null, [
        'status' => 'recognized', 'candidates' => json_encode([['text' => 'KX-4456', 'confidence' => 0.9, 'detector_confidence' => 1.5]]),
    ])->assertStatus(422)->assertJsonValidationErrors('candidates.0.detector_confidence');
});

it('tracks camera heartbeats, offline detection and recovery', function () {
    $headers = ['X-Anpr-Camera' => 'CAM-IN-1', 'Authorization' => 'Bearer '.$this->entryToken];
    $this->withHeaders($headers)->postJson('/api/v1/anpr/device/heartbeat', ['status' => 'ok', 'latency_ms' => 12])->assertOk()->assertJsonPath('status', 'online');

    $this->travel(10)->minutes();
    $this->artisan('parking:anpr-health-check')->assertSuccessful();
    expect($this->entryCam->fresh()->health_status)->toBe('offline');
    $this->actingAs($this->admin)->getJson('/api/v1/reports/anpr/camera-health')->assertOk()->assertJsonPath('data.offline.0', 'CAM-IN-1');

    $this->withHeaders($headers)->postJson('/api/v1/anpr/device/heartbeat', ['status' => 'error', 'message' => 'connect rtsp://admin:pw@192.168.1.20 failed password=abc'])->assertOk();
    $err = $this->entryCam->fresh()->last_error;
    expect($err)->not->toContain('pw@')->not->toContain('abc');
    $this->withHeaders($headers)->postJson('/api/v1/anpr/device/heartbeat', ['status' => 'ok'])->assertOk();

    expect(AnprCameraHealthLog::where('anpr_camera_id', $this->entryCam->id)->pluck('status')->all())->toBe(['online', 'offline', 'error', 'recovered']);
    $this->getJson('/api/v1/anpr/health')->assertOk()->assertJsonMissingPath('service_token');
});

it('serves snapshots privately to authorised roles only and audits access', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');
    $image = AnprEventImage::first();
    expect($image->path)->toStartWith('anpr/')->not->toContain('frame');
    Storage::disk('local')->assertExists($image->path);

    $url = "/api/v1/staff/anpr/events/{$uuid}/images/{$image->uuid}";
    $this->actingAs($this->staff)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->auditor)->get($url)->assertOk();
    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->getJson($url)->assertForbidden();
    $this->actingAs($this->staff)->getJson("/api/v1/staff/anpr/events/{$uuid}")->assertOk()->assertJsonMissingPath('data.images.0.path');
    expect(AuditLog::where('action', 'anpr.image_viewed')->count())->toBe(2);
});

it('rejects non-image uploads from devices', function () {
    $this->withHeaders(['X-Anpr-Camera' => 'CAM-IN-1', 'Authorization' => 'Bearer '.$this->entryToken])->post('/api/v1/anpr/device/events', [
        'idempotency_key' => (string) Str::uuid(), 'status' => 'recognized', 'candidates' => [['text' => 'CAB-1', 'confidence' => 0.9]],
        'plate_crop' => UploadedFile::fake()->create('evil.php', 4, 'application/x-php'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('plate_crop');
});

it('purges snapshots after the retention period but keeps the event record', function () {
    devicePush($this->entryCam, $this->entryToken, 'CAB-1234');
    $image = AnprEventImage::first();
    $this->travel(31)->days();
    $this->artisan('parking:purge-anpr-images')->assertSuccessful();

    $image->refresh();
    expect($image->retention_status)->toBe('purged')->and($image->path)->toBeNull()->and(AnprEvent::count())->toBe(1);
    $this->actingAs($this->staff)->get("/api/v1/staff/anpr/events/{$image->event->uuid}/images/{$image->uuid}")->assertStatus(410);
});

/* =========================== roles & isolation =========================== */

it('gives auditors read-only ANPR access and customers none', function () {
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-1234')->json('event_uuid');
    foreach (['/api/v1/staff/anpr/events', "/api/v1/staff/anpr/events/{$uuid}", '/api/v1/reports/anpr/overview', '/api/v1/reports/anpr/camera-health', '/api/v1/reports/anpr/recognition-history', '/api/v1/audit/anpr/corrections'] as $url) {
        $this->actingAs($this->auditor)->getJson($url)->assertOk();
    }
    $this->actingAs($this->auditor)->postJson("/api/v1/staff/anpr/events/{$uuid}/verify", ['action' => 'confirm'])->assertForbidden();
    $this->actingAs($this->auditor)->postJson('/api/v1/staff/anpr/entry/confirm', ['event_uuid' => $uuid])->assertForbidden();

    $customer = userWithRole(User::ROLE_CUSTOMER);
    foreach (['/api/v1/staff/anpr/events', '/api/v1/reports/anpr/overview', '/api/v1/staff/anpr/cameras', '/api/v1/admin/anpr/cameras'] as $url) {
        $this->actingAs($customer)->getJson($url)->assertForbidden();
    }
});

it('reports ANPR statistics without claiming accuracy', function () {
    devicePush($this->entryCam, $this->entryToken, 'CAB-1234');
    devicePush($this->entryCam, $this->entryToken, 'CAB-9999', 0.40);
    devicePush($this->entryCam, $this->entryToken, null);

    $data = $this->actingAs($this->admin)->getJson('/api/v1/reports/anpr/overview')->assertOk()->json('data');
    expect($data['automatic_events'])->toBe(3)->and($data['recognition_rate_percent'])->toEqual(33.3)->and($data['note'])->toContain('NOT a measured accuracy');
});

/* =========================== compatibility & journey =========================== */

it('keeps the legacy QR exit working alongside ANPR', function () {
    [, $qr] = recordEntry($this->staff, ['plate_number' => 'QR-1234']);
    $this->actingAs($this->staff);
    settle($qr)->assertOk()->assertJsonPath('gate.status', 'simulated');
    expect(GateCommand::where('status', 'success')->count())->toBe(1);
});

it('completes an ANPR-only journey from camera to gate', function () {
    // Entry camera reads the plate; staff verify and confirm; vehicle parks 3 hours; exit camera; pay; gate.
    $in = devicePush($this->entryCam, $this->entryToken, 'NC KY-4321', 0.95)->json('event_uuid');
    verifyEvent($this->staff, $in);
    anprEntry($this->staff, $in, ['confirm_plate' => 'NC KY-4321'])->assertCreated();

    $this->travel(3)->hours();
    $out = devicePush($this->exitCam, $this->exitToken, 'NC KY 4321', 0.93)->json('event_uuid');
    verifyEvent($this->staff, $out);
    $this->actingAs($this->staff)->postJson('/api/v1/staff/anpr/exit/settle', ['event_uuid' => $out, 'confirm_plate' => 'NCKY4321', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('receipt.total_minor', 150000)->assertJsonPath('gate.status', 'simulated');

    $session = ParkingSession::first();
    expect($session->status)->toBe('completed')
        ->and(AnprEvent::where('parking_session_id', $session->id)->pluck('direction')->sort()->values()->all())->toBe(['entry', 'exit'])
        ->and(ParkingSlot::find($session->parking_slot_id)->status)->toBe('available');
});

it('AI region suggestions require explicit verification even for high-confidence readings', function () {
    app(SettingsService::class)->set('anpr.verification_mode', 'below_threshold', $this->admin);

    $suggestions = [[
        'text' => 'NW KL-6036',
        'confidence' => 0.1911,
        'review_required' => true,
        'source' => 'region_ocr',
    ]];

    $uuid = devicePush(
        $this->entryCam,
        $this->entryToken,
        'KL6036',
        0.9997,
        (string) Str::uuid(),
        ['review_suggestions' => $suggestions]
    )->assertCreated()->json('event_uuid');

    $event = AnprEvent::where('uuid', $uuid)->firstOrFail();

    expect($event->verification_status)->toBe('pending')
        ->and($event->review_suggestions)->toBe($suggestions)
        ->and(app(AnprEventService::class)->requiresExplicitVerification($event))->toBeTrue();

    anprEntry($this->staff, $uuid, [
        'confirm_plate' => 'KL6036',
    ])->assertStatus(409)->assertJsonPath('error_code', 'verification_required');

    expect(ParkingSession::count())->toBe(0)
        ->and(GateCommand::count())->toBe(0)
        ->and($event->fresh()->verification_status)->toBe('pending');
});
it('adaptive OCR partial clues require explicit verification even at high confidence', function () {
    app(SettingsService::class)->set('anpr.verification_mode', 'below_threshold', $this->admin);

    $clues = [
        'province_clues' => [
            ['text' => 'SP', 'confidence' => 0.5724, 'angle' => 10],
        ],
        'registration_clues' => [
            ['text' => 'XJ2284', 'confidence' => 0.9515, 'angle' => 30],
        ],
        'review_required' => true,
        'verified' => false,
    ];

    $uuid = devicePush(
        $this->entryCam,
        $this->entryToken,
        'SPX7228',
        0.9997,
        (string) Str::uuid()
    )->assertCreated()->json('event_uuid');

    $event = AnprEvent::where('uuid', $uuid)->firstOrFail();

    $event->partial_review_clues = $clues;
    $event->save();
    $event->refresh();

    expect($event->partial_review_clues)->toBe($clues)
        ->and($event->verification_status)->toBe('pending')
        ->and(app(AnprEventService::class)->requiresExplicitVerification($event))->toBeTrue();

    anprEntry($this->staff, $uuid, [
        'confirm_plate' => 'SPX7228',
    ])->assertStatus(409)->assertJsonPath('error_code', 'verification_required');

    expect(ParkingSession::count())->toBe(0)
        ->and(GateCommand::count())->toBe(0)
        ->and($event->fresh()->verification_status)->toBe('pending');
});
it('accepts valid adaptive OCR partial clues from a camera device', function () {
    $clues = [
        'province_clues' => [
            ['text' => 'SP', 'confidence' => 0.5724, 'angle' => 10],
        ],
        'registration_clues' => [
            ['text' => 'XJ2284', 'confidence' => 0.9515, 'angle' => 30],
        ],
        'review_required' => true,
        'verified' => false,
    ];

    $response = devicePush(
        $this->entryCam,
        $this->entryToken,
        'SPX7228',
        0.9997,
        (string) Str::uuid(),
        ['partial_review_clues' => json_encode($clues)]
    )->assertCreated();

    $event = AnprEvent::where('uuid', $response->json('event_uuid'))->firstOrFail();

    expect($event->partial_review_clues)->toBe($clues)
        ->and($event->verification_status)->toBe('pending')
        ->and(app(AnprEventService::class)->requiresExplicitVerification($event))->toBeTrue()
        ->and(ParkingSession::count())->toBe(0)
        ->and(GateCommand::count())->toBe(0);
});

it('rejects invalid adaptive OCR partial clues from a camera device', function () {
    $clues = [
        'province_clues' => [
            ['text' => 'SP', 'confidence' => 1.5, 'angle' => 10],
        ],
        'registration_clues' => [
            ['text' => 'XJ2284', 'confidence' => 0.9515, 'angle' => 30],
        ],
        'review_required' => true,
        'verified' => true,
    ];

    devicePush(
        $this->entryCam,
        $this->entryToken,
        'SPX7228',
        0.9997,
        (string) Str::uuid(),
        ['partial_review_clues' => json_encode($clues)]
    )->assertStatus(422)
        ->assertJsonValidationErrors([
            'partial_review_clues.province_clues.0.confidence',
            'partial_review_clues.verified',
        ]);

    expect(AnprEvent::count())->toBe(0)
        ->and(ParkingSession::count())->toBe(0)
        ->and(GateCommand::count())->toBe(0);
});
it('one-step confirmation works for high-confidence readings in below_threshold mode', function () {
    app(SettingsService::class)->set('anpr.verification_mode', 'below_threshold', $this->admin);
    $uuid = devicePush($this->entryCam, $this->entryToken, 'CAB-4444', 0.98)->json('event_uuid');

    anprEntry($this->staff, $uuid, ['confirm_plate' => 'CAB-4444'])->assertCreated();
    expect(AnprReviewAction::where('action', 'confirm')->where('user_id', $this->staff->id)->exists())->toBeTrue();
});
