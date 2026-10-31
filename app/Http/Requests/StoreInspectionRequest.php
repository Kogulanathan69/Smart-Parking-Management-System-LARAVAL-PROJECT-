<?php

namespace App\Http\Requests;

use App\Models\SafetyInspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInspectionRequest extends FormRequest
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
            'type' => ['required', Rule::in([SafetyInspection::TYPE_ENTRY, SafetyInspection::TYPE_ROUTINE, SafetyInspection::TYPE_EXIT])],
            'condition' => ['required', Rule::in(SafetyInspection::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'new_damage_found' => ['boolean'],
            'safety_inspection_id' => ['nullable', 'integer'],
        ];
    }
}
