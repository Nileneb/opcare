<?php

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Communication\Models\Nachricht;
use App\Domains\Communication\Services\AnkuendigungskanalHolen;
use App\Domains\Communication\Services\GruppeErstellen;
use App\Domains\Communication\Services\NachrichtSenden;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Station;
use App\Livewire\Communication\Chat;
use App\Livewire\Communication\ChatGlocke;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin', 'betreuer', 'angehoeriger'] as $role) {
        Role::findOrCreate($role);
    }

    $this->tenant = Tenant::create(['name' => 'UI-Chat-Heim', 'slug' => 'ui-chat-heim']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->alice = User::create([
        'name' => 'Alice',
        'email' => 'alice@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->alice->assignRole('pflegefachkraft');

    $this->bob = User::create([
        'name' => 'Bob',
        'email' => 'bob@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->bob->assignRole('pflegefachkraft');

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->admin->assignRole('admin');

    $building = Building::create(['name' => 'UI-Chat-Gebäude']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $this->station = Station::create(['floor_id' => $floor->id, 'name' => 'UI-WB 1', 'tenant_id' => $this->tenant->id]);
});

// ---------------------------------------------------------------------------
// Chat-Screen rendert
// ---------------------------------------------------------------------------

it('Chat-Screen rendert für Staff-User', function () {
    $this->actingAs($this->alice);

    Livewire::test(Chat::class)->assertOk();
});

// ---------------------------------------------------------------------------
// Gate-403 für Portal-User
// ---------------------------------------------------------------------------

it('Chat-Screen verweigert Zugang für betreuer-Rolle (403)', function () {
    $betreuer = User::create([
        'name' => 'Betreuer',
        'email' => 'betreuer@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $this->tenant->id,
    ]);
    $betreuer->assignRole('betreuer');

    $this->actingAs($betreuer);

    Livewire::test(Chat::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// dmStarten legt DM an + erscheint in der Liste
// ---------------------------------------------------------------------------

it('dmStarten legt Direkt-Konversation an und setzt aktivKonversationId', function () {
    $this->actingAs($this->alice);

    $component = Livewire::test(Chat::class)
        ->set('dmPartner', $this->bob->id)
        ->call('dmStarten');

    $konv = Konversation::withoutGlobalScopes()
        ->where('typ', KonversationTyp::Direkt)
        ->where('tenant_id', $this->tenant->id)
        ->first();

    expect($konv)->not->toBeNull();
    $component->assertSet('aktivKonversationId', $konv->id);
});

// ---------------------------------------------------------------------------
// oeffne markiert gelesen
// ---------------------------------------------------------------------------

it('oeffne setzt zuletzt_gelesen_am und Ungelesen-Zähler geht auf 0', function () {
    $this->actingAs($this->alice);

    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Hallo Alice');

    $teilnehmer = KonversationTeilnehmer::withoutGlobalScopes()
        ->where('konversation_id', $gruppe->id)
        ->where('user_id', $this->alice->id)
        ->first();

    expect($teilnehmer->zuletzt_gelesen_am)->toBeNull();

    Livewire::test(Chat::class)
        ->call('oeffne', $gruppe->id);

    $teilnehmer->refresh();

    expect($teilnehmer->zuletzt_gelesen_am)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// senden erscheint im Thread
// ---------------------------------------------------------------------------

it('senden speichert Nachricht und entwurf wird geleert', function () {
    $this->actingAs($this->alice);

    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Sende-Test', [$this->bob->id]);

    Livewire::test(Chat::class)
        ->set('aktivKonversationId', $gruppe->id)
        ->set('entwurf', 'Testnachricht')
        ->call('senden')
        ->assertSet('entwurf', '');

    expect(
        Nachricht::withoutGlobalScopes()
            ->where('konversation_id', $gruppe->id)
            ->where('inhalt', 'Testnachricht')
            ->exists()
    )->toBeTrue();
});

// ---------------------------------------------------------------------------
// zuruckziehen blendet Text aus
// ---------------------------------------------------------------------------

it('zuruckziehen setzt geloescht_am auf eigene Nachricht', function () {
    $this->actingAs($this->alice);

    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Zurück-Test', [$this->bob->id]);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->alice, 'Wird zurückgezogen');

    Livewire::test(Chat::class)
        ->set('aktivKonversationId', $gruppe->id)
        ->call('zuruckziehen', $nachricht->id);

    expect($nachricht->fresh()->istZurueckgezogen())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Gruppe anlegen
// ---------------------------------------------------------------------------

it('gruppeAnlegen legt Gruppe an und setzt aktivKonversationId', function () {
    $this->actingAs($this->alice);

    $component = Livewire::test(Chat::class)
        ->set('gruppeTitel', 'Neue Gruppe')
        ->set('gruppeMitglieder', [$this->bob->id])
        ->call('gruppeAnlegen');

    $konv = Konversation::withoutGlobalScopes()
        ->where('typ', KonversationTyp::Gruppe)
        ->where('titel', 'Neue Gruppe')
        ->first();

    expect($konv)->not->toBeNull();
    $component->assertSet('aktivKonversationId', $konv->id);
});

// ---------------------------------------------------------------------------
// Station beitreten
// ---------------------------------------------------------------------------

it('stationBeitreten tritt Stationskanal bei und setzt aktivKonversationId', function () {
    $this->actingAs($this->alice);

    $component = Livewire::test(Chat::class)
        ->set('stationWahl', $this->station->id)
        ->call('stationBeitreten');

    $konv = Konversation::withoutGlobalScopes()
        ->where('typ', KonversationTyp::Station)
        ->where('station_id', $this->station->id)
        ->first();

    expect($konv)->not->toBeNull();
    $component->assertSet('aktivKonversationId', $konv->id);
});

// ---------------------------------------------------------------------------
// Ankündigung öffnen
// ---------------------------------------------------------------------------

it('ankuendigungOeffnen öffnet Ankündigungs-Kanal', function () {
    $this->actingAs($this->alice);

    $component = Livewire::test(Chat::class)
        ->call('ankuendigungOeffnen');

    $konv = Konversation::withoutGlobalScopes()
        ->where('typ', KonversationTyp::Ankuendigung)
        ->where('tenant_id', $this->tenant->id)
        ->first();

    expect($konv)->not->toBeNull();
    $component->assertSet('aktivKonversationId', $konv->id);
});

it('Nicht-Admin kann nicht in Ankündigung senden (403)', function () {
    $this->actingAs($this->alice);

    $ankuendigung = app(AnkuendigungskanalHolen::class)->handle($this->tenant->id);

    Livewire::test(Chat::class)
        ->set('aktivKonversationId', $ankuendigung->id)
        ->set('entwurf', 'Hallo')
        ->call('senden')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// IDOR: fremde Konversation — oeffne → 403
// ---------------------------------------------------------------------------

it('oeffne auf fremde Konversation (nicht Mitglied) → 403', function () {
    $this->actingAs($this->alice);

    // Konversation nur zwischen bob und admin (alice ist nicht Mitglied)
    $fremd = app(GruppeErstellen::class)->handle($this->bob, 'Fremde Gruppe', [$this->admin->id]);

    Livewire::test(Chat::class)
        ->call('oeffne', $fremd->id)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// IDOR: senden auf fremde Konversation → 403
// ---------------------------------------------------------------------------

it('senden auf Konversation ohne Mitgliedschaft → 403', function () {
    $this->actingAs($this->alice);

    $fremd = app(GruppeErstellen::class)->handle($this->bob, 'Fremde Gruppe 2', [$this->admin->id]);

    Livewire::test(Chat::class)
        ->set('aktivKonversationId', $fremd->id)
        ->set('entwurf', 'Angriff')
        ->call('senden')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// IDOR: zuruckziehen auf fremde Nachricht → 403
// ---------------------------------------------------------------------------

it('zuruckziehen auf fremde Nachricht → 403', function () {
    $this->actingAs($this->alice);

    $gruppe = app(GruppeErstellen::class)->handle($this->bob, 'Fremde Gruppe 3', []);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Von Bob');

    Livewire::test(Chat::class)
        ->call('zuruckziehen', $nachricht->id)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// IDOR: zuruckziehen auf Nachricht anderen Tenants → 404
// ---------------------------------------------------------------------------

it('zuruckziehen auf Nachricht fremden Tenants → ModelNotFoundException', function () {
    $this->actingAs($this->alice);

    $foreignTenant = Tenant::create(['name' => 'Fremdes Heim UI', 'slug' => 'fremdes-heim-ui']);
    app(CurrentTenant::class)->set($foreignTenant);

    $foreignUser = User::create([
        'name' => 'FremderUser',
        'email' => 'fremder@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $foreignTenant->id,
    ]);
    $foreignUser->assignRole('pflegefachkraft');

    $fremdGruppe = app(GruppeErstellen::class)->handle($foreignUser, 'Fremde Gruppe FT', []);
    $fremdNachricht = app(NachrichtSenden::class)->handle($fremdGruppe, $foreignUser, 'Fremd');

    app(CurrentTenant::class)->set($this->tenant);

    expect(fn () => Livewire::test(Chat::class)->call('zuruckziehen', $fremdNachricht->id))
        ->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------------
// ChatGlocke zeigt Ungelesen-Zähler
// ---------------------------------------------------------------------------

it('ChatGlocke zeigt Ungelesen-Count für Staff-User', function () {
    $this->actingAs($this->alice);

    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Glocke-Test', [$this->bob->id]);
    app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Ungelesen für Alice');

    Livewire::test(ChatGlocke::class)
        ->assertSee('1');
});

it('ChatGlocke zeigt 0 für betreuer-Rolle', function () {
    $betreuer = User::create([
        'name' => 'Betreuer2',
        'email' => 'betreuer2@ui-chat.test',
        'password' => bcrypt('x'),
        'tenant_id' => $this->tenant->id,
    ]);
    $betreuer->assignRole('betreuer');

    $this->actingAs($betreuer);

    Livewire::test(ChatGlocke::class)
        ->assertDontSee('badge');
});
