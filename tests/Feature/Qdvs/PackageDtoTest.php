<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;

it('hält ein pseudonymisiertes Bewohner-Datenpaket', function () {
    $p = new QdvsResidentPackage(
        pseudonym: 'R-000123', geburtsjahr: 1940, geschlecht: 'w', pflegegrad: 3,
        aufnahme_am: '2023-03-15', icd_codes: ['F00.0', 'I10'],
        indikatoren: ['sturz' => true, 'dekubitus' => false],
    );

    expect($p->pseudonym)->toBe('R-000123')
        ->and($p->indikatoren['sturz'])->toBeTrue()
        ->and($p->icd_codes)->toContain('I10');
});
