<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AnprDeviceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Multipart clients send candidates as a JSON string.
        if (is_string($this->input('candidates'))) {
            $this->merge(['candidates' => json_decode($this->input('candidates'), true) ?? 'invalid']);
        }

        if (is_string($this->input('review_suggestions'))) {
            $this->merge([
                'review_suggestions' => json_decode($this->input('review_suggestions'), true) ?? 'invalid',
            ]);
        }
        if (is_string($this->input('partial_review_clues'))) {
            $this->merge([
                'partial_review_clues' => json_decode($this->input('partial_review_clues'), true) ?? 'invalid',
            ]);
        }
    }

    public function rules(): array
    {
        $image = File::image()->types(config('parking.anpr.image_mimes'))->max(config('parking.anpr.image_max_kilobytes'));

        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'direction' => ['nullable', 'in:entry,exit'],
            'captured_at' => ['nullable', 'date', 'before_or_equal:+5 minutes', 'after:-1 day'],
            'status' => ['required', 'in:recognized,unreadable,engine_error'],
            'candidates' => ['nullable', 'array', 'max:5'],
            'candidates.*.text' => ['required', 'string', 'max:32'],
            'candidates.*.confidence' => ['required', 'numeric', 'between:0,1'],
            // Optional plate-detector score, kept separate from the OCR confidence (display only; never used for authorisation).
            'candidates.*.detector_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'review_suggestions' => ['sometimes', 'array', 'max:3'],
            'review_suggestions.*' => ['required', 'array:text,confidence,review_required,source'],
            'review_suggestions.*.text' => ['required', 'string', 'max:32'],
            'review_suggestions.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'review_suggestions.*.review_required' => ['required', 'boolean', 'accepted'],
            'review_suggestions.*.source' => ['required', 'in:region_ocr'],
            'partial_review_clues' => ['sometimes', 'array:province_clues,registration_clues,review_required,verified'],
            'partial_review_clues.province_clues' => ['required_with:partial_review_clues', 'array', 'max:10'],
            'partial_review_clues.registration_clues' => ['required_with:partial_review_clues', 'array', 'max:10'],
            'partial_review_clues.province_clues.*' => ['required', 'array:text,confidence,angle'],
            'partial_review_clues.registration_clues.*' => ['required', 'array:text,confidence,angle'],
            'partial_review_clues.province_clues.*.text' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'partial_review_clues.registration_clues.*.text' => ['required', 'string', 'min:5', 'max:8', 'alpha_num:ascii'],
            'partial_review_clues.province_clues.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'partial_review_clues.registration_clues.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'partial_review_clues.province_clues.*.angle' => ['required', 'numeric', 'between:-180,180'],
            'partial_review_clues.registration_clues.*.angle' => ['required', 'numeric', 'between:-180,180'],
            'partial_review_clues.review_required' => ['exclude_without:partial_review_clues', 'required', 'boolean', 'accepted'],
            'partial_review_clues.verified' => ['exclude_without:partial_review_clues', 'required', 'boolean', 'declined'],
            'processing_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'error_code' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'image' => ['nullable', $image],
            'plate_crop' => ['nullable', $image],
        ];
    }
}
