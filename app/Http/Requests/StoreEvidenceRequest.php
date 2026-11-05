<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreEvidenceRequest extends FormRequest
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
            'attachable_type' => ['required', 'in:inspection,incident'],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', File::image()->types(config('parking.evidence.mimes'))->max(config('parking.evidence.max_kilobytes'))],
        ];
    }
}
