<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code is immutable once created (it is referenced by seeders, slots codes and history).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'code' => $creating ? ['required', 'string', 'max:30', 'alpha_dash', 'lowercase', Rule::unique('vehicle_categories', 'code')] : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'requires_plate' => ['sometimes', 'boolean'],
            'is_motorized' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
