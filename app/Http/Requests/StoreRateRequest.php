<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRateRequest extends FormRequest
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
            'vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'overtime_hourly_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'overtime_daily_cap_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'effective_from' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }
}
