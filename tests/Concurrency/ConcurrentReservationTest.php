<?php

/*
| Multi-process races for guaranteed parking. Requires MySQL + pcntl; run explicitly against a
| throwaway database:  DB_CONNECTION=mysql DB_DATABASE=<scratch db> ... php artisan test tests/Concurrency
*/

use App\Exceptions\ParkingException;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\EntryService;
use App\Services\ExitService;
use App\Services\ReservationService;
use App\Services\ZoneService;
use Carbon\CarbonImmutable;
use Database\Seeders\ParkingLayoutSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Needs MySQL and pcntl.');
    }
    Artisan::call('migrate:fresh', ['--force' => true]);
    (new ReferenceDataSeeder)->run();
    (new ParkingLayoutSeeder)->run();
});

/**
 * Exit codes: 0 ok, 10 no slot, 11 duplicate entry, 13 other business rule, 1 unexpected error.
 */
function raceProcs(int $count, callable $work): array
{
    $startAt = microtime(true) + 1.0;
    $pids = [];
    DB::disconnect();
    for ($i = 0; $i < $count; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            DB::reconnect();
            time_sleep_until($startAt);
            try {
                $work($i);
                exit(0);
            } catch (ParkingException $e) {
                fwrite(STDERR, 'BIZ '.$e->errorCode."\n");
                exit(match ($e->errorCode) {
                    'no_slot_available' => 10,
                    'duplicate_active_entry' => 11,
                    default => 13,
                });
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage()."\n");
                exit(1);
            }
        }
        $pids[] = $pid;
    }
    $codes = [];
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wexitstatus($status);
    }
    DB::reconnect();

    return $codes;
}

function monthlyInput(string $plate): array
{
    return ['plate_number' => $plate, 'vehicle_category_id' => category('car')->id, 'parking_zone_id' => zone('D')->id, 'parking_package_id' => package('monthly')->id];
}

it('creates at most one reservation per slot when many first entries race', function () {
    app(ZoneService::class)->setCapacity(zone('D'), category('car'), 3);
    $staffId = User::factory()->staff()->create()->id;

    $codes = raceProcs(8, fn (int $i) => app(EntryService::class)->recordEntry(User::find($staffId), monthlyInput("MON-RACE-{$i}")));

    expect(array_count_values($codes))->toEqual([0 => 3, 10 => 5])
        ->and(ParkingReservation::where('status', 'active')->count())->toBe(3)
        ->and(ParkingReservation::where('status', 'active')->distinct()->count('parking_slot_id'))->toBe(3);
});

it('lets exactly one concurrent re-entry claim the reserved slot', function () {
    $staff = User::factory()->staff()->create();
    [$session, $qr] = app(EntryService::class)->recordEntry($staff, monthlyInput('MON-REENTRY'));
    $this->actingAs($staff);
    app(ExitService::class)->settle($staff, ['qr_payload' => $qr, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash']);
    expect(ParkingSlot::find($session->parking_slot_id)->status)->toBe('reserved');

    $codes = raceProcs(5, fn () => app(EntryService::class)->recordEntry(User::find($staff->id), monthlyInput('MON-REENTRY')));

    expect(array_count_values($codes))->toEqual([0 => 1, 11 => 4])
        ->and(ParkingSession::where('parking_reservation_id', $session->parking_reservation_id)->whereIn('status', ['active'])->count())->toBe(1)
        ->and(ParkingSlot::find($session->parking_slot_id)->status)->toBe('occupied');
});

it('never strands a slot when expiry runs concurrently with re-entry at the expiry instant', function () {
    $staff = User::factory()->staff()->create();
    [$session, $qr] = app(EntryService::class)->recordEntry($staff, monthlyInput('MON-EXP'));
    app(ExitService::class)->settle($staff, ['qr_payload' => $qr, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash']);
    $at = $session->reservation->expires_at->addSecond()->toIso8601String();

    $codes = raceProcs(4, function (int $i) use ($at, $staff) {
        $this->travelTo(CarbonImmutable::parse($at));
        $i % 2 === 0
            ? app(ReservationService::class)->expireDue(CarbonImmutable::parse($at))
            : app(EntryService::class)->recordEntry(User::find($staff->id), monthlyInput('MON-EXP'));
    });

    expect($codes)->each->toBeIn([0, 11])
        ->and(ParkingReservation::where('status', 'active')->count())->toBe(1)
        ->and(ParkingSession::whereIn('status', ['active'])->count())->toBe(1)
        ->and(ParkingSlot::whereIn('status', ['occupied'])->count())->toBe(1)
        ->and(ParkingSlot::where('status', 'reserved')->count())->toBe(0);
});
