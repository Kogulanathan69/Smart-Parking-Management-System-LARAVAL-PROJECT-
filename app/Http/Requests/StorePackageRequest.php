<?php

namespace App\Http\Requests;

use App\Models\ParkingPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:parking_packages,code'],
            'name' => ['required', 'string', 'max:100'],
            'billing_mode' => ['required', Rule::in(ParkingPackage::MODES)],
            'duration_minutes' => ['nullable', 'required_if:billing_mode,fixed', 'integer', 'min:1', 'max:525600'],
            'zone_type_ids' => ['nullable', 'array'],
            'zone_type_ids.*' => ['integer', 'exists:zone_types,id'],
            'allows_early_exit_credit' => ['boolean'],
            'guarantees_slot' => ['boolean', 'declined_if:billing_mode,hourly', 'declined_if:billing_mode,addon'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
