<?php

namespace App\Console\Commands;

use App\Services\Anpr\AnprEventService;
use Illuminate\Console\Command;

class PurgeAnprImages extends Command
{
    protected $signature = 'parking:purge-anpr-images';

    protected $description = 'Delete ANPR snapshots older than the configured retention period (metadata kept).';

    public function handle(AnprEventService $events): int
    {
        $this->info('Purged '.$events->purgeImages().' snapshot(s).');

        return self::SUCCESS;
    }
}
