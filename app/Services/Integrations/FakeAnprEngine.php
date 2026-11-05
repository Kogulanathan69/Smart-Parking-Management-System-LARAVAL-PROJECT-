<?php

namespace App\Services\Integrations;

use App\Contracts\AnprEngine;

/**
 * Deterministic engine for automated tests. Queue results with FakeAnprEngine::queue([...]).
 * Never use in production (it is only selectable with PARKING_ANPR_ENGINE=fake).
 */
class FakeAnprEngine implements AnprEngine
{
    /** @var list<array<string, mixed>> */
    public static array $queue = [];

    public static bool $available = true;

    public static function queue(array $result): void
    {
        self::$queue[] = $result + ['candidates' => [], 'processing_ms' => 42];
    }

    public static function reset(): void
    {
        self::$queue = [];
        self::$available = true;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function recognize(string $imageBytes, string $mimeType): array
    {
        if (! self::$available) {
            return ['status' => 'engine_error', 'candidates' => [], 'processing_ms' => 0, 'error_code' => 'anpr_engine_unavailable'];
        }

        return array_shift(self::$queue) ?? ['status' => 'unreadable', 'candidates' => [], 'processing_ms' => 40];
    }

    public function health(): array
    {
        return ['available' => self::$available, 'latency_ms' => self::$available ? 5 : null];
    }
}
