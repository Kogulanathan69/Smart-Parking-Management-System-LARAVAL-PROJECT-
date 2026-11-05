<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnprSettleExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid', 'exists:anpr_events,uuid'],
            'confirm_plate' => ['nullable', 'string', 'max:30'],
            'parking_session_uuid' => ['nullable', 'uuid'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'payment_method' => ['nullable', 'string', 'exists:payment_methods,code'],
            'tendered_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'overpayment_resolution' => ['nullable', 'in:credit,refund'],
        ];
    }
}
