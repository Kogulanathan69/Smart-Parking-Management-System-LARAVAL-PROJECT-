<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
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
            'parking_session_uuid' => ['nullable', 'uuid', 'exists:parking_sessions,uuid'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'type' => ['required', Rule::in(Incident::TYPES)],
            'severity' => ['nullable', 'in:low,medium,high'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
