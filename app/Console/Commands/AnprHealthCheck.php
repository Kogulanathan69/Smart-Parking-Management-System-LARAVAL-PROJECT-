<?php

namespace App\Console\Commands;

use App\Contracts\AnprEngine;
use App\Models\AnprCamera;
use App\Models\AnprCameraHealthLog;
use App\Services\Anpr\AnprCameraService;
use App\Services\SettingsService;
use App\Support\Anpr\AnprSchema;
use Illuminate\Console\Command;

class AnprHealthCheck extends Command
{
    protected $signature = 'parking:anpr-health-check';

    protected $description = 'Mark silent cameras offline and record ANPR engine availability changes (idempotent).';

    public function handle(AnprCameraService $cameras, SettingsService $settings, AnprEngine $engine): int
    {
        $offlineAfter = (int) $settings->get('anpr.camera_offline_after_seconds');
        $marked = 0;
        if (AnprSchema::cameras()) {
            AnprCamera::where('is_enabled', true)->each(function (AnprCamera $camera) use ($cameras, $offlineAfter, &$marked) {
                $marked += $cameras->markOfflineIfStale($camera, $offlineAfter) ? 1 : 0;
            });
        }

        $health = $engine->health();
        $last = AnprSchema::healthLogs() ? AnprCameraHealthLog::where('component', 'engine')->latest('id')->value('status') : null;
        $now = $health['available'] ? 'online' : 'offline';
        if ($last !== $now && ! ($last === 'recovered' && $now === 'online')) {
            $cameras->logHealth(null, 'engine', $now === 'online' && $last ? 'recovered' : $now, $engine->name(), $health['latency_ms']);
        }

        $this->info("Cameras marked offline: {$marked}. Engine ({$engine->name()}): ".($health['available'] ? 'available' : 'unavailable').'.');

        return self::SUCCESS;
    }
}
