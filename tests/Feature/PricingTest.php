<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PricingService;
use App\Services\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedParking();
    $this->pricing = app(PricingService::class);
});

/**
 * Quote using the currently seeded EXAMPLE car rates.
 */
function quoteFor(string $packageCode, string $entry, string $exit, bool $safety = false, string $category = 'car'): array
{
    $pricing = app(PricingService::class);
    $package = package($packageCode);
    $tz = 'Asia/Colombo';
    $entryAt = CarbonImmutable::parse($entry, $tz);
    $rate = $package->rateFor(category($category)->id, $entryAt);
    $safetyRate = $safety ? package('safety')->rateFor(category($category)->id, $entryAt) : null;

    return $pricing->calculate($pricing->snapshot($package, $rate, $safetyRate), $entryAt, CarbonImmutable::parse($exit, $tz), $safety);
}

it('charges short-time parking per started hour with a minimum of one block', function (string $exit, int $expected) {
    expect(quoteFor('short_time', '2026-11-01 10:00', $exit)['total_minor'])->toBe($expected);
})->with([
    'immediate exit' => ['2026-11-01 10:00', 10000],
    '30 minutes' => ['2026-11-01 10:30', 10000],
    '61 minutes' => ['2026-11-01 11:01', 20000],
    '26 hours, daily cap applied to day one' => ['2026-11-02 12:00', 170000],
]);

it('charges a daily package with a 15 minute grace period', function (string $exit, int $expected) {
    expect(quoteFor('daily', '2026-11-01 08:00', $exit)['total_minor'])->toBe($expected);
})->with([
    'within package' => ['2026-11-01 20:00', 150000],
    'exactly at expiry' => ['2026-11-02 08:00', 150000],
    'inside grace' => ['2026-11-02 08:15', 150000],
    'one minute past grace (overtime from package end)' => ['2026-11-02 08:16', 160000],
    '75 minutes over' => ['2026-11-02 09:15', 170000],
]);

it('caps each overtime day at the daily rate and charges later days separately', function () {
    // 30 hours of overtime: day 1 = 24 × 100 = 2400 → capped 1500; day 2 = 6 × 100 = 600.
    $quote = quoteFor('daily', '2026-11-01 08:00', '2026-11-03 14:00');

    expect($quote['overtime_minutes'])->toBe(1800)
        ->and(collect($quote['lines'])->firstWhere('code', 'overtime')['amount_minor'])->toBe(210000)
        ->and($quote['total_minor'])->toBe(360000);
});

it('can start overtime at the end of grace when configured', function () {
    app(SettingsService::class)->set('pricing.overtime_starts_from', 'grace_end', userWithRole(User::ROLE_ADMIN));

    expect(quoteFor('daily', '2026-11-01 08:00', '2026-11-02 09:15')['total_minor'])->toBe(160000);
});

it('treats weekly as 7 days and prorates early exit by days used', function () {
    $full = quoteFor('weekly', '2026-11-01 08:00', '2026-11-08 08:00');
    expect($full['total_minor'])->toBe(900000)->and($full['early_exit_unused_minor'])->toBe(0);

    // 3 days 2 hours → 4 started days of 7 → 900000 × 4 / 7 = 514285.71 → 514286 (half-up).
    $early = quoteFor('weekly', '2026-11-01 08:00', '2026-11-04 10:00');
    expect($early['total_minor'])->toBe(514286)->and($early['early_exit_unused_minor'])->toBe(385714);
});

it('treats monthly as a fixed 30 days', function () {
    $quote = quoteFor('monthly', '2026-11-01 08:00', '2026-12-01 08:10');

    expect($quote['package_ends_at'])->toBe(CarbonImmutable::parse('2026-12-01 08:00', 'Asia/Colombo')->utc()->toIso8601String())
        ->and($quote['total_minor'])->toBe(3000000);

    expect(quoteFor('monthly', '2026-11-01 08:00', '2026-12-01 09:00')['total_minor'])->toBe(3010000);
});

it('ends the night package at the configured local morning time', function () {
    $quote = quoteFor('night', '2026-11-01 20:00', '2026-11-02 07:00');

    expect($quote['package_ends_at'])->toBe(CarbonImmutable::parse('2026-11-02 06:00', 'Asia/Colombo')->utc()->toIso8601String())
        ->and($quote['total_minor'])->toBe(90000);
});

it('adds the safety charge per started day and safety overtime', function () {
    $quote = quoteFor('daily', '2026-11-01 08:00', '2026-11-02 10:00', safety: true);
    $lines = collect($quote['lines'])->keyBy('code');

    // 26h → 2 started days × 500 = 1000; overtime 2h → 2 × 50 = 100.
    expect($lines['safety']['amount_minor'])->toBe(100000)
        ->and($lines['safety_overtime']['amount_minor'])->toBe(10000)
        ->and($quote['total_minor'])->toBe(150000 + 20000 + 100000 + 10000);
});

it('versions rates and never applies a new price to an existing session', function () {
    $admin = userWithRole(User::ROLE_ADMIN);
    $staff = userWithRole(User::ROLE_STAFF);
    [$session, $payload] = recordEntry($staff);

    $this->actingAs($admin)->postJson('/api/v1/admin/packages/'.package('daily')->id.'/rates', [
        'vehicle_category_id' => category('car')->id, 'price_minor' => 999900, 'overtime_hourly_minor' => 50000,
    ])->assertCreated()->assertJsonPath('data.version', 2);

    expect($this->actingAs($staff)->postJson('/api/v1/staff/exits/preview', ['qr_payload' => $payload])->json('quote.total_minor'))->toBe(150000);

    [$newSession] = recordEntry($staff);
    expect($newSession->pricing_snapshot['rate']['price_minor'])->toBe(999900);

    $history = $this->actingAs($admin)->getJson('/api/v1/admin/packages/'.package('daily')->id.'/rates')->json('data');
    $car = collect($history)->where('vehicle_category_id', category('car')->id);
    expect($car)->toHaveCount(2)->and($car->firstWhere('version', 1)['effective_to'])->not->toBeNull();
    expect(AuditLog::where('action', 'pricing.rate_published')->exists())->toBeTrue();
});

it('previews a calculation for admins before use', function () {
    $this->actingAs(userWithRole(User::ROLE_ADMIN))->postJson('/api/v1/admin/pricing/preview', [
        'parking_package_id' => package('daily')->id, 'vehicle_category_id' => category('car')->id,
        'entry_at' => '2026-11-01 08:00', 'exit_at' => '2026-11-02 08:16',
    ])->assertOk()->assertJsonPath('quote.total_minor', 160000);
});

it('only lets authorised roles change prices', function () {
    $this->actingAs(userWithRole(User::ROLE_STAFF))->postJson('/api/v1/admin/packages/'.package('daily')->id.'/rates', [
        'vehicle_category_id' => category('car')->id, 'price_minor' => 1,
    ])->assertForbidden();
});

it('formats money without floats', function () {
    expect(Money::format(123456789))->toBe('1,234,567.89')
        ->and(Money::toMinor('1250.5'))->toBe(125050)
        ->and(Money::divRound(7, 2))->toBe(4);
});
