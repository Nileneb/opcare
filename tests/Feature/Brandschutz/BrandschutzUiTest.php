<?php

use App\Domains\Brandschutz\Enums\BrandschutzordnungTeil;
use App\Domains\Brandschutz\Enums\MangelSchwere;
use App\Domains\Brandschutz\Models\Brandschutzbegehung;
use App\Domains\Brandschutz\Models\Brandschutzmangel;
use App\Domains\Brandschutz\Models\Brandschutzordnung;
use App\Domains\Brandschutz\Models\Raeumungsuebung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Brandschutz\Brandschutz as BrandschutzScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Brandschutz UI Heim', 'slug' => 'brandschutz-ui-heim']);
    app(CurrentTenant::class)->set($this->tenant);

    foreach (['admin', 'haustechnik', 'pflegefachkraft', 'kueche'] as $r) {
        Role::findOrCreate($r);
    }
});

function brandschutzUser(int $tenantId, string $rolle = 'admin'): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($rolle);

    return $u;
}

// ---------------------------------------------------------------------------
// Gate
// ---------------------------------------------------------------------------

it('zeigt Brandschutz-Screen für admin-Rolle', function () {
    $this->actingAs(brandschutzUser($this->tenant->id, 'admin'));

    Livewire::test(BrandschutzScreen::class)->assertOk();
});

it('zeigt Brandschutz-Screen für haustechnik-Rolle', function () {
    $this->actingAs(brandschutzUser($this->tenant->id, 'haustechnik'));

    Livewire::test(BrandschutzScreen::class)->assertOk();
});

it('verwehrt Zugriff für pflegefachkraft-Rolle (403)', function () {
    $this->actingAs(brandschutzUser($this->tenant->id, 'pflegefachkraft'));

    Livewire::test(BrandschutzScreen::class)->assertForbidden();
});

it('verwehrt Zugriff für kueche-Rolle (403)', function () {
    $this->actingAs(brandschutzUser($this->tenant->id, 'kueche'));

    Livewire::test(BrandschutzScreen::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Brandschutzordnung anlegen + freigeben → Ampel grün
// ---------------------------------------------------------------------------

it('legt Brandschutzordnung an und persistiert sie tenant-scoped', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('ordnung_titel', 'Brandschutzordnung Haus A')
        ->set('ordnung_teil', BrandschutzordnungTeil::B->value)
        ->set('ordnung_version', '2024-01')
        ->set('ordnung_revision_intervall_monate', 24)
        ->call('ordnungAnlegen')
        ->assertHasNoErrors();

    $ordnung = Brandschutzordnung::where('tenant_id', $this->tenant->id)
        ->where('titel', 'Brandschutzordnung Haus A')
        ->first();

    expect($ordnung)->not->toBeNull()
        ->and($ordnung->teil)->toBe(BrandschutzordnungTeil::B)
        ->and($ordnung->version)->toBe('2024-01')
        ->and($ordnung->aktiv)->toBeTrue()
        ->and($ordnung->status())->toBe('entwurf');
});

it('ordnungAnlegen schlägt fehl ohne Titel', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('ordnung_titel', '')
        ->set('ordnung_teil', BrandschutzordnungTeil::A->value)
        ->set('ordnung_version', '2024-01')
        ->call('ordnungAnlegen')
        ->assertHasErrors(['ordnung_titel']);
});

it('ordnungFreigeben setzt Ampel auf grün (aktuell)', function () {
    $user = brandschutzUser($this->tenant->id);
    $this->actingAs($user);

    $ordnung = Brandschutzordnung::create([
        'tenant_id' => $this->tenant->id,
        'titel' => 'Test Ordnung',
        'teil' => BrandschutzordnungTeil::A,
        'version' => '2024-01',
        'revision_intervall_monate' => 24,
        'aktiv' => true,
    ]);

    expect($ordnung->status())->toBe('entwurf')
        ->and($ordnung->ampel())->toBe('red');

    Livewire::test(BrandschutzScreen::class)
        ->call('ordnungFreigeben', $ordnung->id)
        ->assertHasNoErrors();

    $ordnung->refresh();
    expect($ordnung->freigegeben_am->toDateString())->toBe(today()->toDateString())
        ->and($ordnung->freigegeben_von)->toBe($user->id)
        ->and($ordnung->status())->toBe('aktuell')
        ->and($ordnung->ampel())->toBe('green');
});

// ---------------------------------------------------------------------------
// Begehung erfassen + Mangel hinzufügen → offene-Mängel-Badge
// ---------------------------------------------------------------------------

it('Begehung erfassen persistiert tenant-scoped', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('begehung_bereich', 'Wohnbereich 1')
        ->set('begehung_begangen_am', today()->toDateString())
        ->set('begehung_intervall_monate', 12)
        ->set('begehung_bemerkung', 'Alles in Ordnung')
        ->call('begehungErfassen')
        ->assertHasNoErrors();

    $begehung = Brandschutzbegehung::where('tenant_id', $this->tenant->id)
        ->where('bereich', 'Wohnbereich 1')
        ->first();

    expect($begehung)->not->toBeNull()
        ->and($begehung->intervall_monate)->toBe(12)
        ->and($begehung->bemerkung)->toBe('Alles in Ordnung');
});

it('mangelHinzufuegen erzeugt offene-Mängel-Badge', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    $begehung = Brandschutzbegehung::create([
        'tenant_id' => $this->tenant->id,
        'bereich' => 'Keller',
        'begangen_am' => today()->toDateString(),
        'intervall_monate' => 12,
    ]);

    Livewire::test(BrandschutzScreen::class)
        ->set('mangel_beschreibung', 'Feuertür klemmt')
        ->set('mangel_schwere', MangelSchwere::Wesentlich->value)
        ->set('mangel_frist', today()->addDays(14)->toDateString())
        ->call('mangelHinzufuegen', $begehung->id)
        ->assertHasNoErrors();

    $begehung->load('maengel');
    expect($begehung->hatOffeneMaengel())->toBeTrue()
        ->and($begehung->offeneMaengel()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Mangel behoben → hatOffeneMaengel false
// ---------------------------------------------------------------------------

it('mangelBehoben: hatOffeneMaengel wird false', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    $begehung = Brandschutzbegehung::create([
        'tenant_id' => $this->tenant->id,
        'bereich' => 'Küche',
        'begangen_am' => today()->toDateString(),
        'intervall_monate' => 12,
    ]);

    $mangel = Brandschutzmangel::create([
        'tenant_id' => $this->tenant->id,
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Fluchtweg versperrt',
        'schwere' => MangelSchwere::Kritisch,
    ]);

    expect($mangel->istOffen())->toBeTrue();

    Livewire::test(BrandschutzScreen::class)
        ->set('behoben_am', today()->toDateString())
        ->set('behoben_notiz', 'Weg geräumt')
        ->call('mangelBehoben', $mangel->id)
        ->assertHasNoErrors();

    $mangel->refresh();
    expect($mangel->istOffen())->toBeFalse()
        ->and($mangel->behoben_am->toDateString())->toBe(today()->toDateString())
        ->and($mangel->behoben_notiz)->toBe('Weg geräumt');

    $begehung->load('maengel');
    expect($begehung->hatOffeneMaengel())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Räumungsübung dokumentieren → erscheint mit grüner Frist
// ---------------------------------------------------------------------------

it('uebungDokumentieren persistiert tenant-scoped und erzeugt grüne Frist', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('uebung_durchgefuehrt_am', today()->toDateString())
        ->set('uebung_intervall_monate', 12)
        ->set('uebung_bereich', 'Gesamtes Gebäude')
        ->set('uebung_szenario', 'Küchenbrand')
        ->set('uebung_teilnehmer_anzahl', 45)
        ->set('uebung_dauer_minuten', 15)
        ->set('uebung_erkenntnisse', 'Evakuierung reibungslos')
        ->call('uebungDokumentieren')
        ->assertHasNoErrors();

    $uebung = Raeumungsuebung::where('tenant_id', $this->tenant->id)->latest('durchgefuehrt_am')->first();

    expect($uebung)->not->toBeNull()
        ->and($uebung->faelligkeitsStatus())->toBe('gruen')
        ->and($uebung->teilnehmer_anzahl)->toBe(45)
        ->and($uebung->dauer_minuten)->toBe(15)
        ->and($uebung->erkenntnisse)->toBe('Evakuierung reibungslos');
});

// ---------------------------------------------------------------------------
// Zukunftsdatum → Validierungsfehler
// ---------------------------------------------------------------------------

it('begehungErfassen: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('begehung_bereich', 'Dach')
        ->set('begehung_begangen_am', today()->addDay()->toDateString())
        ->call('begehungErfassen')
        ->assertHasErrors(['begehung_begangen_am']);
});

it('uebungDokumentieren: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    Livewire::test(BrandschutzScreen::class)
        ->set('uebung_durchgefuehrt_am', today()->addDay()->toDateString())
        ->call('uebungDokumentieren')
        ->assertHasErrors(['uebung_durchgefuehrt_am']);
});

it('mangelBehoben: Zukunftsdatum ergibt Validierungsfehler', function () {
    $this->actingAs(brandschutzUser($this->tenant->id));

    $begehung = Brandschutzbegehung::create([
        'tenant_id' => $this->tenant->id,
        'bereich' => 'Test',
        'begangen_am' => today()->toDateString(),
        'intervall_monate' => 12,
    ]);

    $mangel = Brandschutzmangel::create([
        'tenant_id' => $this->tenant->id,
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Test Mangel',
        'schwere' => MangelSchwere::Gering,
    ]);

    Livewire::test(BrandschutzScreen::class)
        ->set('behoben_am', today()->addDay()->toDateString())
        ->call('mangelBehoben', $mangel->id)
        ->assertHasErrors(['behoben_am']);
});

// ---------------------------------------------------------------------------
// IDOR: Fremd-Tenant → ModelNotFoundException
// ---------------------------------------------------------------------------

it('ordnungFreigeben auf Ordnung fremden Tenants wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Brandschutz Heim', 'slug' => 'fremdes-bs-ui']);

    $fremdeOrdnung = Brandschutzordnung::create([
        'tenant_id' => $fremderTenant->id,
        'titel' => 'Fremde Ordnung',
        'teil' => BrandschutzordnungTeil::A,
        'version' => '2024-01',
        'revision_intervall_monate' => 24,
        'aktiv' => true,
    ]);

    $this->actingAs(brandschutzUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(BrandschutzScreen::class)
        ->call('ordnungFreigeben', $fremdeOrdnung->id);
});

it('mangelHinzufuegen auf Begehung fremden Tenants wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Brandschutz Heim 2', 'slug' => 'fremdes-bs-ui-2']);

    $fremdeBegehung = Brandschutzbegehung::create([
        'tenant_id' => $fremderTenant->id,
        'bereich' => 'Fremder Bereich',
        'begangen_am' => today()->toDateString(),
        'intervall_monate' => 12,
    ]);

    $this->actingAs(brandschutzUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(BrandschutzScreen::class)
        ->set('mangel_beschreibung', 'Test')
        ->set('mangel_schwere', MangelSchwere::Gering->value)
        ->call('mangelHinzufuegen', $fremdeBegehung->id);
});

it('mangelBehoben auf Mangel fremden Tenants wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Brandschutz Heim 3', 'slug' => 'fremdes-bs-ui-3']);

    $fremdeBegehung = Brandschutzbegehung::create([
        'tenant_id' => $fremderTenant->id,
        'bereich' => 'Fremder Bereich',
        'begangen_am' => today()->toDateString(),
        'intervall_monate' => 12,
    ]);

    $fremdeMangel = Brandschutzmangel::create([
        'tenant_id' => $fremderTenant->id,
        'brandschutzbegehung_id' => $fremdeBegehung->id,
        'beschreibung' => 'Fremder Mangel',
        'schwere' => MangelSchwere::Gering,
    ]);

    $this->actingAs(brandschutzUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(BrandschutzScreen::class)
        ->set('behoben_am', today()->toDateString())
        ->call('mangelBehoben', $fremdeMangel->id);
});
