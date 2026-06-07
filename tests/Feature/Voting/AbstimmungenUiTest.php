<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\Stimme;
use App\Domains\Voting\Models\Wahlteilnahme;
use App\Livewire\Voting\Abstimmungen;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Voting-UI-Test', 'slug' => 'voting-ui-test']);
    app(CurrentTenant::class)->set($this->tenant);

    Role::findOrCreate('admin');
    Role::findOrCreate('pflegefachkraft');

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('admin');

    $this->mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ─── Seite aufrufbar ─────────────────────────────────────────────────────────

it('eingeloggter User erreicht die Abstimmungs-Seite', function () {
    $this->actingAs($this->mitarbeiter);

    Livewire::test(Abstimmungen::class)->assertOk();
});

// ─── Anlegen ─────────────────────────────────────────────────────────────────

it('admin kann eine Umfrage für Mitarbeitende anlegen und sie wird direkt eröffnet', function () {
    config(['voting.online_wahl_aktiv' => false]);
    $this->actingAs($this->admin);

    Livewire::test(Abstimmungen::class)
        ->set('titel', 'Ausflugsziel 2026')
        ->set('beschreibung', 'Wohin soll es gehen?')
        ->set('elektorat', Elektorat::Mitarbeitende->value)
        ->set('modus', Stimmodus::Geheim->value)
        ->set('art', Abstimmungsart::Umfrage->value)
        ->set('optionen', ['Strand', 'Berge'])
        ->call('anlegen')
        ->assertHasNoErrors();

    $abstimmung = Abstimmung::where('tenant_id', $this->tenant->id)
        ->where('titel', 'Ausflugsziel 2026')
        ->first();

    expect($abstimmung)->not->toBeNull()
        ->and($abstimmung->status)->toBe(AbstimmungStatus::Offen);

    expect($abstimmung->optionen->count())->toBe(2);

    expect(
        Wahlteilnahme::where('abstimmung_id', $abstimmung->id)
            ->where('user_id', $this->admin->id)
            ->exists()
    )->toBeTrue();

    expect(
        Wahlteilnahme::where('abstimmung_id', $abstimmung->id)
            ->where('user_id', $this->mitarbeiter->id)
            ->exists()
    )->toBeTrue();
});

// ─── Abstimmen ───────────────────────────────────────────────────────────────

it('eingeloggter User kann abstimmen, hat_abgestimmt wird true, Beleg wird gesetzt', function () {
    config(['voting.online_wahl_aktiv' => false]);
    $this->actingAs($this->admin);

    $abstimmung = Abstimmung::create([
        'tenant_id' => $this->tenant->id,
        'titel' => 'Abstimm-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
        'ergebnis_sichtbar' => false,
        'mehrfachauswahl' => false,
        'erstellt_von' => $this->admin->id,
    ]);

    $option = $abstimmung->optionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Ja',
        'sortierung' => 0,
    ]);
    $abstimmung->optionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Nein',
        'sortierung' => 1,
    ]);

    Wahlteilnahme::create([
        'tenant_id' => $this->tenant->id,
        'abstimmung_id' => $abstimmung->id,
        'user_id' => $this->admin->id,
        'resident_id' => null,
        'hat_abgestimmt' => false,
    ]);

    $component = Livewire::test(Abstimmungen::class)
        ->set("auswahl.{$abstimmung->id}.0", $option->id)
        ->call('abstimmen', $abstimmung->id)
        ->assertHasNoErrors();

    expect($component->get('belegToken'))->toBeString()->not->toBeEmpty();

    $teilnahme = Wahlteilnahme::where('abstimmung_id', $abstimmung->id)
        ->where('user_id', $this->admin->id)
        ->first();

    expect($teilnahme->hat_abgestimmt)->toBeTrue();
    expect(Stimme::where('abstimmung_id', $abstimmung->id)->count())->toBe(1);
});

it('Doppelabstimmung erzeugt Fehler und legt keine zweite Stimme an', function () {
    config(['voting.online_wahl_aktiv' => false]);
    $this->actingAs($this->admin);

    $abstimmung = Abstimmung::create([
        'tenant_id' => $this->tenant->id,
        'titel' => 'Doppel-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
        'ergebnis_sichtbar' => false,
        'mehrfachauswahl' => false,
        'erstellt_von' => $this->admin->id,
    ]);

    $option = $abstimmung->optionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Ja',
        'sortierung' => 0,
    ]);
    $abstimmung->optionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Nein',
        'sortierung' => 1,
    ]);

    Wahlteilnahme::create([
        'tenant_id' => $this->tenant->id,
        'abstimmung_id' => $abstimmung->id,
        'user_id' => $this->admin->id,
        'resident_id' => null,
        'hat_abgestimmt' => false,
    ]);

    Livewire::test(Abstimmungen::class)
        ->set("auswahl.{$abstimmung->id}.0", $option->id)
        ->call('abstimmen', $abstimmung->id)
        ->assertHasNoErrors();

    Livewire::actingAs($this->admin)
        ->test(Abstimmungen::class)
        ->set("auswahl.{$abstimmung->id}.0", $option->id)
        ->call('abstimmen', $abstimmung->id)
        ->assertHasErrors(["auswahl.{$abstimmung->id}"]);

    expect(Stimme::where('abstimmung_id', $abstimmung->id)->count())->toBe(1);
});

// ─── Geheim-Erzwingung im Anlege-Flow ────────────────────────────────────────

it('Heimbeirat-Wahl (Bewohner) mit Modus Namentlich erzeugt Validierungsfehler', function () {
    $this->actingAs($this->admin);

    Livewire::test(Abstimmungen::class)
        ->set('titel', 'Heimbeiratswahl')
        ->set('elektorat', Elektorat::Bewohner->value)
        ->set('modus', Stimmodus::Namentlich->value)
        ->set('art', Abstimmungsart::Wahl->value)
        ->set('optionen', ['Kandidat A', 'Kandidat B'])
        ->call('anlegen')
        ->assertHasErrors(['modus']);

    expect(Abstimmung::where('titel', 'Heimbeiratswahl')->exists())->toBeFalse();
});

// ─── Gate: Nicht-darfAnlegen kann nicht anlegen ───────────────────────────────

it('normaler Mitarbeiter ohne Leitung-Rolle erhält 403 beim Anlegen', function () {
    $this->actingAs($this->mitarbeiter);

    // Livewire wandelt abort_unless(false, 403) in einen 403-Response um — kein Crash,
    // aber der Call liefert einen 403-Status zurück.
    Livewire::test(Abstimmungen::class)
        ->set('titel', 'Unerlaubt')
        ->set('elektorat', Elektorat::Mitarbeitende->value)
        ->set('modus', Stimmodus::Geheim->value)
        ->set('art', Abstimmungsart::Umfrage->value)
        ->set('optionen', ['A', 'B'])
        ->call('anlegen')
        ->assertForbidden();

    expect(Abstimmung::where('titel', 'Unerlaubt')->exists())->toBeFalse();
});
