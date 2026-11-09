<?php

use App\Models\EvidenceFile;
use App\Models\Incident;
use App\Models\SafetyInspection;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use App\Services\SafetyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
});

/**
 * A real 1×1 PNG so tests do not depend on the GD extension.
 */
function tinyPng(string $name = 'photo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
}

function safetyEntry(User $staff, array $extra = []): array
{
    return recordEntry($staff, array_merge(['parking_zone_id' => zone('E')->id, 'parking_package_id' => package('daily')->id], $extra));
}

it('schedules entry and routine inspections at the configured frequency', function () {
    [$session] = safetyEntry($this->staff);

    expect($session->has_safety)->toBeTrue()
        ->and($session->inspections()->where('type', 'entry')->count())->toBe(1)
        ->and($session->inspections()->where('type', 'routine')->count())->toBe(2);

    $this->artisan('parking:schedule-inspections')->assertSuccessful();
    expect($session->inspections()->where('type', 'routine')->count())->toBe(2);
});

it('requires an exit condition inspection before a safety vehicle can leave', function () {
    [$session, $payload] = safetyEntry($this->staff);
    $this->actingAs($this->staff);

    $this->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $payload])->assertOk()->assertJsonPath('quote.requires_exit_inspection', true);
    $this->postJson('/api/v1/staff/exits/settle', ['qr_payload' => $payload, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'exit_inspection_required');

    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/inspections", ['type' => 'entry', 'condition' => 'good'])->assertCreated();
    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/inspections", ['type' => 'exit', 'condition' => 'good'])->assertCreated();

    $this->postJson('/api/v1/staff/exits/settle', ['qr_payload' => $payload, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash'])
        ->assertOk()->assertJsonPath('quote.lines.1.code', 'safety');
});

it('raises a damage incident and alerts the customer and admins', function () {
    Notification::fake();
    $customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0770000009']);
    $admin = userWithRole(User::ROLE_ADMIN);
    [$session] = safetyEntry($this->staff, ['customer_phone' => '0770000009']);

    $this->actingAs($this->staff)->postJson("/api/v1/staff/sessions/{$session->uuid}/inspections", [
        'type' => 'routine', 'condition' => 'minor_damage', 'new_damage_found' => true, 'notes' => 'Scratch on rear bumper',
    ])->assertCreated()->assertJsonPath('data.new_damage_found', true);

    expect(Incident::where('parking_session_id', $session->id)->where('type', 'damage')->exists())->toBeTrue();
    Notification::assertSentTo($customer, DamageReportedNotification::class);
    Notification::assertSentTo($admin, DamageReportedNotification::class);
});

it('does not allow recording the same inspection twice', function () {
    [$session] = safetyEntry($this->staff);
    $inspection = $session->inspections()->where('type', 'entry')->first();
    $this->actingAs($this->staff);

    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/inspections", ['type' => 'entry', 'condition' => 'good', 'safety_inspection_id' => $inspection->id])->assertCreated();
    $this->postJson("/api/v1/staff/sessions/{$session->uuid}/inspections", ['type' => 'entry', 'condition' => 'good', 'safety_inspection_id' => $inspection->id])->assertStatus(409);
});

it('stores photo evidence privately and restricts who can view it', function () {
    Storage::fake('local');
    $owner = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0770000010']);
    $other = userWithRole(User::ROLE_CUSTOMER);
    [$session] = safetyEntry($this->staff, ['customer_phone' => '0770000010']);
    $inspection = $session->inspections()->where('type', 'entry')->first();

    $uuid = $this->actingAs($this->staff)->post('/api/v1/staff/evidence', [
        'attachable_type' => 'inspection', 'attachable_id' => $inspection->id,
        'file' => tinyPng('../../etc/passwd.png'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');

    $evidence = EvidenceFile::where('uuid', $uuid)->firstOrFail();
    expect($evidence->path)->toStartWith('evidence/')->not->toContain('passwd')
        ->and($evidence->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($evidence->path);

    $this->actingAs($this->staff)->get("/api/v1/evidence/{$uuid}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($owner)->get("/api/v1/evidence/{$uuid}")->assertOk();
    $this->actingAs($other)->getJson("/api/v1/evidence/{$uuid}")->assertForbidden();
});

it('rejects non-image and oversized uploads', function () {
    Storage::fake('local');
    [$session] = safetyEntry($this->staff);
    $inspection = $session->inspections()->first();
    $this->actingAs($this->staff);

    $this->postJson('/api/v1/staff/evidence', ['attachable_type' => 'inspection', 'attachable_id' => $inspection->id, 'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson('/api/v1/staff/evidence', ['attachable_type' => 'inspection', 'attachable_id' => $inspection->id, 'file' => tinyPng('big.png')->size(6000)])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('marks overdue routine inspections as missed', function () {
    [$session] = safetyEntry($this->staff);
    $this->travel(15)->hours();

    $this->artisan('parking:schedule-inspections')->assertSuccessful();
    expect($session->inspections()->where('status', SafetyInspection::MISSED)->count())->toBeGreaterThanOrEqual(1);
});

it('lets admins investigate and resolve incidents with compensation', function () {
    $incident = app(SafetyService::class)->reportIncident($this->staff, ['type' => 'damage', 'description' => 'Dent']);
    $admin = userWithRole(User::ROLE_ADMIN);

    $this->actingAs($admin)->patchJson("/api/v1/admin/incidents/{$incident->id}", ['status' => 'resolved', 'resolution' => 'Paid repair', 'compensation_minor' => 500000])
        ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.compensation', '5,000.00');
    expect($incident->refresh()->resolved_by)->toBe($admin->id);
});
