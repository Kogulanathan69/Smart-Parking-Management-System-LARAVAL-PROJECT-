<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $internal = (bool) $request->user()?->isInternal();

        return [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'status' => $this->status,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'zone' => $this->whenLoaded('zone', fn () => ['id' => $this->zone->id, 'code' => $this->zone->code, 'name' => $this->zone->name]),
            'slot' => $this->whenLoaded('slot', fn () => ['id' => $this->slot->id, 'code' => $this->slot->code]),
            'package' => $this->whenLoaded('package', fn () => ['id' => $this->package->id, 'code' => $this->package->code, 'name' => $this->package->name]),
            'has_safety' => $this->has_safety,
            'entry_at' => $this->entry_at?->toIso8601String(),
            'package_ends_at' => $this->package_ends_at?->toIso8601String(),
            'exit_at' => $this->exit_at?->toIso8601String(),
            'total_charge_minor' => $this->total_charge_minor,
            'total_charge' => Money::format($this->total_charge_minor),
            'charge_breakdown' => $this->charge_breakdown,
            'customer_id' => $this->when($internal, $this->customer_id),
            'customer_name' => $this->when($internal, $this->customer_name),
            'customer_phone' => $this->when($internal, $this->customer_phone),
            'entry_staff_id' => $this->when($internal, $this->entry_staff_id),
            'exit_staff_id' => $this->when($internal, $this->exit_staff_id),
            'rate_version' => $this->when($internal, fn () => $this->pricing_snapshot['rate']['version'] ?? null),
            'receipt_number' => $this->whenLoaded('receipt', fn () => $this->receipt?->number),
        ];
    }
}
