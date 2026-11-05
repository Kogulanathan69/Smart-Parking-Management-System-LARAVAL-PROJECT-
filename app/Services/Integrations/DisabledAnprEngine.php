<?php

namespace App\Services\Integrations;

use App\Contracts\AnprEngine;

/**
 * Default when no local ANPR service is configured: every request fails safely so staff use manual identification.
 */
class DisabledAnprEngine implements AnprEngine
{
    public function name(): string
    {
        return 'disabled';
    }

    public function recognize(string $imageBytes, string $mimeType): array
    {
        return ['status' => 'engine_error', 'candidates' => [], 'plate_crop' => null, 'processing_ms' => 0, 'error_code' => 'anpr_engine_disabled'];
    }

    public function health(): array
    {
        return ['available' => false, 'latency_ms' => null, 'detail' => ['reason' => 'disabled']];
    }
}
