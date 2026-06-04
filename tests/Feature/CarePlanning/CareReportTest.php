<?php

use App\Domains\CarePlanning\Actions\CreateCareReport;
use App\Domains\CarePlanning\Actions\ReviseCareReport;
use App\Domains\CarePlanning\Data\CareReportData;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('schreibt einen Bericht und korrigiert ihn versioniert', function () {
    $resident = Resident::factory()->create();
    $report = app(CreateCareReport::class)->handle(new CareReportData(
        resident_id: $resident->id,
        created_by: 1,
        datum: '2026-03-02 08:00:00',
        schicht: 'frueh',
        text: 'Bewohnerin gut gelaunt.',
    ));
    expect($report->version)->toBe(1);

    $v2 = app(ReviseCareReport::class)->handle($report, ['text' => 'Bewohnerin gut gelaunt, hat gefrühstückt.']);
    expect($v2->version)->toBe(2)
        ->and(CareReport::current()->count())->toBe(1)
        ->and($report->fresh()->superseded_by)->toBe($v2->id);
});
