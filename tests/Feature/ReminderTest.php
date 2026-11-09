<?php

use App\Models\SessionReminder;
use App\Models\User;
use App\Notifications\PackageExpiryNotification;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
    $this->customer = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0775550000']);
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00', 'Asia/Colombo'));
});

it('calculates remaining time for fixed packages and reports no fixed expiry for hourly ones', function () {
    [$daily] = recordEntry($this->staff);
    [$hourly] = recordEntry($this->staff, ['parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);

    expect($daily->timeStatus())->toMatchArray(['status' => 'active', 'remaining_minutes' => 1440])
        ->and($hourly->timeStatus())->toMatchArray(['status' => 'no_fixed_expiry', 'remaining_minutes' => null]);

    $this->travel(23 * 60 + 30)->minutes();
    expect($daily->timeStatus(null, 60))->toMatchArray(['status' => 'expiring_soon', 'remaining_minutes' => 30]);

    $this->travel(40)->minutes(); // 10 min past end, inside 15-minute grace
    expect($daily->timeStatus())->toMatchArray(['status' => 'in_grace', 'overdue_minutes' => 10]);

    $this->travel(10)->minutes();
    expect($daily->timeStatus())->toMatchArray(['status' => 'expired', 'overdue_minutes' => 20]);

    $this->actingAs($this->staff)->getJson("/api/v1/staff/sessions/{$daily->uuid}")->assertJsonPath('data.time.status', 'expired');
});

it('lists sessions approaching expiry and already expired for staff', function () {
    [$soon] = recordEntry($this->staff);
    $this->travel(12)->hours();
    [$later] = recordEntry($this->staff);
    $this->travel(11 * 60 + 30)->minutes(); // $soon ends in 30 min, $later in 12h30

    $uuids = collect($this->actingAs($this->staff)->getJson('/api/v1/staff/sessions/expiring?within=60')->assertOk()->json('data'))->pluck('uuid');
    expect($uuids->all())->toBe([$soon->uuid]);
    $this->actingAs($this->customer)->getJson('/api/v1/staff/sessions/expiring')->assertForbidden();
});

it('sends one reminder before expiry and one at expiry, never duplicated', function () {
    [$session] = recordEntry($this->staff, ['customer_phone' => '0775550000']);

    $this->artisan('parking:send-expiry-reminders')->assertSuccessful();
    expect($this->customer->notifications()->count())->toBe(0);

    $this->travel(23 * 60 + 10)->minutes(); // 50 min left
    $this->artisan('parking:send-expiry-reminders')->assertSuccessful();
    $this->artisan('parking:send-expiry-reminders')->assertSuccessful();
    expect($this->customer->notifications()->count())->toBe(1)
        ->and($this->customer->notifications()->first()->data['kind'])->toBe('package_expiring');

    $this->travel(1)->hours();
    $this->artisan('parking:send-expiry-reminders')->assertSuccessful();
    $this->artisan('parking:send-expiry-reminders')->assertSuccessful();

    $kinds = $this->customer->notifications()->get()->pluck('data.kind')->sort()->values()->all();
    expect($kinds)->toBe(['package_expired', 'package_expiring'])
        ->and(SessionReminder::where('parking_session_id', $session->id)->count())->toBe(2);
});

it('sends a single reminder even when several thresholds are crossed at once', function () {
    app(SettingsService::class)->set('reminders.before_expiry_minutes', [120, 60, 15], userWithRole(User::ROLE_ADMIN));
    recordEntry($this->staff, ['customer_phone' => '0775550000']);

    $this->travel(23 * 60 + 50)->minutes(); // 10 minutes left: all three crossed
    $this->artisan('parking:send-expiry-reminders');

    expect($this->customer->notifications()->count())->toBe(1)
        ->and(SessionReminder::where('kind', 'expiring')->count())->toBe(3);
});

it('only notifies the session owner and skips walk-ins, hourly packages and closed sessions', function () {
    Notification::fake();
    [$walkIn] = recordEntry($this->staff);
    recordEntry($this->staff, ['customer_phone' => '0775550000', 'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);
    [$owned, $payload] = recordEntry($this->staff, ['customer_phone' => '0775550000']);
    $this->actingAs($this->staff);
    settle($payload)->assertOk();

    $this->travel(2)->days();
    $this->artisan('parking:send-expiry-reminders');

    Notification::assertNotSentTo($this->customer, PackageExpiryNotification::class);
    expect(SessionReminder::count())->toBe(0);
});

it('lets customers read and mark reminder notifications', function () {
    recordEntry($this->staff, ['customer_phone' => '0775550000']);
    $this->travel(25)->hours();
    $this->artisan('parking:send-expiry-reminders');

    $id = $this->actingAs($this->customer)->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.data.kind', 'package_expired')->assertJsonPath('data.0.read_at', null)->json('data.0.id');
    $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
    expect($this->customer->unreadNotifications()->count())->toBe(0);

    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
});

it('respects the reminder settings and validates them', function () {
    $admin = userWithRole(User::ROLE_ADMIN);
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/reminders.before_expiry_minutes', ['value' => [30, 120]])->assertOk()->assertJsonPath('value', [120, 30]);
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/reminders.before_expiry_minutes', ['value' => [0]])->assertUnprocessable();
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/reminders.enabled', ['value' => false])->assertOk();

    recordEntry($this->staff, ['customer_phone' => '0775550000']);
    $this->travel(25)->hours();
    $this->artisan('parking:send-expiry-reminders');
    expect($this->customer->notifications()->count())->toBe(0);
});
