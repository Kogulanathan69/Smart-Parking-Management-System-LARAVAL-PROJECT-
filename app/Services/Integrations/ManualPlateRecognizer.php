<?php

namespace App\Services\Integrations;

use App\Contracts\PlateRecognizer;

class ManualPlateRecognizer implements PlateRecognizer
{
    public function recognize(?string $imagePath = null, ?string $manualPlate = null): array
    {
        return ['plate' => $manualPlate, 'confidence' => null, 'source' => 'manual'];
    }
}
