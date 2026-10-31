<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'plate_number' => $this->plate_number,
            'is_reference' => $this->is_reference,
            'vehicle_category' => $this->whenLoaded('category', fn () => $this->category->code),
            'vehicle_category_id' => $this->vehicle_category_id,
            'make' => $this->make,
            'model' => $this->model,
            'color' => $this->color,
            'owner_id' => $this->when($request->user()?->isInternal(), $this->owner_id),
            'has_active_session' => $this->whenLoaded('openSession', fn () => (bool) $this->openSession),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
