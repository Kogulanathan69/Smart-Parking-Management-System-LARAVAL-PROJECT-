<?php

namespace App\Http\Resources;

use App\Models\ParkingSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $internal = (bool) $request->user()?->isInternal();
        $tz = config('parking.business_timezone');
        $now = now();
        $open = $this->relationLoaded('sessions')
            ? $this->sessions->first(fn ($s) => in_array($s->status, [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED], true))
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'is_valid' => $this->isActiveAt($now),
            'vehicle' => $this->whenLoaded('vehicle', fn () => ['id' => $this->vehicle->id, 'identifier' => $this->vehicle->identifier]),
            'customer_id' => $this->when($internal, $this->customer_id),
            'zone' => $this->whenLoaded('zone', fn () => ['id' => $this->zone->id, 'code' => $this->zone->code]),
            'slot' => $this->whenLoaded('slot', fn () => ['id' => $this->slot->id, 'code' => $this->slot->code, 'status' => $this->slot->status]),
            'package' => $this->whenLoaded('package', fn () => ['id' => $this->package->id, 'code' => $this->package->code, 'name' => $this->package->name]),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expires_at_local' => $this->expires_at?->setTimezone($tz)->format('Y-m-d H:i'),
            'remaining_minutes' => $this->status === 'active' && $this->expires_at->greaterThan($now) ? (int) ceil(($this->expires_at->getTimestamp() - $now->getTimestamp()) / 60) : 0,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'vehicle_on_site' => $this->relationLoaded('sessions') ? (bool) $open : null,
            'current_session_uuid' => $open?->uuid,
            'periods' => $this->whenLoaded('periods', fn () => $this->periods->map(fn ($p) => [
                'sequence' => $p->sequence,
                'kind' => $p->kind,
                'starts_at' => $p->starts_at->toIso8601String(),
                'ends_at' => $p->ends_at->toIso8601String(),
                'price_minor' => $p->price_minor,
                'price' => Money::format($p->price_minor),
                'status' => $p->status,
                'paid' => $p->isPaid(),
                'billed_session_id' => $this->when($internal, $p->billed_session_id),
            ])),
            'notes' => $this->when($internal, $this->notes),
        ];
    }
}
