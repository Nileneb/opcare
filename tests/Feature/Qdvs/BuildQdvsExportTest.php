<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Actions\BuildQdvsExport;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260123456']));
});

it('erstellt eine valide Export-Datei und protokolliert sie', function () {
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Hypertonie']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);

    $export = app(BuildQdvsExport::class)->handle(stichtag: '2026-02-15', specKey: 'csv-v1');

    expect($export->status)->toBe('exportiert')
        ->and($export->bewohner_count)->toBe(1)
        ->and($export->pfad)->not->toBeNull();
    Storage::disk('local')->assertExists($export->pfad);
});

it('blockt den Export bei Datenfehlern und protokolliert sie', function () {
    Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => null]);

    $export = app(BuildQdvsExport::class)->handle(stichtag: '2026-02-15', specKey: 'csv-v1');

    expect($export->status)->toBe('fehler')
        ->and($export->pfad)->toBeNull()
        ->and(count($export->fehler))->toBeGreaterThan(0);
});
