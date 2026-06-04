<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Services\QdvsValidator;

it('meldet fehlenden Pflegegrad als Fehler, fehlende Diagnose als Warnung', function () {
    $ok = new QdvsResidentPackage('R-1', 1940, 'w', 3, '2023-01-01', ['F00.0'], []);
    $kaputt = new QdvsResidentPackage('R-2', 1942, 'm', null, '2023-01-01', [], []);

    $issues = app(QdvsValidator::class)->validate([$ok, $kaputt]);

    $fehler = collect($issues)->where('schwere', 'fehler');
    expect($fehler->pluck('pseudonym')->all())->toContain('R-2')->not->toContain('R-1')
        ->and(collect($issues)->where('schwere', 'warnung')->pluck('feld')->all())->toContain('icd_codes');
});
