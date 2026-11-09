<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'zone_type' => $this->whenLoaded('zoneType', fn () => ['id' => $this->zoneType->id, 'code' => $this->zoneType->code, 'name' => $this->zoneType->name, 'is_safety' => $this->zoneType->is_safety]),
            'description' => $this->description,
            'is_active' => $this->is_active,
            'vehicle_categories' => $this->whenLoaded('vehicleCategories', fn () => $this->vehicleCategories->pluck('code')),
            'occupancy' => $this->when(isset($this->occupancy), fn () => array_values($this->occupancy)),
        ];
    }
}
