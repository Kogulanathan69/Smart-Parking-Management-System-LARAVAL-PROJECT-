<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
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
            'vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'parking_zone_id' => ['required', 'integer', 'exists:parking_zones,id'],
            'parking_package_id' => ['required', 'integer', 'exists:parking_packages,id'],
            'parking_slot_id' => ['nullable', 'integer', 'exists:parking_slots,id'],
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_CUSTOMER)],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 \-]{7,20}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
