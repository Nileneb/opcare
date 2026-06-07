<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\AbstimmungOption;
use App\Domains\Voting\Models\Stimme;
use App\Domains\Voting\Models\Wahlteilnahme;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'VotingTenant', 'slug' => 'voting-tenant']);
    app(CurrentTenant::class)->set($this->tenant);
});

// ─── Schema-Anonymitäts-Tests (kritisch für DSGVO ErwG 26) ─────────────────

it('stimmen-Tabelle hat KEIN created_at und KEIN updated_at', function () {
    expect(Schema::hasColumn('stimmen', 'created_at'))->toBeFalse()
        ->and(Schema::hasColumn('stimmen', 'updated_at'))->toBeFalse();
});

it('Stimme hat UUID-PK (kein Auto-Increment)', function () {
    expect((new Stimme)->incrementing)->toBeFalse()
        ->and((new Stimme)->getKeyType())->toBe('string')
        ->and((new Stimme)->timestamps)->toBeFalse();
});

it('Stimme bekommt UUID als id und trägt KEINE Timestamps', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Anonym-Test',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
    ]);

    $stimme = Stimme::create([
        'abstimmung_id' => $abstimmung->id,
        'beleg_token' => bin2hex(random_bytes(16)),
    ]);

    expect($stimme->id)->toBeString()
        ->and(Str::isUuid($stimme->id))->toBeTrue()
        ->and($stimme->created_at)->toBeNull()
        ->and($stimme->updated_at)->toBeNull();
});

// ─── Abstimmung + Optionen + Enum-Casts ─────────────────────────────────────

it('Abstimmung legt sich mit zwei Optionen an und Enum-Casts stimmen', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Wohin der Ausflug?',
        'beschreibung' => 'Bitte abstimmen.',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
    ]);

    AbstimmungOption::create(['abstimmung_id' => $abstimmung->id, 'text' => 'Zoo', 'sortierung' => 0]);
    AbstimmungOption::create(['abstimmung_id' => $abstimmung->id, 'text' => 'Theater', 'sortierung' => 1]);

    $abstimmung->refresh();

    expect($abstimmung->elektorat)->toBe(Elektorat::Bewohner)
        ->and($abstimmung->modus)->toBe(Stimmodus::Geheim)
        ->and($abstimmung->art)->toBe(Abstimmungsart::Umfrage)
        ->and($abstimmung->status)->toBe(AbstimmungStatus::Offen)
        ->and($abstimmung->optionen)->toHaveCount(2)
        ->and($abstimmung->optionen->first()->text)->toBe('Zoo');
});

// ─── offen()-Helper ─────────────────────────────────────────────────────────

it('offen() gibt true zurück wenn status=Offen und kein ende_am', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Ohne Frist',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Beschluss,
        'status' => AbstimmungStatus::Offen,
    ]);

    expect($abstimmung->offen())->toBeTrue();
});

it('offen() gibt false zurück wenn status=Geschlossen', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Abgeschlossen',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Geschlossen,
    ]);

    expect($abstimmung->offen())->toBeFalse();
});

it('offen() gibt false zurück wenn ende_am in der Vergangenheit liegt', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Abgelaufen',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
        'status' => AbstimmungStatus::Offen,
        'ende_am' => now()->subDay(),
    ]);

    expect($abstimmung->offen())->toBeFalse();
});

// ─── Wahlteilnahme Unique-Constraint (one-person-one-vote) ───────────────────

it('Wahlteilnahme-Unique verhindert Doppelabstimmung für denselben User', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'MAV-Wahl',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
    ]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    Wahlteilnahme::create([
        'abstimmung_id' => $abstimmung->id,
        'user_id' => $user->id,
        'hat_abgestimmt' => false,
    ]);

    expect(fn () => Wahlteilnahme::create([
        'abstimmung_id' => $abstimmung->id,
        'user_id' => $user->id,
        'hat_abgestimmt' => true,
    ]))->toThrow(QueryException::class);
});

// ─── Geheime Stimme ohne Personen-FK ────────────────────────────────────────

it('geheime Stimme ist ohne waehler_* anlegbar', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Geheimwahl',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Wahl,
    ]);

    $stimme = Stimme::create([
        'abstimmung_id' => $abstimmung->id,
        'beleg_token' => bin2hex(random_bytes(16)),
    ]);

    expect($stimme->waehler_user_id)->toBeNull()
        ->and($stimme->waehler_resident_id)->toBeNull()
        ->and(Str::isUuid($stimme->id))->toBeTrue();
});

it('namentliche Stimme trägt die Person', function () {
    $abstimmung = Abstimmung::create([
        'titel' => 'Namentliche Abstimmung',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Namentlich,
        'art' => Abstimmungsart::Beschluss,
    ]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $stimme = Stimme::create([
        'abstimmung_id' => $abstimmung->id,
        'beleg_token' => bin2hex(random_bytes(16)),
        'waehler_user_id' => $user->id,
    ]);

    expect($stimme->waehler_user_id)->toBe($user->id);
});

// ─── Tenant-Scoping ─────────────────────────────────────────────────────────

it('Abstimmungen eines anderen Tenants sind nicht sichtbar', function () {
    $andererTenant = Tenant::create(['name' => 'Fremder', 'slug' => 'fremder']);

    Abstimmung::create([
        'tenant_id' => $andererTenant->id,
        'titel' => 'Fremdabstimmung',
        'elektorat' => Elektorat::Bewohner,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Umfrage,
    ]);

    // Tenant-Scope des aktuellen Tenants greift — fremde Abstimmung unsichtbar
    expect(Abstimmung::count())->toBe(0);
});
