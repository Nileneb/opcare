<?php

use App\Domains\Qdvs\Engine\QdvsRuleRepository;

it('lädt alle 440 DAS-Regeln aus der CSV', function () {
    expect(app(QdvsRuleRepository::class)->all())->toHaveCount(440);
});

it('teilt Regeln nach rule_type in 383 Fehler und 57 Warnungen', function () {
    $rules = collect(app(QdvsRuleRepository::class)->all());

    expect($rules->filter(fn ($r) => $r->schwere() === 'fehler'))->toHaveCount(383)
        ->and($rules->filter(fn ($r) => $r->schwere() === 'warnung'))->toHaveCount(57);
});

it('parst die fünf Spalten korrekt', function () {
    $r = collect(app(QdvsRuleRepository::class)->all())->firstWhere('ruleId', '10001');

    expect($r)->not->toBeNull()
        ->and($r->dataset)->toBe('qs_data')
        ->and($r->ruleType)->toBe('ERROR')
        ->and($r->assertTest)->toContain('IDBEWOHNER')
        ->and($r->ruleText)->toContain('Pflichtfeld');
});

it('memoisiert das Laden (Singleton liefert dieselbe Liste)', function () {
    $repo = app(QdvsRuleRepository::class);

    expect($repo->all())->toBe($repo->all());
});
