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

it('Online-Wahl gesperrt wenn voting.online_wahl_aktiv = false', function () {
    config(['voting.online_wahl_aktiv' => false]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $abstimmung = erstelleAbstimmung([
        'titel' => 'Heimbeiratswahl gesperrt',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
        'status' => AbstimmungStatus::Offen,
    ]);

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
