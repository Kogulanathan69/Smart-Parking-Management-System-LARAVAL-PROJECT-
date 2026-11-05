<?php

namespace App\Http\Requests;

use App\Models\AnprCamera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnprCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'code' => $creating ? ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('anpr_cameras', 'code')] : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'direction' => [$creating ? 'required' : 'sometimes', Rule::in(AnprCamera::DIRECTIONS)],
            'gate_code' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'camera_type' => [$creating ? 'required' : 'sometimes', Rule::in(AnprCamera::TYPES)],
            'stream_url' => ['nullable', 'string', 'max:500'],
            'stream_username' => ['nullable', 'string', 'max:100'],
            'stream_password' => ['nullable', 'string', 'max:200'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
