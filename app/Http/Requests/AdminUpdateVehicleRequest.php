<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateVehicleRequest extends FormRequest
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
            'vehicle_category_id' => ['sometimes', 'integer', Rule::exists('vehicle_categories', 'id')->where('is_active', true)],
            'fuel_type' => ['sometimes', Rule::enum(FuelType::class)],
            'owner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_CUSTOMER)],
            'make' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
        ];
    }
}
