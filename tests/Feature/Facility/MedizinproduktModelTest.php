<?php

use App\Domains\Facility\Enums\MpAnlage;
use App\Domains\Facility\Models\Medizinprodukt;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('meldet STK überfällig als rote Ampel (Anlage 1)', function () {
    $mp = Medizinprodukt::create([
        'tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Anlage1,
        'stk_intervall_monate' => 24, 'letzte_stk' => now()->subMonths(26)->toDateString(),
    ]);

    expect($mp->naechsteStk()?->isPast())->toBeTrue();
    expect($mp->pruefAmpel())->toBe('red');
    expect($mp->pruefungUeberfaellig())->toBeTrue();
});

it('ist grau ohne Anlagen-Pflicht und führt kein Medizinproduktebuch', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Pflegebett', 'anlage' => MpAnlage::Keine]);

    expect($mp->anlage->brauchtMedizinproduktebuch())->toBeFalse();
    expect($mp->pruefAmpel())->toBe('grau');
    expect($mp->naechsteStk())->toBeNull();
});

it('berechnet keine STK für Anlage 2 (nur MTK)', function () {
    $mp = Medizinprodukt::create([
        'tenant_id' => $this->tenant->id, 'bezeichnung' => 'Blutzuckermessgerät', 'anlage' => MpAnlage::Anlage2,
        'mtk_intervall_monate' => 24, 'letzte_mtk' => now()->subMonths(2)->toDateString(),
    ]);

    expect($mp->naechsteStk())->toBeNull();
    expect($mp->naechsteMtk())->not->toBeNull();
    expect($mp->pruefAmpel())->toBe('green');
});

it('zeigt amber, wenn pflichtige Kontrolle nie dokumentiert wurde', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Anlage1, 'stk_intervall_monate' => 24]);

    expect($mp->pruefAmpel())->toBe('amber');
});

it('gilt nach Außerbetriebnahme als inaktiv', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Keine]);
    expect($mp->aktiv())->toBeTrue();

    $mp->update(['ausser_betrieb_am' => today()->toDateString()]);
    expect($mp->fresh()->aktiv())->toBeFalse();
});
