<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
            'plate_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'vehicle_category_id' => ['required', 'integer', Rule::exists('vehicle_categories', 'id')->where('is_active', true)],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'make' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
        ];
    }
}
