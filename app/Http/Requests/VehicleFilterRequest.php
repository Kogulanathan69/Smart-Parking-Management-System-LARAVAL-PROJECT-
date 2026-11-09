<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleFilterRequest extends FormRequest
{
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
            'q' => ['nullable', 'string', 'max:30'],
            'vehicle_category_id' => ['nullable', 'integer', 'exists:vehicle_categories,id'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'owner_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
