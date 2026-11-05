<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftAssignmentRequest extends FormRequest
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
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_date' => ['required', 'date_format:Y-m-d'],
            'is_gate_duty' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
