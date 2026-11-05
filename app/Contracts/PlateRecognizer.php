<?php

namespace App\Contracts;

/**
 * Future camera/OCR integration (Python AI service). Staff always confirm or correct the result.
 */
interface PlateRecognizer
{
    /**
     * @return array{plate: ?string, confidence: ?float, source: string}
     */
    public function recognize(?string $imagePath = null, ?string $manualPlate = null): array;
}
