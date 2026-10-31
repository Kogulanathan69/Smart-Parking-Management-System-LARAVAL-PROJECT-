<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'session_id' => $this->parking_session_id,
            'method' => $this->whenLoaded('method', fn () => $this->method->code),
            'status' => $this->status,
            'currency' => $this->currency,
            'amount_minor' => $this->amount_minor,
            'amount' => Money::format($this->amount_minor),
            'tendered_minor' => $this->tendered_minor,
            'change_minor' => $this->change_minor,
            'external_reference' => $this->external_reference,
            'gateway' => $this->gateway,
            'failure_reason' => $this->failure_reason,
            'processed_by' => $this->processed_by,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
