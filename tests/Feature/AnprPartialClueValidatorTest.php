<?php

use App\Services\Integrations\AnprPartialClueValidator;

it('accepts valid unverified OCR clues', function () {
    $result = AnprPartialClueValidator::normalize([
        'province_clues' => [
            ['text' => 'SP', 'confidence' => 0.5724, 'angle' => 10],
        ],
        'registration_clues' => [
            ['text' => 'XJ2284', 'confidence' => 0.9515, 'angle' => 30],
        ],
        'review_required' => true,
        'verified' => false,
    ]);

    expect($result)->not->toBeNull()
        ->and($result['verified'])->toBeFalse();
});

it('rejects invalid or unsafe OCR clues', function () {
    $valid = [
        'province_clues' => [],
        'registration_clues' => [],
        'review_required' => true,
        'verified' => false,
    ];

    foreach ([
        array_replace($valid, ['verified' => true]),
        array_replace($valid, ['review_required' => false]),
        array_replace($valid, ['province_clues' => [
            ['text' => 'SP', 'confidence' => 1.5, 'angle' => 10],
        ]]),
        array_replace($valid, ['registration_clues' => [
            ['text' => 'INVALID!', 'confidence' => 0.8, 'angle' => 30],
        ]]),
        array_replace($valid, ['province_clues' => array_fill(
            0, 11, ['text' => 'SP', 'confidence' => 0.5, 'angle' => 10]
        )]),
        array_replace($valid, ['unexpected_field' => 'unsafe']),
    ] as $invalid) {
        expect(AnprPartialClueValidator::normalize($invalid))->toBeNull();
    }
});