<?php

/*
| Multi-process ANPR races. Requires MySQL + pcntl; run explicitly against a throwaway database:
|   DB_CONNECTION=mysql DB_DATABASE=<scratch db> ... php artisan test tests/Concurrency
*/

use App\Contracts\GateController;
use App\Exceptions\ParkingException;
use App\Models\AnprCamera;
use App\Models\AnprEvent;
use App\Models\GateCommand;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\User;
use App\Services\Anpr\AnprCameraService;
use App\Services\Anpr\AnprEventService;
use App\Services\Anpr\AnprWorkflowService;
use App\Services\GateService;
use App\Services\Integrations\LogGateController;
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
    $this->admin = userWithRole('admin');
    $this->staff = User::factory()->staff()->create();
    [$this->inCam] = app(AnprCameraService::class)->create(['code' => 'CAM-IN-R', 'name' => 'In', 'direction' => 'entry', 'camera_type' => 'ip_rtsp'], $this->admin);
    [$this->outCam] = app(AnprCameraService::class)->create(['code' => 'CAM-OUT-R', 'name' => 'Out', 'direction' => 'exit', 'camera_type' => 'ip_rtsp'], $this->admin);
});

/** Exit codes: 0 ok, 20 event already used, 21 duplicate active entry, 22 gate already opened, 23 other rule, 1 unexpected. */
function anprRace(int $count, callable $work): array
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
                    'anpr_event_already_used' => 20, 'duplicate_active_entry' => 21, 'gate_already_opened' => 22, default => 23,
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

function pushReading(AnprCamera $camera, string $plate, ?string $key = null): array
{
    return app(AnprEventService::class)->ingestFromDevice($camera, [
        'idempotency_key' => $key ?? (string) Str::uuid(), 'status' => 'recognized',
        'candidates' => [['text' => $plate, 'confidence' => 0.97]], 'processing_ms' => 50,
    ]);
}

function verifiedEvent(AnprCamera $camera, string $plate, User $staff): AnprEvent
{
    [$event] = pushReading($camera, $plate);

    return app(AnprEventService::class)->verify($event, $staff, 'confirm');
}

it('stores one event for concurrent device retries with the same idempotency key', function () {
    $camId = $this->inCam->id;
    $codes = anprRace(6, fn () => pushReading(AnprCamera::find($camId), 'CAB-1234', 'same-key-0001'));

    expect($codes)->each->toBe(0)->and(AnprEvent::count())->toBe(1);
});

it('keeps exactly one primary reading when the same plate floods in concurrently', function () {
    $camId = $this->inCam->id;
    $codes = anprRace(6, fn () => pushReading(AnprCamera::find($camId), 'CAB-1234'));

    expect($codes)->each->toBe(0)
        ->and(AnprEvent::count())->toBe(6)
        ->and(AnprEvent::where('recognition_status', '!=', 'duplicate')->count())->toBe(1);
});

it('creates one session when staff confirm the same ANPR entry event concurrently', function () {
    $event = verifiedEvent($this->inCam, 'CAB-1234', $this->staff);
    $ids = [$event->id, $this->staff->id, category('car')->id, zone('B')->id, package('daily')->id];

    $codes = anprRace(5, function () use ($ids) {
        app(AnprWorkflowService::class)->entryConfirm(AnprEvent::find($ids[0]), User::find($ids[1]), [
            'confirm_plate' => 'CAB-1234', 'vehicle_category_id' => $ids[2], 'fuel_type' => 'petrol', 'parking_zone_id' => $ids[3], 'parking_package_id' => $ids[4],
        ]);
    });

    expect(collect($codes)->filter(fn ($c) => $c === 0)->count())->toBe(1)
        ->and(collect($codes)->reject(fn ($c) => in_array($c, [0, 20, 21], true))->all())->toBe([])
        ->and(ParkingSession::count())->toBe(1)
        ->and(AnprEvent::find($event->id)->consumed_at)->not->toBeNull();
});

it('settles once and pulses the gate once when the same exit event is settled concurrently', function () {
    $in = verifiedEvent($this->inCam, 'CAB-1234', $this->staff);
    app(AnprWorkflowService::class)->entryConfirm($in, $this->staff, [
        'confirm_plate' => 'CAB-1234', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol', 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ]);
    $this->travel(30)->minutes();
    $out = verifiedEvent($this->outCam, 'CAB-1234', $this->staff);
    $ids = [$out->id, $this->staff->id];

    $codes = anprRace(5, function () use ($ids) {
        app(AnprWorkflowService::class)->exitSettle(AnprEvent::find($ids[0]), User::find($ids[1]), [
            'confirm_plate' => 'CAB-1234', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'tendered_minor' => 500000,
        ]);
    });

    expect(collect($codes)->reject(fn ($c) => in_array($c, [0, 20, 23], true))->all())->toBe([])
        ->and(Payment::where('status', 'paid')->count())->toBe(1)
        ->and(GateCommand::count())->toBe(1)
        ->and(GateCommand::where('status', 'success')->count())->toBe(1)
        ->and(ParkingSession::first()->status)->toBe('completed');
});

it('pulses the gate once for concurrent manual gate retries', function () {
    $in = verifiedEvent($this->inCam, 'CAB-7777', $this->staff);
    $session = app(AnprWorkflowService::class)->entryConfirm($in, $this->staff, [
        'confirm_plate' => 'CAB-7777', 'vehicle_category_id' => category('car')->id, 'fuel_type' => 'petrol', 'parking_zone_id' => zone('B')->id, 'parking_package_id' => package('daily')->id,
    ])['session'];
    $this->travel(30)->minutes();
    app()->bind(GateController::class, fn () => new class implements GateController
    {
        public function open(ParkingSession $s, string $d): array
        {
            throw new RuntimeException('relay offline');
        }
    });
    $out = verifiedEvent($this->outCam, 'CAB-7777', $this->staff);
    $settled = app(AnprWorkflowService::class)->exitSettle($out, $this->staff, ['confirm_plate' => 'CAB-7777', 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'tendered_minor' => 500000]);
    expect($settled['gate']['status'])->toBe('failed');

    app()->forgetInstance(GateController::class);
    app()->bind(GateController::class, LogGateController::class);
    $sid = $session->id;
    $codes = anprRace(5, fn () => app(GateService::class)->openForExit(ParkingSession::find($sid), null));

    expect(array_count_values($codes))->toEqual([0 => 1, 22 => 4])
        ->and(GateCommand::where('status', 'success')->count())->toBe(1)
        ->and(GateCommand::where('status', 'failed')->count())->toBe(1);
});
