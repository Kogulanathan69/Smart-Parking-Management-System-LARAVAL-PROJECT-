<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

it('returns honest empty states when the optional ANPR schema is absent', function () {
    $admin = userWithRole(User::ROLE_SUPER_ADMIN);

    Schema::disableForeignKeyConstraints();
    foreach (['anpr_review_actions', 'anpr_event_images', 'anpr_camera_health_logs', 'anpr_events', 'anpr_cameras'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();

    $this->actingAs($admin)->getJson('/api/v1/staff/anpr/events')
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('configuration_state', 'not_configured');

    $this->actingAs($admin)->getJson('/api/v1/admin/anpr/cameras')
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.configuration_state', 'not_configured');

    $this->actingAs($admin)->getJson('/api/v1/reports/anpr/overview')
        ->assertOk()->assertJsonPath('data.configured', false)->assertJsonPath('data.automatic_events', 0);

    $this->actingAs($admin)->getJson('/api/v1/reports/anpr/camera-health')
        ->assertOk()->assertJsonPath('data.configuration_state', 'not_configured')->assertJsonCount(0, 'data.cameras');

    $this->actingAs($admin)->getJson('/api/v1/audit/anpr/corrections')
        ->assertOk()->assertJsonPath('configuration_state', 'not_configured')->assertJsonCount(0, 'data');

    $this->actingAs($admin)->getJson('/api/v1/admin/system/status')
        ->assertOk()->assertJsonPath('data.cameras.configuration_state', 'not_configured');
});

it('blocks camera registration when the optional ANPR schema is absent', function () {
    $admin = userWithRole(User::ROLE_SUPER_ADMIN);
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('anpr_cameras');
    Schema::enableForeignKeyConstraints();

    $this->actingAs($admin)->postJson('/api/v1/admin/anpr/cameras', [
        'code' => 'CAM-1', 'name' => 'Gate camera', 'direction' => 'entry', 'camera_type' => 'usb_local',
    ])->assertConflict()->assertJsonPath('error_code', 'anpr_not_configured');
});

it('returns a clear fallback response for a valid recognition upload when ANPR is absent', function () {
    $staff = userWithRole(User::ROLE_STAFF);
    Schema::disableForeignKeyConstraints();
    foreach (['anpr_review_actions', 'anpr_event_images', 'anpr_camera_health_logs', 'anpr_events', 'anpr_cameras'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();

    $image = UploadedFile::fake()->createWithContent(
        'plate.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
    );

    $this->actingAs($staff)->post('/api/v1/staff/anpr/recognize', [
        'direction' => 'entry',
        'image' => $image,
        'idempotency_key' => 'recognize-without-schema',
    ], ['Accept' => 'application/json'])
        ->assertConflict()
        ->assertJsonPath('error_code', 'anpr_not_configured')
        ->assertJsonPath('manual_fallback', true);
});

it('validates a bad recognition upload before reporting missing ANPR configuration', function () {
    $staff = userWithRole(User::ROLE_STAFF);
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('anpr_cameras');
    Schema::enableForeignKeyConstraints();

    $this->actingAs($staff)->post('/api/v1/staff/anpr/recognize', [
        'direction' => 'entry',
        'image' => UploadedFile::fake()->create('not-an-image.txt', 2, 'text/plain'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});
