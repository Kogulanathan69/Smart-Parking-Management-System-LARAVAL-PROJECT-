<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_uuid' => $this->whenLoaded('session', fn () => $this->session->uuid),
            'session_reference' => $this->whenLoaded('session', fn () => $this->session->reference),
            'type' => $this->type,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'performed_at' => $this->performed_at?->toIso8601String(),
            'inspected_by' => $this->inspected_by,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'new_damage_found' => $this->new_damage_found,
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
        ];
    }
}
