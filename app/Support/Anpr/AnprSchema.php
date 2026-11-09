<?php

namespace App\Support\Anpr;

use Illuminate\Support\Facades\Schema;

/**
 * ANPR is an optional installation module. These checks keep read-only screens useful when
 * the hardware schema has not been installed, without disguising a configured integration error.
 */
final class AnprSchema
{
    public static function cameras(): bool
    {
        return Schema::hasTable('anpr_cameras');
    }

    public static function events(): bool
    {
        return self::cameras() && Schema::hasTable('anpr_events');
    }

    public static function reviews(): bool
    {
        return self::events() && Schema::hasTable('anpr_review_actions');
    }

    public static function images(): bool
    {
        return self::events() && Schema::hasTable('anpr_event_images');
    }

    public static function healthLogs(): bool
    {
        return self::cameras() && Schema::hasTable('anpr_camera_health_logs');
    }

    public static function configured(): bool
    {
        return self::events() && self::images() && self::reviews();
    }

    public static function gateCommands(): bool
    {
        return Schema::hasTable('gate_commands');
    }

    public static function unavailable(string $operation = 'ANPR'): array
    {
        return [
            'message' => "$operation is not configured for this installation.",
            'error_code' => 'anpr_not_configured',
            'context' => ['configuration_state' => 'not_configured'],
        ];
    }
}
