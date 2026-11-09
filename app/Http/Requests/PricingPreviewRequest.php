<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PricingPreviewRequest extends FormRequest
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
            'parking_package_id' => ['required', 'integer', 'exists:parking_packages,id'],
            'vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'entry_at' => ['required', 'date'],
            'exit_at' => ['required', 'date', 'after_or_equal:entry_at'],
            'with_safety' => ['boolean'],
        ];
    }
}
