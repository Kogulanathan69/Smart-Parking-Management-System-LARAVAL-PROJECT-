<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->parking_package_id,
            'vehicle_category_id' => $this->vehicle_category_id,
            'version' => $this->version,
            'currency' => $this->currency,
            'price_minor' => $this->price_minor,
            'price' => Money::format($this->price_minor),
            'overtime_hourly_minor' => $this->overtime_hourly_minor,
            'overtime_daily_cap_minor' => $this->overtime_daily_cap_minor,
            'grace_minutes' => $this->grace_minutes,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
        ];
    }
}
