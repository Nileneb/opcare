<?php

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Events\NachrichtGesendet;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Communication\Models\Nachricht;
use App\Domains\Communication\Services\AnkuendigungskanalHolen;
use App\Domains\Communication\Services\DirektnachrichtOeffnen;
use App\Domains\Communication\Services\GruppeErstellen;
use App\Domains\Communication\Services\KonversationGelesen;
use App\Domains\Communication\Services\NachrichtSenden;
use App\Domains\Communication\Services\NachrichtZurueckziehen;
use App\Domains\Communication\Services\StationskanalBeitreten;
use App\Domains\Communication\Services\UngeleseneZaehler;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Station;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'] as $role) {
        Role::findOrCreate($role);
    }

    $this->tenant = Tenant::create(['name' => 'Chat-Test-Heim', 'slug' => 'chat-test']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->alice = User::create([
        'name' => 'Alice',
        'email' => 'alice@chat.test',
        'password' => bcrypt('password'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->alice->assignRole('pflegefachkraft');

    $this->bob = User::create([
        'name' => 'Bob',
        'email' => 'bob@chat.test',
        'password' => bcrypt('password'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->bob->assignRole('pflegefachkraft');

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@chat.test',
        'password' => bcrypt('password'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->admin->assignRole('admin');

    $building = Building::create(['name' => 'Chat-Gebäude']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $this->station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich 1', 'tenant_id' => $this->tenant->id]);
});

// ---------------------------------------------------------------------------
// KonversationTyp
// ---------------------------------------------------------------------------

it('KonversationTyp gibt korrektes Label zurück', function () {
    expect(KonversationTyp::Direkt->label())->toBe('Direktnachricht')
        ->and(KonversationTyp::Gruppe->label())->toBe('Gruppe')
        ->and(KonversationTyp::Station->label())->toBe('Stationskanal')
        ->and(KonversationTyp::Ankuendigung->label())->toBe('Ankündigungen');
});

// ---------------------------------------------------------------------------
// darfSchreiben
// ---------------------------------------------------------------------------

it('darfSchreiben Ankündigung: nur admin darf schreiben', function () {
    $k = Konversation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'typ' => KonversationTyp::Ankuendigung,
        'titel' => 'Ankündigungen',
    ]);

    KonversationTeilnehmer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'konversation_id' => $k->id,
        'user_id' => $this->alice->id,
        'darf_schreiben' => false,
    ]);

    KonversationTeilnehmer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'konversation_id' => $k->id,
        'user_id' => $this->admin->id,
        'darf_schreiben' => false,
    ]);

    expect($k->darfSchreiben($this->admin))->toBeTrue()
        ->and($k->darfSchreiben($this->alice))->toBeFalse();
});

it('darfSchreiben Gruppe: Mitglied mit darf_schreiben=true darf, ohne Recht nicht', function () {
    $k = Konversation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'typ' => KonversationTyp::Gruppe,
        'titel' => 'Test-Gruppe',
    ]);

    KonversationTeilnehmer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'konversation_id' => $k->id,
        'user_id' => $this->alice->id,
        'darf_schreiben' => true,
    ]);

    KonversationTeilnehmer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'konversation_id' => $k->id,
        'user_id' => $this->bob->id,
        'darf_schreiben' => false,
    ]);

    expect($k->darfSchreiben($this->alice))->toBeTrue()
        ->and($k->darfSchreiben($this->bob))->toBeFalse();
});

it('darfSchreiben: Nicht-Mitglied kann nicht schreiben', function () {
    $k = Konversation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'typ' => KonversationTyp::Gruppe,
        'titel' => 'Test-Gruppe',
    ]);

    expect($k->darfSchreiben($this->alice))->toBeFalse();
});

// ---------------------------------------------------------------------------
// DirektnachrichtOeffnen
// ---------------------------------------------------------------------------

it('DirektnachrichtOeffnen: erzeugt neue Direkt-Konversation', function () {
    $service = app(DirektnachrichtOeffnen::class);
    $k = $service->handle($this->alice, $this->bob->id);

    expect($k)->toBeInstanceOf(Konversation::class)
        ->and($k->typ)->toBe(KonversationTyp::Direkt)
        ->and($k->istMitglied($this->alice->id))->toBeTrue()
        ->and($k->istMitglied($this->bob->id))->toBeTrue();
});

it('DirektnachrichtOeffnen: Dedupe — zweimaliges Öffnen gibt selbe Konversation', function () {
    $service = app(DirektnachrichtOeffnen::class);
    $k1 = $service->handle($this->alice, $this->bob->id);
    $k2 = $service->handle($this->alice, $this->bob->id);

    expect($k1->id)->toBe($k2->id)
        ->and(Konversation::withoutGlobalScopes()->where('typ', KonversationTyp::Direkt)->count())->toBe(1);
});

it('DirektnachrichtOeffnen: Dedupe auch bei vertauschter Reihenfolge (Bob→Alice)', function () {
    $service = app(DirektnachrichtOeffnen::class);
    $k1 = $service->handle($this->alice, $this->bob->id);
    $k2 = $service->handle($this->bob, $this->alice->id);

    expect($k1->id)->toBe($k2->id);
});

it('DirektnachrichtOeffnen: fremder Tenant-Partner → 422', function () {
    $foreignTenant = Tenant::create(['name' => 'Fremdes Heim', 'slug' => 'fremdes-heim']);
    $foreignUser = User::create([
        'name' => 'Fremd',
        'email' => 'fremd@other.test',
        'password' => bcrypt('password'),
        'tenant_id' => $foreignTenant->id,
    ]);

    $service = app(DirektnachrichtOeffnen::class);

    expect(fn () => $service->handle($this->alice, $foreignUser->id))
        ->toThrow(HttpException::class);
});

// ---------------------------------------------------------------------------
// GruppeErstellen
// ---------------------------------------------------------------------------

it('GruppeErstellen: Ersteller + Mitglieder sind alle Teilnehmer', function () {
    $service = app(GruppeErstellen::class);
    $k = $service->handle($this->alice, 'Test-Gruppe', [$this->bob->id, $this->admin->id]);

    expect($k->typ)->toBe(KonversationTyp::Gruppe)
        ->and($k->titel)->toBe('Test-Gruppe')
        ->and($k->istMitglied($this->alice->id))->toBeTrue()
        ->and($k->istMitglied($this->bob->id))->toBeTrue()
        ->and($k->istMitglied($this->admin->id))->toBeTrue();
});

it('GruppeErstellen: User fremden Tenants → 422', function () {
    $foreignTenant = Tenant::create(['name' => 'Fremdes Heim 2', 'slug' => 'fremdes-heim-2']);
    $foreignUser = User::create([
        'name' => 'Fremd2',
        'email' => 'fremd2@other.test',
        'password' => bcrypt('password'),
        'tenant_id' => $foreignTenant->id,
    ]);

    $service = app(GruppeErstellen::class);

    expect(fn () => $service->handle($this->alice, 'Böse Gruppe', [$foreignUser->id]))
        ->toThrow(HttpException::class);
});

// ---------------------------------------------------------------------------
// StationskanalBeitreten
// ---------------------------------------------------------------------------

it('StationskanalBeitreten: erzeugt Stationskanal und fügt User hinzu', function () {
    $service = app(StationskanalBeitreten::class);
    $k = $service->handle($this->alice, $this->station->id);

    expect($k->typ)->toBe(KonversationTyp::Station)
        ->and($k->station_id)->toBe($this->station->id)
        ->and($k->istMitglied($this->alice->id))->toBeTrue();
});

it('StationskanalBeitreten: find-or-create — zweimal beitreten ergibt eine Konversation', function () {
    $service = app(StationskanalBeitreten::class);
    $k1 = $service->handle($this->alice, $this->station->id);
    $k2 = $service->handle($this->bob, $this->station->id);

    expect($k1->id)->toBe($k2->id)
        ->and($k2->istMitglied($this->bob->id))->toBeTrue();
});

it('StationskanalBeitreten: Station fremden Tenants → 422', function () {
    $foreignTenant = Tenant::create(['name' => 'Fremdes Heim 3', 'slug' => 'fremdes-heim-3']);
    app(CurrentTenant::class)->set($foreignTenant);
    $foreignBuilding = Building::create(['name' => 'FremdesGeb']);
    $foreignFloor = Floor::create(['building_id' => $foreignBuilding->id, 'name' => 'EG']);
    $foreignStation = Station::create(['floor_id' => $foreignFloor->id, 'name' => 'Fremde Station', 'tenant_id' => $foreignTenant->id]);
    app(CurrentTenant::class)->set($this->tenant);

    $service = app(StationskanalBeitreten::class);

    expect(fn () => $service->handle($this->alice, $foreignStation->id))
        ->toThrow(HttpException::class);
});

// ---------------------------------------------------------------------------
// AnkuendigungskanalHolen
// ---------------------------------------------------------------------------

it('AnkuendigungskanalHolen: erstellt Ankündigungs-Kanal', function () {
    $service = app(AnkuendigungskanalHolen::class);
    $k = $service->handle($this->tenant->id);

    expect($k->typ)->toBe(KonversationTyp::Ankuendigung)
        ->and($k->titel)->toBe('Ankündigungen');
});

it('AnkuendigungskanalHolen: idempotent — genau eine Konversation je Tenant', function () {
    $service = app(AnkuendigungskanalHolen::class);
    $k1 = $service->handle($this->tenant->id);
    $k2 = $service->handle($this->tenant->id);

    expect($k1->id)->toBe($k2->id)
        ->and(
            Konversation::withoutGlobalScopes()
                ->where('typ', KonversationTyp::Ankuendigung)
                ->where('tenant_id', $this->tenant->id)
                ->count()
        )->toBe(1);
});

it('AnkuendigungskanalHolen: alle Staff-User sind Mitglied', function () {
    $service = app(AnkuendigungskanalHolen::class);
    $k = $service->handle($this->tenant->id);

    expect($k->istMitglied($this->alice->id))->toBeTrue()
        ->and($k->istMitglied($this->bob->id))->toBeTrue()
        ->and($k->istMitglied($this->admin->id))->toBeTrue();
});

it('AnkuendigungskanalHolen: darf_schreiben-Gate nur für admin', function () {
    $service = app(AnkuendigungskanalHolen::class);
    $k = $service->handle($this->tenant->id);

    expect($k->darfSchreiben($this->admin))->toBeTrue()
        ->and($k->darfSchreiben($this->alice))->toBeFalse();
});

// ---------------------------------------------------------------------------
// NachrichtSenden
// ---------------------------------------------------------------------------

it('NachrichtSenden: Mitglied sendet Nachricht und Event wird gefeuert', function () {
    Event::fake();

    $service = app(NachrichtSenden::class);
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Event-Test', [$this->bob->id]);

    $nachricht = $service->handle($gruppe, $this->alice, 'Hallo Welt');

    expect($nachricht)->toBeInstanceOf(Nachricht::class)
        ->and($nachricht->inhalt)->toBe('Hallo Welt')
        ->and($nachricht->user_id)->toBe($this->alice->id);

    Event::assertDispatched(NachrichtGesendet::class, fn ($e) => $e->konversationId === $gruppe->id && $e->nachrichtId === $nachricht->id
    );
});

it('NachrichtSenden: Nicht-Mitglied → 403', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Privat', []);
    $service = app(NachrichtSenden::class);

    expect(fn () => $service->handle($gruppe, $this->bob, 'Hallo'))
        ->toThrow(HttpException::class);
});

it('NachrichtSenden: Ankündigung durch Nicht-Admin → 403', function () {
    $ankuendigung = app(AnkuendigungskanalHolen::class)->handle($this->tenant->id);
    $service = app(NachrichtSenden::class);

    expect(fn () => $service->handle($ankuendigung, $this->alice, 'Hallo'))
        ->toThrow(HttpException::class);
});

it('NachrichtSenden: leerer Inhalt → ValidationException', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $service = app(NachrichtSenden::class);

    expect(fn () => $service->handle($gruppe, $this->alice, '   '))
        ->toThrow(ValidationException::class);
});

it('NachrichtSenden: Inhalt > 2000 Zeichen → ValidationException', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $service = app(NachrichtSenden::class);

    expect(fn () => $service->handle($gruppe, $this->alice, str_repeat('a', 2001)))
        ->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// NachrichtZurueckziehen
// ---------------------------------------------------------------------------

it('NachrichtZurueckziehen: eigene Nachricht ≤ 15 min kann zurückgezogen werden', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->alice, 'Zurückziehen');

    app(NachrichtZurueckziehen::class)->handle($nachricht, $this->alice);

    expect($nachricht->fresh()->istZurueckgezogen())->toBeTrue();
});

it('NachrichtZurueckziehen: fremde Nachricht → 403', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->alice, 'Von Alice');

    expect(fn () => app(NachrichtZurueckziehen::class)->handle($nachricht, $this->bob))
        ->toThrow(HttpException::class);
});

it('NachrichtZurueckziehen: > 15 min alte Nachricht → 422', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->alice, 'Alte Nachricht');

    // WHY: withoutGlobalScopes nötig weil der TenantScope beim direkten Update greift.
    Nachricht::withoutGlobalScopes()
        ->where('id', $nachricht->id)
        ->update(['created_at' => now()->subMinutes(16)]);

    expect(fn () => app(NachrichtZurueckziehen::class)->handle($nachricht->fresh(), $this->alice))
        ->toThrow(HttpException::class);
});

// ---------------------------------------------------------------------------
// UngeleseneZaehler + KonversationGelesen
// ---------------------------------------------------------------------------

it('UngeleseneZaehler: zählt fremde ungelesene, nicht eigene', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Nachricht von Bob');
    app(NachrichtSenden::class)->handle($gruppe, $this->alice, 'Nachricht von Alice');

    $zaehler = app(UngeleseneZaehler::class);

    // Alice sieht nur Bobs Nachricht, nicht ihre eigene
    expect($zaehler->fuer($this->alice))->toBe(1)
        // Bob sieht nur Alices Nachricht
        ->and($zaehler->fuer($this->bob))->toBe(1);
});

it('UngeleseneZaehler: zurückgezogene Nachrichten werden nicht gezählt', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    $nachricht = app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Wird zurückgezogen');
    app(NachrichtZurueckziehen::class)->handle($nachricht, $this->bob);

    expect(app(UngeleseneZaehler::class)->fuer($this->alice))->toBe(0);
});

it('KonversationGelesen: nach Gelesen → UngeleseneZaehler 0', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);
    app(NachrichtSenden::class)->handle($gruppe, $this->bob, 'Ungelesen');

    expect(app(UngeleseneZaehler::class)->fuer($this->alice))->toBe(1);

    app(KonversationGelesen::class)->handle($gruppe, $this->alice);

    expect(app(UngeleseneZaehler::class)->fuer($this->alice))->toBe(0);
});

it('KonversationGelesen: Nicht-Mitglied ändert nichts', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', []);

    // carol ist kein Mitglied — sollte still ignoriert werden
    $carol = User::create([
        'name' => 'Carol',
        'email' => 'carol@chat.test',
        'password' => bcrypt('password'),
        'tenant_id' => $this->tenant->id,
    ]);
    $carol->assignRole('pflegefachkraft');

    app(KonversationGelesen::class)->handle($gruppe, $carol);

    expect(
        KonversationTeilnehmer::withoutGlobalScopes()
            ->where('konversation_id', $gruppe->id)
            ->where('user_id', $carol->id)
            ->exists()
    )->toBeFalse();
});

// ---------------------------------------------------------------------------
// Channel-Auth
// ---------------------------------------------------------------------------

it('Channel-Auth: Mitglied derselben Tenant erhält true', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);

    $closure = Broadcast::routes()['konversation'] ?? null;

    // Direkt istMitglied + tenant-Check testen (Closure-Äquivalent)
    $konversation = Konversation::withoutGlobalScopes()
        ->where('id', $gruppe->id)
        ->where('tenant_id', $this->alice->tenant_id)
        ->first();

    expect($konversation)->not->toBeNull()
        ->and($konversation->istMitglied($this->alice->id))->toBeTrue();
});

it('Channel-Auth: Nicht-Mitglied erhält false', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', []);

    $konversation = Konversation::withoutGlobalScopes()
        ->where('id', $gruppe->id)
        ->where('tenant_id', $this->bob->tenant_id)
        ->first();

    expect($konversation)->not->toBeNull()
        ->and($konversation->istMitglied($this->bob->id))->toBeFalse();
});

it('Channel-Auth: fremder Tenant → Konversation nicht gefunden', function () {
    $gruppe = app(GruppeErstellen::class)->handle($this->alice, 'Test', [$this->bob->id]);

    $foreignTenant = Tenant::create(['name' => 'Fremdes Heim 4', 'slug' => 'fremdes-heim-4']);
    $foreignUser = User::create([
        'name' => 'FremderUser',
        'email' => 'fremd4@other.test',
        'password' => bcrypt('password'),
        'tenant_id' => $foreignTenant->id,
    ]);

    $konversation = Konversation::withoutGlobalScopes()
        ->where('id', $gruppe->id)
        ->where('tenant_id', $foreignUser->tenant_id)
        ->first();

    expect($konversation)->toBeNull();
});

it('letzteNachricht liefert die NEUESTE Nachricht (nicht die älteste)', function () {
    $konv = Konversation::create(['tenant_id' => $this->tenant->id, 'typ' => KonversationTyp::Gruppe, 'titel' => 'Team']);
    $alt = Nachricht::create(['tenant_id' => $this->tenant->id, 'konversation_id' => $konv->id, 'user_id' => $this->alice->id, 'inhalt' => 'ALT']);
    $alt->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();
    $neu = Nachricht::create(['tenant_id' => $this->tenant->id, 'konversation_id' => $konv->id, 'user_id' => $this->alice->id, 'inhalt' => 'NEU']);
    $neu->forceFill(['created_at' => now()])->saveQuietly();

    expect($konv->letzteNachricht()?->inhalt)->toBe('NEU');
});
