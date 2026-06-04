<?php

use App\Domains\CarePlanning\Actions\CreateEvaluation;
use App\Domains\CarePlanning\Data\EvaluationData;
use App\Domains\CarePlanning\Enums\ZielErreichung;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\Evaluation;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('bewertet die Zielerreichung einer Maßnahme polymorph', function () {
    $resident = Resident::factory()->create();
    $measure = CareMeasure::create([
        'resident_id' => $resident->id, 'themenfeld' => 'mobilitaet', 'beschreibung' => 'Gehen',
    ]);

    $eval = app(CreateEvaluation::class)->handle(new EvaluationData(
        evaluable_type: CareMeasure::class,
        evaluable_id: $measure->id,
        created_by: 1,
        datum: '2026-04-01',
        zielerreichung: 'teilweise',
        anlass: 'Quartalsevaluation',
    ));

    expect($eval->zielerreichung)->toBe(ZielErreichung::Teilweise)
        ->and($eval->evaluable->is($measure))->toBeTrue()
        ->and($measure->evaluations)->toHaveCount(1);
});
