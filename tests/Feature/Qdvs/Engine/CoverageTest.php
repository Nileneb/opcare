<?php

use App\Domains\Qdvs\Engine\AssertCompiler;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\QdvsRuleRepository;

beforeEach(function () {
    $this->report = app(AssertCompiler::class)
        ->compileAll(app(QdvsRuleRepository::class)->all())['report'];
});

it('klassifiziert alle 440 Regeln ohne stilles Überspringen', function () {
    $skippedTotal = array_sum($this->report->skipped);

    expect($this->report->total)->toBe(440)
        ->and($this->report->applicable + $skippedTotal)->toBe(440);
});

it('gibt jeder geskippten Regel einen dokumentierten Grund', function () {
    foreach (array_keys($this->report->skippedRuleIds) as $reason) {
        expect(SkipReason::tryFrom($reason))->not->toBeNull();
    }
});

it('schaltet mindestens 57 DAS-Regeln scharf (Feld-Ausbau-Hebel)', function () {
    // Regression-Guard: Feld-Ausbau hob die aktiv geprüften Regeln 4 → 38 → 52 (Dekubitus) → 57 (Sturz)
    expect($this->report->applicable)->toBeGreaterThanOrEqual(57);
});

it('klassifiziert die 13 datensatzübergreifenden Regeln als Aggregat', function () {
    expect($this->report->skipped[SkipReason::OutOfScopeAggregate->value] ?? 0)->toBe(13);
});

it('begrenzt nicht erkannte Muster (Guard gegen stille CSV-Drift)', function () {
    // Fixe Erwartung: ändert die CSV ihre Regel-Formen, muss dieser Wert bewusst nachgezogen werden
    expect($this->report->skipped[SkipReason::UnknownPattern->value] ?? 0)->toBe(119)
        ->and($this->report->skipped[SkipReason::UnmappedField->value] ?? 0)->toBe(251);
});
