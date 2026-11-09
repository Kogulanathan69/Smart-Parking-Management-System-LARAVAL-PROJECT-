<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'session_id' => $this->parking_session_id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'severity' => $this->severity,
            'description' => $this->description,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'compensation_minor' => $this->compensation_minor,
            'compensation' => Money::format($this->compensation_minor),
            'reported_by' => $this->reported_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
        ];
    }
}
