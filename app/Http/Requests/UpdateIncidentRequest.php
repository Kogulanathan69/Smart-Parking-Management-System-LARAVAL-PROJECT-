<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(Incident::STATUSES)],
            'severity' => ['sometimes', 'in:low,medium,high'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'compensation_minor' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}
