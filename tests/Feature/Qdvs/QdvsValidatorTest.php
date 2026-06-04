<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Services\QdvsValidator;

/** DAS-vollständiges Basis-Paket; einzelne Felder werden je Test überschrieben. */
function vollesPaket(array $overrides = []): QdvsResidentPackage
{
    return new QdvsResidentPackage(...array_merge([
        'pseudonym' => 'R-1',
        'geburtsjahr' => 1940,
        'geschlecht' => 'w',
        'pflegegrad' => 3,
        'aufnahme_am' => '2023-01-01',
        'icd_codes' => ['F00.0'],
        'indikatoren' => [],
        'geburtsmonat' => 5,
        'erhebungsdatum' => '2026-02-15',
    ], $overrides));
}

it('meldet fehlenden Pflegegrad als Fehler, fehlende Diagnose als Warnung', function () {
    $ok = vollesPaket();
    $kaputt = vollesPaket(['pseudonym' => 'R-2', 'pflegegrad' => null, 'icd_codes' => []]);

    $issues = app(QdvsValidator::class)->validate([$ok, $kaputt]);

    $fehler = collect($issues)->where('schwere', 'fehler');
    expect($fehler->pluck('pseudonym')->all())->toContain('R-2')->not->toContain('R-1')
        ->and(collect($issues)->where('schwere', 'warnung')->pluck('feld')->all())->toContain('icd_codes');
});

it('meldet ein zukünftiges Einzugsdatum als Fehler (DAS 50002)', function () {
    $issues = app(QdvsValidator::class)->validate([vollesPaket(['aufnahme_am' => '2099-01-01'])]);

    expect(collect($issues)->where('schwere', 'fehler')->pluck('feld')->all())->toContain('EINZUGSDATUM');
});

it('meldet ein Geburtsjahr außerhalb des Wertebereichs als Fehler (DAS 40001)', function () {
    $issues = app(QdvsValidator::class)->validate([vollesPaket(['geburtsjahr' => 1850])]);

    expect(collect($issues)->where('schwere', 'fehler')->pluck('feld')->all())->toContain('GEBURTSJAHR');
});

it('meldet Erhebungsdatum vor Einzugsdatum als Fehler (DAS 50019)', function () {
    $issues = app(QdvsValidator::class)->validate([
        vollesPaket(['erhebungsdatum' => '2022-12-01', 'aufnahme_am' => '2023-01-01']),
    ]);

    expect(collect($issues)->where('schwere', 'fehler')->pluck('feld')->all())->toContain('ERHEBUNGSDATUM');
});

it('liefert einen Coverage-Report nach der Validierung', function () {
    $validator = app(QdvsValidator::class);
    $validator->validate([vollesPaket()]);

    expect($validator->report()->total)->toBe(440)
        ->and($validator->report()->applicable)->toBeGreaterThanOrEqual(38);
});
