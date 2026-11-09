<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:parking_zones,code'],
            'name' => ['required', 'string', 'max:100'],
            'zone_type_id' => ['required', 'integer', 'exists:zone_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'vehicle_category_ids' => ['required', 'array', 'min:1'],
            'vehicle_category_ids.*' => ['integer', 'exists:vehicle_categories,id'],
        ];
    }
}
