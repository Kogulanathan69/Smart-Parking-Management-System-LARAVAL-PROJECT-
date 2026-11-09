<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'zone_id' => $this->parking_zone_id,
            'vehicle_category_id' => $this->vehicle_category_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'decommissioned' => (bool) $this->decommissioned_at,
        ];
    }
}
