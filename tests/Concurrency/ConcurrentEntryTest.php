<?php

/*
| Real multi-process race test. Requires MySQL (row locks) and the pcntl extension.
| Not part of the default suite. Run explicitly against a disposable database:
|
|   DB_CONNECTION=mysql DB_DATABASE=smart_parking_test ... php artisan test tests/Concurrency
*/

use App\Exceptions\ParkingException;
use App\Models\ParkingSession;
use App\Models\User;
use App\Services\EntryService;
use App\Services\ZoneService;
use Database\Seeders\ParkingLayoutSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
 * Fork $count workers that all start at the same instant; returns their exit codes.
 * Exit code 0 = entry recorded, 10 = no slot, 11 = duplicate entry, 1 = unexpected error.
 */
function race(int $count, callable $work): array
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
                exit(match ($e->errorCode) {
                    'no_slot_available' => 10, 'duplicate_active_entry' => 11, default => 2
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

it('never double-allocates a slot when many entries race for few slots', function () {
    $zone = zone('B');
    app(ZoneService::class)->setCapacity($zone, category('van'), 3);
    $staff = User::factory()->staff()->create();
    $ids = [$staff->id, category('van')->id, $zone->id, package('daily')->id];

    $codes = race(10, function (int $i) use ($ids) {
        app(EntryService::class)->recordEntry(User::find($ids[0]), [
            'plate_number' => "VAN-RACE-{$i}", 'vehicle_category_id' => $ids[1], 'parking_zone_id' => $ids[2], 'parking_package_id' => $ids[3],
        ]);
    });

    expect(array_count_values($codes))->toEqual([0 => 3, 10 => 7])
        ->and(ParkingSession::where('status', 'active')->distinct()->count('parking_slot_id'))->toBe(3);
});

it('never creates two active sessions for one vehicle under concurrent entries', function () {
    $staff = User::factory()->staff()->create();
    $ids = [$staff->id, category('car')->id, zone('B')->id, package('daily')->id];

    $codes = race(6, function () use ($ids) {
        app(EntryService::class)->recordEntry(User::find($ids[0]), [
            'plate_number' => 'CAR-SAME-1', 'vehicle_category_id' => $ids[1], 'parking_zone_id' => $ids[2], 'parking_package_id' => $ids[3],
        ]);
    });

    expect(array_count_values($codes))->toEqual([0 => 1, 11 => 5])
        ->and(ParkingSession::where('status', 'active')->count())->toBe(1);
});
