<?php

namespace App\Console\Commands;

use App\Models\ParkingSession;
use App\Services\SafetyService;
use Illuminate\Console\Command;

class ScheduleSafetyInspections extends Command
{
    protected $signature = 'parking:schedule-inspections';

    protected $description = 'Top up routine safety inspections for active safety sessions and mark overdue ones as missed.';

    public function handle(SafetyService $safety): int
    {
        $created = 0;
        ParkingSession::where('status', ParkingSession::ACTIVE)->where('has_safety', true)
            ->each(function (ParkingSession $session) use ($safety, &$created) {
                $created += $safety->scheduleRoutine($session);
            });
        $missed = $safety->markMissed();
        $this->info("Scheduled {$created} inspections; marked {$missed} as missed.");

        return self::SUCCESS;
    }
}
