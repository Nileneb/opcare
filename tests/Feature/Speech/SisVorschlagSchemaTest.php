<?php

use App\Domains\Speech\Data\SisVorschlagData;

it('validiert einen wohlgeformten LLM-Vorschlag', function () {
    $vorschlag = SisVorschlagData::from([
        'felder' => [
            ['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.'],
        ],
    ]);

    expect($vorschlag->felder)->toHaveCount(1)
        ->and($vorschlag->felder[0]->themenfeld)->toBe('mobilitaet');
});

it('weist einen Vorschlag mit unbekanntem Themenfeld ab', function () {
    SisVorschlagData::validateAndCreate(['felder' => [['themenfeld' => 'quatsch', 'freitext' => 'x']]]);
})->throws(\Illuminate\Validation\ValidationException::class);
