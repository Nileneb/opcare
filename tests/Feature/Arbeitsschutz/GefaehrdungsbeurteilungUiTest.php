<?php

use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Arbeitsschutz\Enums\Massnahmentyp;
use App\Domains\Arbeitsschutz\Models\Gefaehrdung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Arbeitsschutz\Models\Schutzmassnahme;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Arbeitsschutz\Gefaehrdungsbeurteilung as GbuScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'GBU UI Heim', 'slug' => 'gbu-ui-heim']);
    app(CurrentTenant::class)->set($this->tenant);

    foreach (['admin', 'pflegefachkraft', 'haustechnik', 'kueche'] as $r) {
        Role::findOrCreate($r);
    }
});

function gbuUser(int $tenantId, string $rolle = 'admin'): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($rolle);

    return $u;
}

// ---------------------------------------------------------------------------
// Zugriffs-Gate
// ---------------------------------------------------------------------------

it('zeigt GBU-Screen für admin-Rolle', function () {
    $this->actingAs(gbuUser($this->tenant->id, 'admin'));

    Livewire::test(GbuScreen::class)->assertOk();
});

it('zeigt GBU-Screen für pflegefachkraft-Rolle', function () {
    $this->actingAs(gbuUser($this->tenant->id, 'pflegefachkraft'));

    Livewire::test(GbuScreen::class)->assertOk();
});

it('verwehrt Zugriff für haustechnik-Rolle (403)', function () {
    $this->actingAs(gbuUser($this->tenant->id, 'haustechnik'));

    Livewire::test(GbuScreen::class)->assertForbidden();
});

it('verwehrt Zugriff für kueche-Rolle (403)', function () {
    $this->actingAs(gbuUser($this->tenant->id, 'kueche'));

    Livewire::test(GbuScreen::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// GBU anlegen
// ---------------------------------------------------------------------------

it('legt GBU an und zeigt sie in der Liste', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    Livewire::test(GbuScreen::class)
        ->set('arbeitsbereich', 'Pflege WB 1')
        ->set('taetigkeit', 'Heben und Umlagern')
        ->set('ueberpruefungsintervall_monate', 12)
        ->set('verantwortlich', 'SiFa Müller')
        ->call('gbuAnlegen')
        ->assertHasNoErrors();

    $gbu = Gefaehrdungsbeurteilung::where('tenant_id', $this->tenant->id)
        ->where('arbeitsbereich', 'Pflege WB 1')
        ->first();

    expect($gbu)->not->toBeNull()
        ->and($gbu->status)->toBe(GbuStatus::Entwurf)
        ->and($gbu->taetigkeit)->toBe('Heben und Umlagern')
        ->and($gbu->verantwortlich)->toBe('SiFa Müller')
        ->and($gbu->ueberpruefungsintervall_monate)->toBe(12);
});

it('gbuAnlegen schlägt fehl ohne Arbeitsbereich', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    Livewire::test(GbuScreen::class)
        ->set('arbeitsbereich', '')
        ->call('gbuAnlegen')
        ->assertHasErrors(['arbeitsbereich']);
});

it('gbuAnlegen schlägt fehl mit ungültigem Intervall (0)', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    Livewire::test(GbuScreen::class)
        ->set('arbeitsbereich', 'Küche')
        ->set('ueberpruefungsintervall_monate', 0)
        ->call('gbuAnlegen')
        ->assertHasErrors(['ueberpruefungsintervall_monate']);
});

// ---------------------------------------------------------------------------
// Gefährdung hinzufügen
// ---------------------------------------------------------------------------

it('fügt Gefährdung zu GBU hinzu', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    Livewire::test(GbuScreen::class)
        ->set('gefaehrdung_faktor', Gefaehrdungsfaktor::Einwirkungen->value)
        ->set('gefaehrdung_beschreibung', 'Biologische Belastung durch Körperkontakt')
        ->set('gefaehrdung_wahrscheinlichkeit', 2)
        ->set('gefaehrdung_schwere', 3)
        ->call('gefaehrdungHinzufuegen', $gbu->id)
        ->assertHasNoErrors();

    $gefaehrdung = Gefaehrdung::where('gefaehrdungsbeurteilung_id', $gbu->id)->first();

    expect($gefaehrdung)->not->toBeNull()
        ->and($gefaehrdung->faktor)->toBe(Gefaehrdungsfaktor::Einwirkungen)
        ->and($gefaehrdung->risikowert())->toBe(6)
        ->and($gefaehrdung->risikostufe())->toBe('hoch');
});

it('gefaehrdungHinzufuegen schlägt fehl ohne Beschreibung', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    Livewire::test(GbuScreen::class)
        ->set('gefaehrdung_faktor', Gefaehrdungsfaktor::Einwirkungen->value)
        ->set('gefaehrdung_beschreibung', '')
        ->call('gefaehrdungHinzufuegen', $gbu->id)
        ->assertHasErrors(['gefaehrdung_beschreibung']);
});

// ---------------------------------------------------------------------------
// Maßnahme hinzufügen
// ---------------------------------------------------------------------------

it('fügt Maßnahme zu Gefährdung hinzu', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Einwirkungen,
        'beschreibung' => 'Test',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ]);

    Livewire::test(GbuScreen::class)
        ->set('massnahme_typ', Massnahmentyp::Technisch->value)
        ->set('massnahme_beschreibung', 'Schutzhandschuhe bereitstellen')
        ->set('massnahme_verantwortlich', 'Pflegeleitung')
        ->call('massnahmeHinzufuegen', $gefaehrdung->id)
        ->assertHasNoErrors();

    $massnahme = Schutzmassnahme::where('gefaehrdung_id', $gefaehrdung->id)->first();

    expect($massnahme)->not->toBeNull()
        ->and($massnahme->typ)->toBe(Massnahmentyp::Technisch)
        ->and($massnahme->istOffen())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Maßnahme umgesetzt → offene-Badge sinkt
// ---------------------------------------------------------------------------

it('massnahmeUmgesetzt: hatOffeneMassnahmen wird false, offene-Badge sinkt', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Küche',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Arbeitsmittel,
        'beschreibung' => 'Messer',
        'wahrscheinlichkeit' => 1,
        'schwere' => 2,
    ]);

    $massnahme = Schutzmassnahme::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Schutzhandschuhe',
        'umgesetzt_am' => null,
    ]);

    $gbu->load('gefaehrdungen.massnahmen');
    expect($gbu->hatOffeneMassnahmen())->toBeTrue();

    Livewire::test(GbuScreen::class)
        ->set('umgesetzt_am', today()->toDateString())
        ->call('massnahmeUmgesetzt', $massnahme->id)
        ->assertHasNoErrors();

    $massnahme->refresh();
    expect($massnahme->istOffen())->toBeFalse()
        ->and($massnahme->umgesetzt_am->toDateString())->toBe(today()->toDateString());

    $gbu->load('gefaehrdungen.massnahmen');
    expect($gbu->hatOffeneMassnahmen())->toBeFalse();
});

// ---------------------------------------------------------------------------
// GBU freigeben
// ---------------------------------------------------------------------------

it('gbuFreigeben setzt Status auf Freigegeben', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Haustechnik',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    Livewire::test(GbuScreen::class)
        ->call('gbuFreigeben', $gbu->id)
        ->assertHasNoErrors();

    $gbu->refresh();
    expect($gbu->status)->toBe(GbuStatus::Freigegeben)
        ->and($gbu->freigegeben_am->toDateString())->toBe(today()->toDateString());
});

// ---------------------------------------------------------------------------
// GBU fortschreiben → Ampel grün + Status Freigegeben
// ---------------------------------------------------------------------------

it('gbuFortschreiben setzt Ampel grün und Status Freigegeben', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Pflege WB 1',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subMonths(13)->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    expect($gbu->faelligkeitsStatus())->toBe('rot');

    Livewire::test(GbuScreen::class)
        ->set('fortschreibung_datum', today()->toDateString())
        ->call('gbuFortschreiben', $gbu->id)
        ->assertHasNoErrors();

    $gbu->refresh();
    expect($gbu->status)->toBe(GbuStatus::Freigegeben)
        ->and($gbu->faelligkeitsStatus())->toBe('gruen');
});

// ---------------------------------------------------------------------------
// Zukunftsdatum → Validierungsfehler
// ---------------------------------------------------------------------------

it('massnahmeUmgesetzt: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Test',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Qualifikation,
        'beschreibung' => 'Test',
        'wahrscheinlichkeit' => 1,
        'schwere' => 1,
    ]);

    $massnahme = Schutzmassnahme::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Personenbezogen,
        'beschreibung' => 'Unterweisung',
    ]);

    Livewire::test(GbuScreen::class)
        ->set('umgesetzt_am', today()->addDay()->toDateString())
        ->call('massnahmeUmgesetzt', $massnahme->id)
        ->assertHasErrors(['umgesetzt_am']);
});

it('gbuFortschreiben: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Test',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Freigegeben,
    ]);

    Livewire::test(GbuScreen::class)
        ->set('fortschreibung_datum', today()->addDay()->toDateString())
        ->call('gbuFortschreiben', $gbu->id)
        ->assertHasErrors(['fortschreibung_datum']);
});

it('wirksamkeitPruefen: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(gbuUser($this->tenant->id));

    $gbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $this->tenant->id,
        'arbeitsbereich' => 'Test',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Qualifikation,
        'beschreibung' => 'Test',
        'wahrscheinlichkeit' => 1,
        'schwere' => 1,
    ]);

    $massnahme = Schutzmassnahme::create([
        'tenant_id' => $this->tenant->id,
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Personenbezogen,
        'beschreibung' => 'Unterweisung',
        'umgesetzt_am' => today()->toDateString(),
    ]);

    Livewire::test(GbuScreen::class)
        ->set('wirksam_geprueft_am', today()->addDay()->toDateString())
        ->call('wirksamkeitPruefen', $massnahme->id)
        ->assertHasErrors(['wirksam_geprueft_am']);
});

// ---------------------------------------------------------------------------
// Gate-403 in schreibenden Methoden
// ---------------------------------------------------------------------------

// WHY: Gate in jeder schreibenden Methode wird durch mount()-Sperre abgedeckt —
// ein User ohne admin/pflegefachkraft kann keine Action erreichen, weil mount() bereits 403 gibt.
// Die mount()-Tests oben (haustechnik, kueche) decken diesen Schutz vollständig ab.
// Direkter Action-403-Test (ohne mount) ist in Livewire 4 nicht sinnvoll testbar (kein valider Snapshot).

// ---------------------------------------------------------------------------
// IDOR: User aus Tenant A kann GBU aus Tenant B nicht editieren
// ---------------------------------------------------------------------------

it('gbuFortschreiben auf GBU aus fremdem Tenant wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes GBU Heim', 'slug' => 'fremdes-gbu-ui']);

    $fremdeGbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $fremderTenant->id,
        'arbeitsbereich' => 'Fremder Bereich',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    $this->actingAs(gbuUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(GbuScreen::class)
        ->set('fortschreibung_datum', today()->toDateString())
        ->call('gbuFortschreiben', $fremdeGbu->id);
});

it('gefaehrdungHinzufuegen auf GBU aus fremdem Tenant wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes GBU Heim 2', 'slug' => 'fremdes-gbu-ui-2']);

    $fremdeGbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $fremderTenant->id,
        'arbeitsbereich' => 'Fremder Bereich',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $this->actingAs(gbuUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(GbuScreen::class)
        ->set('gefaehrdung_faktor', Gefaehrdungsfaktor::Einwirkungen->value)
        ->set('gefaehrdung_beschreibung', 'Test')
        ->call('gefaehrdungHinzufuegen', $fremdeGbu->id);
});

it('massnahmeUmgesetzt auf Massnahme aus fremdem Tenant wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes GBU Heim 3', 'slug' => 'fremdes-gbu-ui-3']);

    $fremdeGbu = Gefaehrdungsbeurteilung::create([
        'tenant_id' => $fremderTenant->id,
        'arbeitsbereich' => 'Fremder Bereich',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $fremdeGefaehrdung = Gefaehrdung::create([
        'tenant_id' => $fremderTenant->id,
        'gefaehrdungsbeurteilung_id' => $fremdeGbu->id,
        'faktor' => Gefaehrdungsfaktor::Arbeitsmittel,
        'beschreibung' => 'Fremd',
        'wahrscheinlichkeit' => 1,
        'schwere' => 1,
    ]);

    $fremdeMassnahme = Schutzmassnahme::create([
        'tenant_id' => $fremderTenant->id,
        'gefaehrdung_id' => $fremdeGefaehrdung->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Fremde Massnahme',
    ]);

    $this->actingAs(gbuUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(GbuScreen::class)
        ->set('umgesetzt_am', today()->toDateString())
        ->call('massnahmeUmgesetzt', $fremdeMassnahme->id);
});
