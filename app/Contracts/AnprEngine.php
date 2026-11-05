<?php

namespace App\Contracts;

/**
 * Local plate-recognition engine (the Python ANPR service). Implementations must never invent a
 * plate: when nothing is readable they return status "unreadable" with no candidates.
 */
interface AnprEngine
{
    public function name(): string;

    /**
     * @return array{status: 'recognized'|'unreadable'|'engine_error', candidates: list<array{text: string, confidence_bp: int, detector_confidence_bp?: ?int}>, plate_crop?: ?string, processing_ms: int, error_code?: ?string}
     */
    public function recognize(string $imageBytes, string $mimeType): array;

    /**
     * @return array{available: bool, latency_ms: ?int, detail?: array<string, mixed>}
     */
    public function health(): array;
}
