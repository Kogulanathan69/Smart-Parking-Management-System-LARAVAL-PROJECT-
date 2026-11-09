<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tz = config('parking.business_timezone');

        return [
            'number' => $this->number,
            'session_reference' => $this->whenLoaded('session', fn () => $this->session->reference),
            'vehicle' => $this->whenLoaded('session', fn () => $this->session->vehicle?->identifier),
            'currency' => $this->currency,
            'total_minor' => $this->total_minor,
            'total' => Money::format($this->total_minor),
            'paid_minor' => $this->paid_minor,
            'paid' => Money::format($this->paid_minor),
            'credit_issued_minor' => $this->credit_issued_minor,
            'refund_minor' => $this->refund_minor,
            'breakdown' => $this->breakdown,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_at_local' => $this->issued_at?->setTimezone($tz)->format('Y-m-d H:i'),
        ];
    }
}
