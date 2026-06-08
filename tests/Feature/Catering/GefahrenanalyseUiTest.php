<?php

use App\Domains\Catering\Enums\GefahrenanalyseStatus;
use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Catering\Models\Gefahrenanalyse;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\LebensmittelGefahr;
use App\Domains\Catering\Models\Lenkungsmassnahme;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Catering\Gefahrenanalyse as GefahrenanalyseScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Gefahr UI Haus', 'slug' => 'gefahr-ui-haus']);
    app(CurrentTenant::class)->set($this->tenant);

    foreach (['admin', 'kueche', 'pflegefachkraft', 'haustechnik'] as $r) {
        Role::findOrCreate($r);
    }
});

function gefahrUser(int $tenantId, string $rolle = 'kueche'): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($rolle);

    return $u;
}

// ---------------------------------------------------------------------------
// Zugriff
// ---------------------------------------------------------------------------

it('zeigt Gefahrenanalyse für kueche/admin/pflegefachkraft', function (string $rolle) {
    $this->actingAs(gefahrUser($this->tenant->id, $rolle));

    Livewire::test(GefahrenanalyseScreen::class)->assertOk();
})->with(['kueche', 'admin', 'pflegefachkraft']);

it('verwehrt Zugriff für haustechnik (403)', function () {
    $this->actingAs(gefahrUser($this->tenant->id, 'haustechnik'));

    Livewire::test(GefahrenanalyseScreen::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// CRUD
// ---------------------------------------------------------------------------

it('legt eine Gefahrenanalyse an', function () {
    $this->actingAs(gefahrUser($this->tenant->id));

    Livewire::test(GefahrenanalyseScreen::class)
        ->set('prozessschritt', 'Garen')
        ->set('bereich', 'Küche')
        ->set('verifizierungsintervall_monate', 12)
        ->call('analyseAnlegen')
        ->assertHasNoErrors();

    expect(Gefahrenanalyse::where('prozessschritt', 'Garen')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

it('fügt eine Gefahr mit CCP-Messpunkt-Verknüpfung hinzu', function () {
    $this->actingAs(gefahrUser($this->tenant->id));
    $a = Gefahrenanalyse::create(['tenant_id' => $this->tenant->id, 'prozessschritt' => 'Kühlen', 'erstellt_am' => today(), 'verifizierungsintervall_monate' => 12, 'status' => GefahrenanalyseStatus::Entwurf]);
    $mp = HaccpMesspunkt::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Kühlhaus', 'art' => HaccpArt::Kuehlung, 'grenzwert' => 7.0, 'aktiv' => true]);

    Livewire::test(GefahrenanalyseScreen::class)
        ->set('gefahr_art', 'biologisch')
        ->set('gefahr_beschreibung', 'Salmonellen')
        ->set('gefahr_wahrscheinlichkeit', 3)
        ->set('gefahr_schwere', 3)
        ->set('gefahr_ist_ccp', true)
        ->set('gefahr_messpunkt_id', $mp->id)
        ->call('gefahrHinzufuegen', $a->id)
        ->assertHasNoErrors();

    $g = LebensmittelGefahr::where('gefahrenanalyse_id', $a->id)->first();
    expect($g)->not->toBeNull()
        ->and($g->ist_ccp)->toBeTrue()
        ->and($g->haccp_messpunkt_id)->toBe($mp->id)
        ->and($g->istCcpOhneUeberwachung())->toBeFalse();
});

it('fügt eine Lenkungsmaßnahme hinzu, markiert umgesetzt und verifiziert', function () {
    $this->actingAs(gefahrUser($this->tenant->id));
    $a = Gefahrenanalyse::create(['tenant_id' => $this->tenant->id, 'prozessschritt' => 'Lagerung', 'erstellt_am' => today(), 'verifizierungsintervall_monate' => 12, 'status' => GefahrenanalyseStatus::Entwurf]);
    $g = LebensmittelGefahr::create(['tenant_id' => $this->tenant->id, 'gefahrenanalyse_id' => $a->id, 'gefahrenart' => 'biologisch', 'beschreibung' => 'X', 'wahrscheinlichkeit' => 2, 'schwere' => 2]);

    $comp = Livewire::test(GefahrenanalyseScreen::class)
        ->set('lenkung_art', 'ccp')
        ->set('lenkung_beschreibung', 'Kühlkette ≤ 7 °C')
        ->call('lenkungHinzufuegen', $g->id)
        ->assertHasNoErrors();

    $l = Lenkungsmassnahme::where('lebensmittel_gefahr_id', $g->id)->first();
    expect($l)->not->toBeNull()->and($l->istOffen())->toBeTrue();

    $comp->set('umgesetzt_am', today()->toDateString())->call('lenkungUmgesetzt', $l->id)->assertHasNoErrors();
    $comp->set('verifiziert_am', today()->toDateString())->call('lenkungVerifizieren', $l->id)->assertHasNoErrors();

    $l->refresh();
    expect($l->istOffen())->toBeFalse()->and($l->istVerifiziert())->toBeTrue();
});

it('gibt frei und verifiziert die Analyse', function () {
    $this->actingAs(gefahrUser($this->tenant->id));
    $a = Gefahrenanalyse::create(['tenant_id' => $this->tenant->id, 'prozessschritt' => 'Ausgabe', 'erstellt_am' => today(), 'verifizierungsintervall_monate' => 12, 'status' => GefahrenanalyseStatus::Entwurf]);

    Livewire::test(GefahrenanalyseScreen::class)
        ->call('analyseFreigeben', $a->id)
        ->assertHasNoErrors()
        ->set('verifizierung_datum', today()->toDateString())
        ->call('analyseVerifizieren', $a->id)
        ->assertHasNoErrors();

    $a->refresh();
    expect($a->status)->toBe(GefahrenanalyseStatus::Freigegeben)
        ->and($a->letzte_verifizierung_am->toDateString())->toBe(today()->toDateString());
});

// ---------------------------------------------------------------------------
// IDOR
// ---------------------------------------------------------------------------

it('verwehrt das Hinzufügen einer Gefahr zu einer fremden Analyse (IDOR)', function () {
    $fremd = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd-gefahr-ui']);
    app(CurrentTenant::class)->set($fremd);
    $fremdeAnalyse = Gefahrenanalyse::create(['tenant_id' => $fremd->id, 'prozessschritt' => 'Fremd', 'erstellt_am' => today(), 'verifizierungsintervall_monate' => 12, 'status' => GefahrenanalyseStatus::Entwurf]);
    app(CurrentTenant::class)->set($this->tenant);

    $this->actingAs(gefahrUser($this->tenant->id));

    expect(fn () => Livewire::test(GefahrenanalyseScreen::class)
        ->set('gefahr_art', 'biologisch')
        ->set('gefahr_beschreibung', 'X')
        ->call('gefahrHinzufuegen', $fremdeAnalyse->id))
        ->toThrow(ModelNotFoundException::class);
});
