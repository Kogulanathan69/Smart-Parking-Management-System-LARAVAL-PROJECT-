<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null),
            'actor_role' => $this->actor_role,
            'subject_type' => $this->auditable_type ? class_basename($this->auditable_type) : null,
            'subject_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'meta' => $this->meta,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
