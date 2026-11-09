<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'parking:expire-reservations {--at= : Evaluate expiry at this ISO-8601 time (testing / catch-up)}';

    protected $description = 'Expire due weekly/monthly reservations: release vacant reserved slots, never occupied ones (idempotent).';

    public function handle(ReservationService $reservations): int
    {
        $at = $this->option('at') ? CarbonImmutable::parse($this->option('at')) : now();
        $result = $reservations->expireDue($at);
        $this->info("Expired {$result['expired']} reservation(s).");

        return self::SUCCESS;
    }
}
