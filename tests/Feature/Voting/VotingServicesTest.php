<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\GremiumFunktion;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Domains\Quality\Enums\MitgliedArt;
use App\Domains\Quality\Models\Gremium;
use App\Domains\Quality\Models\GremiumMitglied;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\Stimme;
use App\Domains\Voting\Models\Wahlteilnahme;
use App\Domains\Voting\Services\AbstimmungStarten;
use App\Domains\Voting\Services\Auszaehlung;
use App\Domains\Voting\Services\StimmeAbgeben;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'VotingServices', 'slug' => 'voting-services']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->starten = app(AbstimmungStarten::class);
    $this->abstimmen = app(StimmeAbgeben::class);
    $this->auszaehlung = app(Auszaehlung::class);
});

// ─── Hilfs-Funktion für Abstimmung + Optionen ───────────────────────────────

function erstelleAbstimmung(array $daten, array $optionTexte = ['Ja', 'Nein']): Abstimmung
{
    $starten = app(AbstimmungStarten::class);
    $optionen = array_map(fn ($text, $i) => ['text' => $text, 'sortierung' => $i], $optionTexte, array_keys($optionTexte));

    return $starten->handle($daten, $optionen);
}

// ─── eroeffne legt Wahlteilnahmen an ────────────────────────────────────────

it('eroeffne legt Wahlteilnahmen für alle aktiven Bewohner an', function () {
    $resident1 = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);
    $resident2 = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);
    $inaktiv = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'inaktiv']);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Ausflugsziel',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBe(2);

    $ids = Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->pluck('resident_id')->all();
    expect($ids)->toContain($resident1->id)
        ->and($ids)->toContain($resident2->id)
        ->and($ids)->not->toContain($inaktiv->id);
});

it('eroeffne ist idempotent — zweimaliges Aufrufen verdoppelt keine Einträge', function () {
    Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Idempotenz-Test',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Entwurf,
    ]);

    $this->starten->eroeffne($abstimmung);
    $this->starten->eroeffne($abstimmung);

    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBe(1);
});

// ─── StimmeAbgeben: geheime Umfrage ─────────────────────────────────────────

it('geheime Umfrage: Stimme ohne waehler_*, hat_abgestimmt true, Token zurück', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Geheime Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();

    $token = $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    expect($token)->toBeString()->toHaveLength(32);

    $stimme = Stimme::where('abstimmung_id', $abstimmung->id)->first();
    expect($stimme->waehler_user_id)->toBeNull()
        ->and($stimme->waehler_resident_id)->toBeNull()
        ->and($stimme->beleg_token)->toBe($token);

    $teilnahme = Wahlteilnahme::where('abstimmung_id', $abstimmung->id)
        ->where('user_id', $user->id)->first();
    expect($teilnahme->hat_abgestimmt)->toBeTrue();
});

it('Doppelabgabe wirft Exception', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Doppelabgabe',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();

    $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    expect(fn () => $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]))
        ->toThrow(InvalidArgumentException::class, 'bereits abgestimmt');
});

it('nicht stimmberechtigter Wähler wirft Exception', function () {
    $abstimmung = erstelleAbstimmung([
        'titel' => 'Fremder Wähler',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();
    $fremderId = 99999;

    expect(fn () => $this->abstimmen->handle($abstimmung, 'user', $fremderId, [$option->id]))
        ->toThrow(InvalidArgumentException::class, 'stimmberechtigt');
});

// ─── Geheim-Erzwingung (HeimbwV §5, MVG-EKD §11) ───────────────────────────

it('Heimbeirat-Wahl mit Modus Namentlich wirft Geheim-Erzwingung', function () {
    expect(fn () => erstelleAbstimmung([
        'titel' => 'Heimbeiratswahl',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Wahl,
    ]))->toThrow(InvalidArgumentException::class, 'geheim sein');
});

it('MAV-Wahl (Mitarbeitende) mit Modus Namentlich wirft Geheim-Erzwingung', function () {
    expect(fn () => erstelleAbstimmung([
        'titel' => 'MAV-Wahl',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Wahl,
    ]))->toThrow(InvalidArgumentException::class, 'geheim sein');
});

it('Gremiums-Wahl mit Namentlich wirft NICHT (nur Bewohner/Mitarbeitende betroffen)', function () {
    $gremium = Gremium::create([
        'tenant_id' => $this->tenant->id,
        'typ' => GremiumTyp::Heimbeirat,
        'name' => 'Testgremium',
    ]);

    expect(fn () => erstelleAbstimmung([
        'titel' => 'Gremiums-Beschluss',
        'elektorat' => Elektorat::Gremium,
        'gremium_id' => $gremium->id,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Wahl,
    ]))->not->toThrow(InvalidArgumentException::class);
});

// ─── Namentliche Umfrage ─────────────────────────────────────────────────────

it('namentliche Stimme trägt waehler_user_id', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Namentliche Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();
    $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    $stimme = Stimme::where('abstimmung_id', $abstimmung->id)->first();
    expect($stimme->waehler_user_id)->toBe($user->id);
});

// ─── Online-Wahl-Sperre ──────────────────────────────────────────────────────

it('StimmeAbgeben: Online-Wahl gesperrt wenn voting.online_wahl_aktiv = false', function () {
    // Abstimmung im Entwurf anlegen (kein eroeffne-Aufruf, da Sperre greift dort ebenfalls).
    // Direkt öffnen + Wahlteilnahme manuell anlegen — testet die zweite Verteidigungslinie in StimmeAbgeben.
    config(['voting.online_wahl_aktiv' => true]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Heimbeiratswahl gesperrt',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Offen,
    ]);

    // Sperre nachträglich aktivieren — testet, dass auch StimmeAbgeben prüft
    config(['voting.online_wahl_aktiv' => false]);

    $option = $abstimmung->optionen->first();

    expect(fn () => $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]))
        ->toThrow(InvalidArgumentException::class, 'Online-Wahl nicht freigegeben');
});

it('Online-Wahl möglich wenn voting.online_wahl_aktiv = true', function () {
    config(['voting.online_wahl_aktiv' => true]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Heimbeiratswahl freigegeben',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();
    $token = $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    expect($token)->toBeString()->toHaveLength(32);
});

// ─── Auszählung ──────────────────────────────────────────────────────────────

it('Auszählung liefert korrekte Stimmenzahl je Option und Beteiligung', function () {
    $user1 = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user3 = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Auszählung-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ], ['Ja', 'Nein', 'Enthaltung']);

    $optionen = $abstimmung->optionen;
    $ja = $optionen->firstWhere('text', 'Ja');
    $nein = $optionen->firstWhere('text', 'Nein');

    $this->abstimmen->handle($abstimmung, 'user', $user1->id, [$ja->id]);
    $this->abstimmen->handle($abstimmung, 'user', $user2->id, [$ja->id]);
    $this->abstimmen->handle($abstimmung, 'user', $user3->id, [$nein->id]);

    $ergebnis = $this->auszaehlung->ergebnis($abstimmung);

    expect($ergebnis['optionen'][$ja->id]['stimmen'])->toBe(2)
        ->and($ergebnis['optionen'][$nein->id]['stimmen'])->toBe(1)
        ->and($ergebnis['beteiligung']['berechtigt'])->toBe(3)
        ->and($ergebnis['beteiligung']['abgestimmt'])->toBe(3)
        ->and($ergebnis['namentlich'])->toBeNull();
});

it('namentliche Auszählung enthält Wähler-Namen je Option', function () {
    $user1 = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Anna Müller']);
    $user2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bob Schmidt']);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Namentliche Auszählung',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Beschluss,
        'status' => AbstimmungStatus::Offen,
    ]);

    $optionen = $abstimmung->optionen;
    $ja = $optionen->firstWhere('text', 'Ja');
    $nein = $optionen->firstWhere('text', 'Nein');

    $this->abstimmen->handle($abstimmung, 'user', $user1->id, [$ja->id]);
    $this->abstimmen->handle($abstimmung, 'user', $user2->id, [$nein->id]);

    $ergebnis = $this->auszaehlung->ergebnis($abstimmung);

    expect($ergebnis['namentlich'])->not->toBeNull()
        ->and($ergebnis['namentlich'][$ja->id])->toContain('Anna Müller')
        ->and($ergebnis['namentlich'][$nein->id])->toContain('Bob Schmidt');
});

// ─── Anonymität: keine Person→Stimme-Verknüpfung bei Geheimwahl ─────────────

it('geheime Stimme lässt sich NICHT von der Person zur Stimme joinen', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Anonymitäts-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();
    $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    // Stimme wurde abgegeben, aber kein FK auf die Person gesetzt
    expect(Stimme::where('waehler_user_id', $user->id)->count())->toBe(0);
    expect(Stimme::where('abstimmung_id', $abstimmung->id)->count())->toBe(1);
});

// ─── Gremiums-Wahlteilnahmen ─────────────────────────────────────────────────

it('eroeffne legt Wahlteilnahmen für alle Gremiumsmitglieder an', function () {
    $gremium = Gremium::create([
        'tenant_id' => $this->tenant->id,
        'typ' => GremiumTyp::Heimbeirat,
        'name' => 'Heimbeirat',
    ]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $resident = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);

    GremiumMitglied::create([
        'tenant_id' => $this->tenant->id,
        'gremium_id' => $gremium->id,
        'name' => 'User-Mitglied',
        'art' => MitgliedArt::Mitarbeiter,
        'funktion' => GremiumFunktion::Mitglied,
        'user_id' => $user->id,
        'resident_id' => null,
    ]);

    GremiumMitglied::create([
        'tenant_id' => $this->tenant->id,
        'gremium_id' => $gremium->id,
        'name' => 'Bewohner-Mitglied',
        'art' => MitgliedArt::Bewohner,
        'funktion' => GremiumFunktion::Vorsitz,
        'user_id' => null,
        'resident_id' => $resident->id,
    ]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Gremiumsbeschluss',
        'elektorat' => Elektorat::Gremium,
        'gremium_id' => $gremium->id,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Beschluss,
        'status' => AbstimmungStatus::Offen,
    ]);

    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBe(2);
});

// ─── B1: Wahlteilnahme hat KEINE Timestamp-Spalten ──────────────────────────

it('wahlteilnahmen-Tabelle hat keine created_at/updated_at-Spalten (Anonymität)', function () {
    expect(Schema::hasColumn('wahlteilnahmen', 'updated_at'))->toBeFalse();
    expect(Schema::hasColumn('wahlteilnahmen', 'created_at'))->toBeFalse();
});

it('nach Stimmabgabe keine Zeitspur in wahlteilnahmen (kein updated_at)', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Timestamp-Anonymitaet',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $option = $abstimmung->optionen->first();
    $this->abstimmen->handle($abstimmung, 'user', $user->id, [$option->id]);

    $teilnahme = Wahlteilnahme::where('abstimmung_id', $abstimmung->id)
        ->where('user_id', $user->id)->first();

    expect($teilnahme->hat_abgestimmt)->toBeTrue();
    // Kein Zeitstempel-Feld vorhanden — isset schlägt fehl, offsetExists liefert false
    expect(isset($teilnahme->updated_at))->toBeFalse();
    expect(isset($teilnahme->created_at))->toBeFalse();
});

// ─── B4: Mehrfachauswahl mit gemeinsamem beleg_token ────────────────────────

it('Mehrfachauswahl erzeugt zwei Stimmen mit identischem beleg_token (kein Unique-Fehler)', function () {
    config(['voting.online_wahl_aktiv' => true]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Mehrfachauswahl-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'mehrfachauswahl' => true,
        'status' => AbstimmungStatus::Offen,
    ], ['Option A', 'Option B', 'Option C']);

    $optionen = $abstimmung->optionen;
    $optA = $optionen->firstWhere('text', 'Option A');
    $optB = $optionen->firstWhere('text', 'Option B');

    $token = $this->abstimmen->handle($abstimmung, 'user', $user->id, [$optA->id, $optB->id]);

    $stimmen = Stimme::where('abstimmung_id', $abstimmung->id)->get();
    expect($stimmen)->toHaveCount(2);
    expect($stimmen->pluck('beleg_token')->unique()->count())->toBe(1);
    expect($stimmen->first()->beleg_token)->toBe($token);
});

// ─── B3: Online-Wahl-Sperre bei eroeffne ────────────────────────────────────

it('eroeffne einer Wahl bei online_wahl_aktiv=false wirft Exception und legt KEINE Wahlteilnahmen an', function () {
    config(['voting.online_wahl_aktiv' => false]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Gesperrte Wahl',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Entwurf,
    ]);

    // Noch keine Wahlteilnahmen (Entwurf)
    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBe(0);

    expect(fn () => $this->starten->eroeffne($abstimmung))
        ->toThrow(InvalidArgumentException::class, 'Eröffnung blockiert');

    // Keine Wahlteilnahmen angelegt trotz Exception
    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBe(0);
});

it('eroeffne einer Wahl bei online_wahl_aktiv=true legt Wahlteilnahmen an', function () {
    config(['voting.online_wahl_aktiv' => true]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Freigegebene Wahl',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Entwurf,
    ]);

    $this->starten->eroeffne($abstimmung);

    expect(Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->count())->toBeGreaterThan(0);
});

// ─── GeheimKrypto: gebaut & stillgelegt (Inbetriebnahme-Schalter) ────────────

it('Stimmodus::GeheimKrypto hat label, istGeheim und istKrypto', function () {
    expect(Stimmodus::GeheimKrypto->label())->toBe('Geheim (krypto-unverkettbar)')
        ->and(Stimmodus::GeheimKrypto->istGeheim())->toBeTrue()
        ->and(Stimmodus::GeheimKrypto->istKrypto())->toBeTrue()
        ->and(Stimmodus::Geheim->istGeheim())->toBeTrue()
        ->and(Stimmodus::Geheim->istKrypto())->toBeFalse()
        ->and(Stimmodus::Namentlich->istGeheim())->toBeFalse();
});

it('AbstimmungStarten lehnt GeheimKrypto ab solange der Schalter aus ist', function () {
    config()->set('voting.krypto_unverkettbarkeit_aktiv', false);

    expect(fn () => erstelleAbstimmung([
        'titel' => 'Krypto-Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::GeheimKrypto,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Entwurf,
    ]))->toThrow(InvalidArgumentException::class, 'stillgelegt');
});

it('AbstimmungStarten erlaubt GeheimKrypto wenn der Schalter an ist', function () {
    config()->set('voting.krypto_unverkettbarkeit_aktiv', true);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Krypto-Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::GeheimKrypto,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Entwurf,
    ]);

    expect($abstimmung->modus)->toBe(Stimmodus::GeheimKrypto);
});

it('GeheimKrypto erfüllt die Geheim-Pflicht gesetzlicher Wahlen (kein Geheim-Zwang-Fehler)', function () {
    config()->set('voting.krypto_unverkettbarkeit_aktiv', true);
    config()->set('voting.online_wahl_aktiv', true);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Heimbeiratswahl krypto',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::GeheimKrypto,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Entwurf,
    ]);

    expect($abstimmung->modus)->toBe(Stimmodus::GeheimKrypto);
});

it('StimmeAbgeben blockiert eine in GeheimKrypto eröffnete Abstimmung wenn der Schalter wieder aus ist', function () {
    config()->set('voting.krypto_unverkettbarkeit_aktiv', true);
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Krypto-Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::GeheimKrypto,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $optionId = $abstimmung->optionen()->first()->id;

    // Schalter zurück auf aus → Abgabe muss an der Defense-in-depth-Sperre scheitern
    config()->set('voting.krypto_unverkettbarkeit_aktiv', false);

    expect(fn () => $this->abstimmen->handle($abstimmung, 'user', $user->id, [$optionId]))
        ->toThrow(InvalidArgumentException::class, 'stillgelegt');
});

it('GeheimKrypto-Stimme trägt keinen Personenbezug (anonym wie Geheim)', function () {
    config()->set('voting.krypto_unverkettbarkeit_aktiv', true);
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Krypto-Umfrage',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::GeheimKrypto,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    $optionId = $abstimmung->optionen()->first()->id;
    $this->abstimmen->handle($abstimmung, 'user', $user->id, [$optionId]);

    $stimme = Stimme::where('abstimmung_id', $abstimmung->id)->first();
    expect($stimme->waehler_user_id)->toBeNull()
        ->and($stimme->waehler_resident_id)->toBeNull();
});
