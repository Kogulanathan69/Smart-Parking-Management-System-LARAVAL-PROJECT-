<?php

namespace App\Http\Requests;

use App\Models\AuditFinding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditFindingRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'severity' => ['nullable', Rule::in(AuditFinding::SEVERITIES)],
            'subject_type' => ['nullable', 'in:session,payment,user,inspection,incident'],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer'],
        ];
    }
}
