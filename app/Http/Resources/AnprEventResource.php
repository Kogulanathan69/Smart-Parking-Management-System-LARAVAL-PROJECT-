<?php

namespace App\Http\Resources;

use App\Models\AnprEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnprEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'camera' => $this->whenLoaded('camera', fn () => $this->camera ? ['code' => $this->camera->code, 'name' => $this->camera->name] : null),
            'direction' => $this->direction,
            'source' => $this->source,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'raw_text' => $this->raw_text,
            'normalized_plate' => $this->normalized_plate,
            'plate_format' => $this->plate_format,
            'review_suggestions' => $this->review_suggestions ?? [],
            'partial_review_clues' => $this->partial_review_clues ?? null,
            'candidates' => $this->candidates,
            'confidence_percent' => $this->confidence_bp !== null ? round($this->confidence_bp / 100, 2) : null,
            'recognition_status' => $this->recognition_status,
            'verification_status' => $this->verification_status,
            'verified_plate' => $this->verified_plate,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'override_reason' => $this->override_reason,
            'override_notes' => $this->override_notes,
            'vehicle_id' => $this->vehicle_id,
            'parking_session_id' => $this->parking_session_id,
            'duplicate_of' => $this->duplicate_of_id ? AnprEvent::whereKey($this->duplicate_of_id)->value('uuid') : null,
            'consumed_at' => $this->consumed_at?->toIso8601String(),
            'error_code' => $this->error_code,
            'processing_ms' => $this->processing_ms,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'uuid' => $i->uuid, 'category' => $i->category, 'retention_status' => $i->retention_status,
                'url' => $i->retention_status === 'stored' ? route('api.staff.anpr.images.show', [$this->uuid, $i->uuid]) : null,
            ])),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map->only(['action', 'from_plate', 'to_plate', 'reason', 'notes', 'user_id', 'created_at'])),
        ];
    }
}
