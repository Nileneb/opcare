<?php

use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\Temperaturmessung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Catering\Haccp;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'HACCP UI Haus', 'slug' => 'haccp-ui-haus']);
    app(CurrentTenant::class)->set($this->tenant);

    foreach (['admin', 'kueche', 'pflegefachkraft', 'haustechnik'] as $r) {
        Role::findOrCreate($r);
    }
});

function haccpUser(int $tenantId, string $rolle = 'kueche'): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($rolle);

    return $u;
}

// ---------------------------------------------------------------------------
// Basis-Zugriff
// ---------------------------------------------------------------------------

it('zeigt HACCP-Seite für kueche-Rolle (assertOk)', function () {
    $this->actingAs(haccpUser($this->tenant->id, 'kueche'));

    Livewire::test(Haccp::class)->assertOk();
});

it('zeigt HACCP-Seite für admin-Rolle', function () {
    $this->actingAs(haccpUser($this->tenant->id, 'admin'));

    Livewire::test(Haccp::class)->assertOk();
});

it('verwehrt Zugriff für Rolle haustechnik (403)', function () {
    $this->actingAs(haccpUser($this->tenant->id, 'haustechnik'));

    Livewire::test(Haccp::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Messpunkt anlegen
// ---------------------------------------------------------------------------

it('legt Messpunkt mit Default-Grenzwert an', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    Livewire::test(Haccp::class)
        ->set('bezeichnung', 'Kühlhaus Test')
        ->set('art', HaccpArt::Kuehlung->value)
        ->set('grenzwert', '')
        ->call('messpunktSpeichern')
        ->assertHasNoErrors();

    $mp = HaccpMesspunkt::where('tenant_id', $this->tenant->id)
        ->where('bezeichnung', 'Kühlhaus Test')
        ->first();

    expect($mp)->not->toBeNull()
        ->and((float) $mp->grenzwert)->toBe(7.0)
        ->and($mp->art)->toBe(HaccpArt::Kuehlung);
});

it('legt Messpunkt mit eigenem Grenzwert an', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    Livewire::test(Haccp::class)
        ->set('bezeichnung', 'Spezial-CCP')
        ->set('art', HaccpArt::Heisshaltung->value)
        ->set('grenzwert', '70')
        ->call('messpunktSpeichern')
        ->assertHasNoErrors();

    $mp = HaccpMesspunkt::where('tenant_id', $this->tenant->id)
        ->where('bezeichnung', 'Spezial-CCP')
        ->first();

    expect($mp)->not->toBeNull()
        ->and((float) $mp->grenzwert)->toBe(70.0);
});

// ---------------------------------------------------------------------------
// Messung erfassen + Abweichungs-Anzeige
// ---------------------------------------------------------------------------

it('erfasst Messung mit Abweichung (Kühlung 9 °C) und setzt abweichung=true', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus A',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    Livewire::test(Haccp::class)
        ->set('wert', '9')
        ->set('gemessen_am', now()->subMinutes(5)->format('Y-m-d\TH:i'))
        ->call('messungErfassen', $mp->id)
        ->assertHasNoErrors();

    $messung = Temperaturmessung::where('tenant_id', $this->tenant->id)
        ->where('haccp_messpunkt_id', $mp->id)
        ->first();

    expect($messung)->not->toBeNull()
        ->and($messung->abweichung)->toBeTrue();
});

it('zeigt roten Abweichungs-Kasten im View für offene Abweichung', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus B',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subMinutes(10),
        'wert' => 9.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    Livewire::test(Haccp::class)
        ->assertSee('Grenzwert-Abweichung am CCP')
        ->assertSee('Kühlhaus B')
        ->assertSee('Korrekturmaßnahme erforderlich');
});

// ---------------------------------------------------------------------------
// Korrekturmaßnahme nachtragen
// ---------------------------------------------------------------------------

it('korrekturSetzen schließt offene Abweichung (offeneAbweichung = false)', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus C',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $messung = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subMinutes(10),
        'wert' => 9.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    Livewire::test(Haccp::class)
        ->set('korrektur_text', 'Kühlgerät gereinigt, Tür-Dichtung geprüft')
        ->call('korrekturSetzen', $messung->id)
        ->assertHasNoErrors();

    $mp->refresh();
    expect($mp->offeneAbweichung())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Validierung: Zukunftsdatum abweisen
// ---------------------------------------------------------------------------

it('verwirft Messung mit Zukunftsdatum und erstellt keine Messung', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus D',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    Livewire::test(Haccp::class)
        ->set('wert', '5')
        ->set('gemessen_am', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('messungErfassen', $mp->id)
        ->assertHasErrors(['gemessen_am']);

    expect(Temperaturmessung::where('haccp_messpunkt_id', $mp->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Tenant-Isolation: Fremd-Tenant-Messpunkt → findOrFail wirft
// ---------------------------------------------------------------------------

it('verwirft Messung auf Messpunkt eines fremden Tenants', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Haus', 'slug' => 'fremdes-haus-ui']);

    $fremderMp = HaccpMesspunkt::create([
        'tenant_id' => $fremderTenant->id,
        'bezeichnung' => 'Fremder CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $this->actingAs(haccpUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(Haccp::class)
        ->set('wert', '5')
        ->set('gemessen_am', now()->subMinutes(5)->format('Y-m-d\TH:i'))
        ->call('messungErfassen', $fremderMp->id);
});

// ---------------------------------------------------------------------------
// Gate in mount: 403 bei fehlender Rolle (haustechnik hat keinen Zugriff)
// ---------------------------------------------------------------------------

it('mount verwehrt Zugriff für haustechnik — messpunktSpeichern nicht erreichbar', function () {
    $this->actingAs(haccpUser($this->tenant->id, 'haustechnik'));

    // mount wirft bereits 403 — Component kann nicht initialisiert werden.
    Livewire::test(Haccp::class)->assertForbidden();

    // Kein Messpunkt wurde angelegt.
    expect(HaccpMesspunkt::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// B1-Regression: tag-übergreifende offene Abweichungen sichtbar (VO 852/2004 Art. 5)
// ---------------------------------------------------------------------------

it('B1: gestrige + heutige offene Abweichung beide in offeneAbweichungen() und im View sichtbar', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus B1',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $gestrige = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subDay()->setTime(10, 0),
        'wert' => 10.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    $heutige = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subHour(),
        'wert' => 9.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    // Model-Methode liefert beide Messungen
    $offene = $mp->offeneAbweichungen();
    expect($offene)->toHaveCount(2)
        ->and($offene->pluck('id')->toArray())->toContain($gestrige->id)
        ->and($offene->pluck('id')->toArray())->toContain($heutige->id);

    // View zeigt beide Korrekturformulare
    Livewire::test(Haccp::class)
        ->assertSee('korrekturSetzen('.$gestrige->id.')', false)
        ->assertSee('korrekturSetzen('.$heutige->id.')', false);
});

it('B1: nach korrekturSetzen der heutigen Messung bleibt die gestrige offen und sichtbar', function () {
    $this->actingAs(haccpUser($this->tenant->id));

    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus B1b',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $gestrige = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subDay()->setTime(10, 0),
        'wert' => 10.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    $heutige = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subHour(),
        'wert' => 9.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    // Heutige Korrektur schließen
    Livewire::test(Haccp::class)
        ->set('korrektur_text', 'Kühlgerät gereinigt')
        ->call('korrekturSetzen', $heutige->id)
        ->assertHasNoErrors();

    // Gestrige muss noch offen sein
    $mp->refresh();
    expect($mp->offeneAbweichung())->toBeTrue();
    expect($mp->offeneAbweichungen())->toHaveCount(1)
        ->and($mp->offeneAbweichungen()->first()->id)->toBe($gestrige->id);

    // View zeigt immer noch das Formular für die gestrige Messung
    Livewire::test(Haccp::class)
        ->assertSee('korrekturSetzen('.$gestrige->id.')', false);
});
