<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnprConfirmEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid', 'exists:anpr_events,uuid'],
            'confirm_plate' => ['nullable', 'string', 'max:30'],
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
