<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'billing_mode' => $this->billing_mode,
            'duration_minutes' => $this->duration_minutes,
            'zone_types' => $this->whenLoaded('zoneTypes', fn () => $this->zoneTypes->pluck('code')),
            'allows_early_exit_credit' => $this->allows_early_exit_credit,
            'guarantees_slot' => $this->guaranteesSlot(),
            'is_active' => $this->is_active,
            'description' => $this->description,
            'current_rates' => RateResource::collection($this->whenLoaded('rates')),
        ];
    }
}
