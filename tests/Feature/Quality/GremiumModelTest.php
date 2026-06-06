<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Domains\Quality\Models\Gremium;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

function neuesGremium(array $attr = []): Gremium
{
    return Gremium::create(array_merge([
        'tenant_id' => app(CurrentTenant::class)->id(),
        'typ' => GremiumTyp::Heimbeirat, 'name' => 'Heimbeirat',
    ], $attr));
}

it('meldet Neuwahl-Bedarf, wenn die Wahlperiode abgelaufen ist', function () {
    $g = neuesGremium(['gewaehlt_am' => today()->subMonths(30)->toDateString(), 'periode_monate' => 24]);
    expect($g->status())->toBe('neuwahl_faellig');
    expect($g->ampel())->toBe('red');
});

it('meldet Sitzungsbedarf, wenn der Sitzungstakt überschritten ist', function () {
    $g = neuesGremium([
        'typ' => GremiumTyp::Arbeitsschutzausschuss, 'name' => 'ASA',
        'gewaehlt_am' => today()->subMonths(2)->toDateString(), 'periode_monate' => null,
        'sitzung_intervall_monate' => 3,
    ]);
    $g->sitzungen()->create(['tenant_id' => $g->tenant_id, 'datum' => today()->subMonths(4)->toDateString(), 'thema' => 'alt']);
    expect($g->status())->toBe('sitzung_faellig');
    expect($g->ampel())->toBe('amber');
});

it('ist grün, solange Wahlperiode und Sitzungstakt gewahrt sind', function () {
    $g = neuesGremium(['gewaehlt_am' => today()->subMonths(2)->toDateString(), 'periode_monate' => 24, 'sitzung_intervall_monate' => 3]);
    $g->sitzungen()->create(['tenant_id' => $g->tenant_id, 'datum' => today()->subDays(10)->toDateString(), 'thema' => 'neu']);
    expect($g->status())->toBe('aktiv');
    expect($g->ampel())->toBe('green');
});

it('ist grau nach Auflösung', function () {
    $g = neuesGremium(['aufgeloest_am' => today()->toDateString()]);
    expect($g->aktiv())->toBeFalse();
    expect($g->ampel())->toBe('gray');
});
