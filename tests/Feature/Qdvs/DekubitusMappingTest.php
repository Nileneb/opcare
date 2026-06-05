<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Services\AssemblePackages;
use App\Domains\Qdvs\Services\QdvsValidator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

function dekubitusResident(array $details): Resident
{
    $r = Resident::factory()->create([
        'aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w', 'geburtsdatum' => '1940-05-10',
    ]);
    CareEvent::create(['resident_id' => $r->id, 'indicator' => 'dekubitus', 'datum' => '2026-02-01', 'details' => $details]);

    return $r;
}

function packagesAtStichtag(): array
{
    return app(AssemblePackages::class)->handle(Cohort::atStichtag('2026-02-15'));
}

it('mappt strukturierten Dekubitus ins QDVS-Paket', function () {
    dekubitusResident(['stadium' => 3, 'beginn' => '2026-01-20']);

    $p = packagesAtStichtag()[0];
    expect($p->indikatoren['dekubitus'])->toBeTrue()
        ->and($p->dekubitus_stadium)->toBe(3)
        ->and($p->dekubitus_beginn)->toBe('2026-01-20');
});

it('meldet keinen Stadium-Fehler bei konsistenter Dekubitus-Erfassung', function () {
    dekubitusResident(['stadium' => 3, 'beginn' => '2026-01-20']);

    $issues = app(QdvsValidator::class)->validate(packagesAtStichtag());
    expect(collect($issues)->where('feld', 'DEKUBITUS')->where('schwere', 'fehler'))->toBeEmpty();
});

it('meldet einen Dekubitus ohne Stadium als DAS-Datenlücke (Regel 60019)', function () {
    dekubitusResident(['beginn' => '2026-01-20']); // Stadium fehlt

    $issues = app(QdvsValidator::class)->validate(packagesAtStichtag());
    expect(collect($issues)->where('schwere', 'fehler')->pluck('feld')->all())->toContain('DEKUBITUS');
});
