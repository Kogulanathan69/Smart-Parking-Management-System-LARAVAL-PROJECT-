<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCapacityRequest extends FormRequest
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
            'capacity' => ['required', 'integer', 'min:0', 'max:5000'],
        ];
    }
}
