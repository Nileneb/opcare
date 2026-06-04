<?php

use App\Domains\CarePlanning\Actions\ReviseSisAssessment;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erzeugt eine neue Version und löst die alte ab', function () {
    $sis = SisAssessment::factory()->create(['eingangsfrage' => 'Alt']);

    $v2 = app(ReviseSisAssessment::class)->handle($sis, ['eingangsfrage' => 'Neu']);

    expect($v2->version)->toBe(2)
        ->and($v2->eingangsfrage)->toBe('Neu')
        ->and($v2->status)->toBe('aktiv')
        ->and($sis->fresh()->superseded_by)->toBe($v2->id)
        ->and($sis->fresh()->status)->toBe('abgelöst')
        ->and(SisAssessment::current()->count())->toBe(1);
});
