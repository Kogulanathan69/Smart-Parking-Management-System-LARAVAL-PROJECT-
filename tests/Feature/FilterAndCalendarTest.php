<?php

use App\Models\ParkingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    seedParking();
    $this->staff = userWithRole(User::ROLE_STAFF);
    $this->auditor = userWithRole(User::ROLE_AUDITOR);
});

function at(string $local): void
{
    test()->travelTo(CarbonImmutable::parse($local, 'Asia/Colombo'));
}

function uuidsFrom(TestResponse $response): array
{
    return collect($response->assertOk()->json('data'))->pluck('uuid')->sort()->values()->all();
}

/**
 * Fixture: 4 sessions across two local days with different categories, fuels and outcomes.
 *
 * @return array<string, ParkingSession>
 */
function filterFixture(User $staff): array
{
    at('2026-11-03 09:00');
    [$petrolCar, $p1] = recordEntry($staff, ['plate_number' => 'CAR-PET', 'fuel_type' => 'petrol']);
    [$dieselVan] = recordEntry($staff, ['plate_number' => 'VAN-DSL', 'fuel_type' => 'diesel', 'vehicle_category_id' => category('van')->id]);
    at('2026-11-03 23:50'); // just before local midnight (18:20 UTC)
    [$dieselCar, $p3] = recordEntry($staff, ['plate_number' => 'CAR-DSL', 'fuel_type' => 'diesel']);
    at('2026-11-04 00:10'); // just after local midnight
    [$electricCar] = recordEntry($staff, ['plate_number' => 'CAR-EV', 'fuel_type' => 'electric', 'parking_zone_id' => zone('A')->id, 'parking_package_id' => package('short_time')->id]);
    test()->actingAs($staff);
    settle($p1)->assertOk();                                // petrol car exits 04 Nov, cash
    settle($p3, ['payment_method' => 'online'])->assertStatus(202); // diesel car: payment pending

    return compact('petrolCar', 'dieselVan', 'dieselCar', 'electricCar');
}

it('filters by each of the four primary dimensions', function () {
    $s = filterFixture($this->staff);
    $this->actingAs($this->auditor);
    $base = '/api/v1/reports/sessions?from=2026-11-01&to=2026-11-30';

    expect(uuidsFrom($this->getJson("$base&vehicle_category=van")))->toBe([$s['dieselVan']->uuid]);
    expect(uuidsFrom($this->getJson("$base&vehicle_category_id=".category('car')->id)))->toHaveCount(3);
    expect(uuidsFrom($this->getJson("$base&fuel_type=diesel")))->toBe(collect([$s['dieselVan']->uuid, $s['dieselCar']->uuid])->sort()->values()->all());
    expect(uuidsFrom($this->getJson("$base&status=completed")))->toBe([$s['petrolCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&status=active,completed")))->toHaveCount(4);
    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?from=2026-11-04&to=2026-11-04')))->toBe([$s['electricCar']->uuid]);
});

it('combines filters, including plate, zone, package and payment status', function () {
    $s = filterFixture($this->staff);
    $this->actingAs($this->auditor);
    $base = '/api/v1/reports/sessions?from=2026-11-01&to=2026-11-30';

    expect(uuidsFrom($this->getJson("$base&vehicle_category=car&fuel_type=diesel&status=active")))->toBe([$s['dieselCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&vehicle_category=car&fuel_type=diesel&status=completed")))->toBe([]);
    expect(uuidsFrom($this->getJson("$base&plate=car-ev")))->toBe([$s['electricCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&zone_id=".zone('A')->id.'&package_id='.package('short_time')->id)))->toBe([$s['electricCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&payment_status=paid")))->toBe([$s['petrolCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&payment_status=pending")))->toBe([$s['dieselCar']->uuid]);
    expect(uuidsFrom($this->getJson("$base&payment_status=unpaid&fuel_type=diesel")))->toBe(collect([$s['dieselVan']->uuid, $s['dieselCar']->uuid])->sort()->values()->all());
});

it('validates filter parameters', function (string $query) {
    $this->actingAs($this->auditor)->getJson("/api/v1/reports/sessions?$query")->assertUnprocessable();
})->with([
    'fuel_type=steam', 'status=parked', 'from=2026-11-05&to=2026-11-01', 'date=03-11-2026',
    'date=2026-11-03&from=2026-11-01', 'payment_status=maybe', 'vehicle_category=spaceship', 'per_page=500', 'date_field=yesterday',
]);

it('uses Sri Lanka calendar days for entries and exits on a selected date', function () {
    $s = filterFixture($this->staff);
    $this->actingAs($this->auditor);

    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?date=2026-11-03&date_field=entry')))->toHaveCount(3);
    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?date=2026-11-04&date_field=entry')))->toBe([$s['electricCar']->uuid]);
    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?date=2026-11-04&date_field=exit')))->toBe([$s['petrolCar']->uuid]);
    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?date=2026-11-04&date_field=present')))->toHaveCount(4);
    expect(uuidsFrom($this->getJson('/api/v1/reports/sessions?date=2026-11-02&date_field=present')))->toBe([]);
});

it('returns a calendar with daily entries, exits, parked vehicles and revenue', function () {
    filterFixture($this->staff);

    $days = collect($this->actingAs($this->auditor)->getJson('/api/v1/reports/calendar?month=2026-11')->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Colombo')->json('data.days'))->keyBy('date');

    expect($days)->toHaveCount(30)
        ->and($days['2026-11-03'])->toMatchArray(['entries' => 3, 'exits' => 0, 'parked_at_end_of_day' => 3, 'revenue_minor' => 0])
        ->and($days['2026-11-04'])->toMatchArray(['entries' => 1, 'exits' => 1, 'parked_at_end_of_day' => 3, 'revenue_minor' => 150000])
        ->and($days['2026-11-05']['parked_at_end_of_day'])->toBe(3);

    $this->actingAs($this->auditor)->getJson('/api/v1/reports/calendar?from=2026-01-01&to=2026-12-31')->assertUnprocessable()->assertJsonPath('error_code', 'range_too_large');
    $this->actingAs($this->staff)->getJson('/api/v1/reports/calendar?month=2026-11')->assertForbidden();
});

it('keeps the staff list defaulting to open sessions while allowing history filters', function () {
    $s = filterFixture($this->staff);
    $this->actingAs($this->staff);

    expect(uuidsFrom($this->getJson('/api/v1/staff/sessions')))->toHaveCount(3);
    expect(uuidsFrom($this->getJson('/api/v1/staff/sessions?q=van')))->toBe([$s['dieselVan']->uuid]);
    expect(uuidsFrom($this->getJson('/api/v1/staff/sessions?status=completed&date=2026-11-04&date_field=exit')))->toBe([$s['petrolCar']->uuid]);
    $this->getJson('/api/v1/staff/sessions?per_page=2')->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.total', 3);
});

it('scopes customer history to the customer even with filters', function () {
    $owner = userWithRole(User::ROLE_CUSTOMER, ['phone' => '0779990000']);
    at('2026-11-03 09:00');
    [$mine] = recordEntry($this->staff, ['customer_phone' => '0779990000', 'fuel_type' => 'diesel']);
    recordEntry($this->staff, ['fuel_type' => 'diesel']);

    expect(uuidsFrom($this->actingAs($owner)->getJson('/api/v1/customer/sessions?fuel_type=diesel&date=2026-11-03')))->toBe([$mine->uuid]);
    expect(uuidsFrom($this->actingAs(userWithRole(User::ROLE_CUSTOMER))->getJson('/api/v1/customer/sessions?fuel_type=diesel')))->toBe([]);
});

it('keeps report session history on the category recorded at entry', function () {
    $s = filterFixture($this->staff);
    $s['dieselVan']->vehicle->forceFill(['fuel_type' => 'electric'])->save();

    expect(uuidsFrom($this->actingAs($this->auditor)->getJson('/api/v1/reports/sessions?from=2026-11-01&to=2026-11-30&fuel_type=diesel&vehicle_category=van')))
        ->toBe([$s['dieselVan']->uuid]);
});

it('publishes the configurable filter definitions', function () {
    $this->actingAs($this->staff)->getJson('/api/v1/reference-data')->assertOk()
        ->assertJsonPath('data.session_filters.primary', ['vehicle_category', 'fuel_type', 'status', 'date_range']);
});
