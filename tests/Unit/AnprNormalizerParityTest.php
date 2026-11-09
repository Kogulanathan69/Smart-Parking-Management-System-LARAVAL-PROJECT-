<?php

use App\Support\Anpr\SriLankaPlateNormalizer;

/*
| The Python service ships a port of the normaliser (anpr-service/anpr_service/normalizer.py). Both test suites
| read the same fixture so the two implementations cannot drift apart.
*/

$cases = json_decode(file_get_contents(__DIR__.'/../../anpr-service/tests/fixtures/plate_cases.json'), true)['cases'];

it('matches the shared plate fixture used by the Python service', function (array $case) {
    $n = (new SriLankaPlateNormalizer)->normalize($case['raw']);

    expect($n['normalized'])->toBe($case['normalized'])
        ->and($n['display'])->toBe($case['display'])
        ->and($n['format'])->toBe($case['format'])
        ->and($n['province'])->toBe($case['province'])
        ->and($n['confusion_corrected'])->toBe($case['corrected']);
})->with(array_map(fn ($c) => [$c], $cases));
