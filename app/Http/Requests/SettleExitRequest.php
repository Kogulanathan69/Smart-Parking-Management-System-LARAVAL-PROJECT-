<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettleExitRequest extends FormRequest
{
    /**
     * Coarse access is enforced by route middleware; record-level access by policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_payload' => ['required', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'payment_method' => ['nullable', 'string', 'exists:payment_methods,code'],
            'tendered_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'overpayment_resolution' => ['nullable', 'in:credit,refund'],
        ];
    }
}
