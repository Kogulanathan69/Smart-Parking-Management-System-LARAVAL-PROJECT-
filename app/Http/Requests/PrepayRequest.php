<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrepayRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'payment_method' => ['required', 'string', 'exists:payment_methods,code'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'tendered_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
