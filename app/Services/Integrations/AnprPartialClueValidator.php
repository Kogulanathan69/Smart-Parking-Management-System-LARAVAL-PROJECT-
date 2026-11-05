<?php
namespace App\Services\Integrations;

use Illuminate\Support\Facades\Validator;

final class AnprPartialClueValidator
{
    public static function normalize(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $rules = [
            'partial_review_clues' => ['required', 'array:province_clues,registration_clues,review_required,verified'],
            'partial_review_clues.province_clues' => ['required', 'array', 'max:10'],
            'partial_review_clues.registration_clues' => ['required', 'array', 'max:10'],
            'partial_review_clues.province_clues.*' => ['required', 'array:text,confidence,angle'],
            'partial_review_clues.registration_clues.*' => ['required', 'array:text,confidence,angle'],
            'partial_review_clues.province_clues.*.text' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'partial_review_clues.registration_clues.*.text' => ['required', 'string', 'min:5', 'max:8', 'alpha_num:ascii'],
            'partial_review_clues.province_clues.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'partial_review_clues.registration_clues.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'partial_review_clues.province_clues.*.angle' => ['required', 'numeric', 'between:-180,180'],
            'partial_review_clues.registration_clues.*.angle' => ['required', 'numeric', 'between:-180,180'],
            'partial_review_clues.review_required' => ['required', 'boolean', 'accepted'],
            'partial_review_clues.verified' => ['required', 'boolean', 'declined'],
        ];

        $validator = Validator::make(
            ['partial_review_clues' => $value],
            $rules
        );

        if ($validator->fails()) {
            return null;
        }

        return $validator->validated()['partial_review_clues'];
    }
}
