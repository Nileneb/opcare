<?php

use App\Domains\Facility\Enums\MpAnlage;
use App\Domains\Facility\Models\Medizinprodukt;
use App\Domains\Facility\Models\MedizinproduktEinweisung;
use App\Domains\Facility\Models\MedizinproduktVorkommnis;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Facility\Medizinprodukte;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
});

it('legt ein Medizinprodukt an und setzt das STK-Standardintervall für Anlage 1', function () {
    Livewire::test(Medizinprodukte::class)
        ->set('p_bezeichnung', 'Defibrillator')
        ->set('p_anlage', 'anlage1')
        ->call('anlegen')->assertHasNoErrors();

    $mp = Medizinprodukt::where('bezeichnung', 'Defibrillator')->firstOrFail();
    expect($mp->anlage)->toBe(MpAnlage::Anlage1);
    expect($mp->stk_intervall_monate)->toBe(24);
});

it('dokumentiert eine STK', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Anlage1, 'stk_intervall_monate' => 24]);

    Livewire::test(Medizinprodukte::class)
        ->set('selected', $mp->id)
        ->set('stk_datum', '2026-06-01')
        ->call('stkDokumentieren')->assertHasNoErrors();

    expect($mp->fresh()->letzte_stk?->toDateString())->toBe('2026-06-01');
});

it('dokumentiert eine Einweisung', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Anlage1]);
    $pers = User::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Medizinprodukte::class)
        ->set('selected', $mp->id)
        ->set('e_user', $pers->id)
        ->set('e_datum', '2026-06-01')
        ->call('einweisen')->assertHasNoErrors();

    expect(MedizinproduktEinweisung::where('medizinprodukt_id', $mp->id)->where('user_id', $pers->id)->count())->toBe(1);
});

it('erfasst ein Vorkommnis im Medizinproduktebuch', function () {
    $mp = Medizinprodukt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'AED', 'anlage' => MpAnlage::Anlage1]);

    Livewire::test(Medizinprodukte::class)
        ->set('selected', $mp->id)
        ->set('v_art', 'vorkommnis')
        ->set('v_datum', '2026-06-01')
        ->set('v_beschreibung', 'Gerät löste nicht aus.')
        ->call('vorkommnisMelden')->assertHasNoErrors();

    $v = MedizinproduktVorkommnis::where('medizinprodukt_id', $mp->id)->firstOrFail();
    expect($v->art->meldepflichtig())->toBeTrue();
    expect($v->bfarm_gemeldet)->toBeFalse();
});

it('verwehrt das Anlegen ohne Verwaltungsrolle', function () {
    $fremd = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($fremd);

    Livewire::test(Medizinprodukte::class)
        ->set('p_bezeichnung', 'Heimlich')
        ->call('anlegen')->assertForbidden();

    expect(Medizinprodukt::where('bezeichnung', 'Heimlich')->count())->toBe(0);
});
