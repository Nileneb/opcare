<?php

use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Models\BelastungFreischaltung;
use App\Domains\Arbeitsschutz\Models\BelastungsKonfig;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\PersoenlicheBelastung;
use App\Domains\Arbeitsschutz\Models\SelbstmeldungUeberlastung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Wahlteilnahme;
use App\Domains\Voting\Services\AbstimmungStarten;
use App\Domains\Voting\Services\StimmeAbgeben;
use App\Livewire\Arbeitsschutz\Gefaehrdungsbeurteilung as GbuScreen;
use App\Livewire\Personnel\Energiebarometer;
use App\Livewire\Scheduling\Arbeitsrecht;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    foreach (['admin', 'super-admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft', 'kueche', 'haustechnik', 'buchhaltung'] as $r) {
        Role::findOrCreate($r);
    }

    $this->tenant = Tenant::create(['name' => 'UI-Test-Heim', 'slug' => 'ui-test-heim']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('admin');

    $this->mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->mitarbeiter->assignRole('pflegefachkraft');

    BelastungsKonfig::create([
        'tenant_id' => $this->tenant->id,
        'schwelle_hoch' => 60,
        'schwelle_kritisch' => 80,
        'gewicht_pflegelast' => 1,
        'gewicht_deckung' => 1,
        'gewicht_spitzenzeit' => 1,
        'gewicht_ergonomie' => 1,
    ]);
});

// ─── Hilfs-Funktionen ─────────────────────────────────────────────────────────

function uiTestBeschluss(Tenant $tenant, int $ja, int $nein): array
{
    $starten = app(AbstimmungStarten::class);
    $abstimmung = $starten->handle([
        'tenant_id' => $tenant->id,
        'titel' => 'UI-Test-Beschluss',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Beschluss,
        'status' => AbstimmungStatus::Offen,
    ], [
        ['text' => 'Ja', 'sortierung' => 0],
        ['text' => 'Nein', 'sortierung' => 1],
    ]);

    $jaOpt = $abstimmung->optionen->firstWhere('text', 'Ja');
    $neinOpt = $abstimmung->optionen->firstWhere('text', 'Nein');
    $abstimmen = app(StimmeAbgeben::class);

    for ($i = 0; $i < $ja + $nein; $i++) {
        $u = User::factory()->create(['tenant_id' => $tenant->id, 'email' => "vui{$i}-{$abstimmung->id}@t.de"]);
        Wahlteilnahme::firstOrCreate(
            ['abstimmung_id' => $abstimmung->id, 'user_id' => $u->id, 'resident_id' => null],
            ['tenant_id' => $tenant->id, 'hat_abgestimmt' => false],
        );
        if ($i < $ja) {
            $abstimmen->handle($abstimmung, 'user', $u->id, [$jaOpt->id]);
        } else {
            $abstimmen->handle($abstimmung, 'user', $u->id, [$neinOpt->id]);
        }
    }

    $abstimmung->update(['status' => AbstimmungStatus::Geschlossen]);
    $abstimmung->refresh();

    return ['abstimmung' => $abstimmung, 'ja' => $jaOpt, 'nein' => $neinOpt];
}

function aktiveFreischaltungAnlegen(Tenant $tenant, User $admin): BelastungFreischaltung
{
    $data = uiTestBeschluss($tenant, 3, 1);

    return BelastungFreischaltung::create([
        'tenant_id' => $tenant->id,
        'abstimmung_id' => $data['abstimmung']->id,
        'freigeschaltet_von' => $admin->id,
        'freigeschaltet_am' => today(),
    ]);
}

// ─── Energiebarometer: Selbst-Ampel-Sichtbarkeit ─────────────────────────────

it('Energiebarometer zeigt Hinweis statt Selbst-Ampel wenn keine Freischaltung', function () {
    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->assertSee('Mitarbeitenden-Beschluss freischaltbar');
});

it('Energiebarometer zeigt Selbst-Ampel wenn Freischaltung aktiv', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->assertSee('Meine Belastung')
        ->assertSee('freigeschaltet');
});

it('belastungSetzen speichert eigenen Wert und lädt meineBelastung', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->call('belastungSetzen', 7)
        ->assertHasNoErrors();

    $latest = PersoenlicheBelastung::where('user_id', $this->mitarbeiter->id)->latest('id')->first();
    expect($latest)->not->toBeNull();
    expect($latest->wert)->toBe(7);
});

it('belastungSetzen ohne Freischaltung → 403', function () {
    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->call('belastungSetzen', 5)
        ->assertForbidden();
});

it('ueberlastungMelden erzeugt Meldung', function () {
    Notification::fake();
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    PersoenlicheBelastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 3,
    ]);

    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->call('ueberlastungMelden')
        ->assertHasNoErrors();

    expect(SelbstmeldungUeberlastung::where('user_id', $this->mitarbeiter->id)->exists())->toBeTrue();
});

it('ueberlastungMelden zeigt Dedupe-Flash bei zweiter offener Meldung', function () {
    Notification::fake();
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    SelbstmeldungUeberlastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 4,
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->mitarbeiter);

    Livewire::test(Energiebarometer::class)
        ->call('ueberlastungMelden')
        ->assertSee('Du hast bereits eine offene Meldung.');
});

// ─── Arbeitsrecht: Freischaltungs-Verwaltung ─────────────────────────────────

it('Arbeitsrecht zeigt Freischaltungs-Sektion für admin', function () {
    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->assertSee('Belastungs-Features')
        ->assertSee('inaktiv');
});

it('Arbeitsrecht belastungFreischalten aus angenommenem Beschluss → aktiv', function () {
    $data = uiTestBeschluss($this->tenant, 3, 1);

    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->set('freischaltungBeschlussId', $data['abstimmung']->id)
        ->set('freischaltungOptionId', $data['ja']->id)
        ->call('belastungFreischalten')
        ->assertHasNoErrors();

    expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeTrue();
});

it('Arbeitsrecht belastungFreischalten aus nicht-angenommenem Beschluss → Fehler-Flash', function () {
    $data = uiTestBeschluss($this->tenant, 2, 3);

    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->set('freischaltungBeschlussId', $data['abstimmung']->id)
        ->set('freischaltungOptionId', $data['ja']->id)
        ->call('belastungFreischalten')
        ->assertSee('Freischaltung fehlgeschlagen');

    expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeFalse();
});

it('Arbeitsrecht belastungZuruecknehmen deaktiviert die Freischaltung', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);
    expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeTrue();

    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->call('belastungZuruecknehmen')
        ->assertHasNoErrors();

    expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeFalse();
});

it('Arbeitsrecht zeigt aktiv nach Freischaltung', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->assertSee('aktiv');
});

it('Arbeitsrecht-Gate: kueche-Rolle → 403', function () {
    $kueche = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $kueche->assignRole('kueche');

    $this->actingAs($kueche);

    Livewire::test(Arbeitsrecht::class)
        ->assertForbidden();
});

// ─── GBU: Selbstmeldungen anzeigen + quittieren ───────────────────────────────

it('GBU listet offene Selbst-Überlastungsmeldungen', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    SelbstmeldungUeberlastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 2,
        'notiz' => 'Zu viele Nachtdienste',
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuScreen::class)
        ->assertSee('Selbst-Überlastungsmeldungen')
        ->assertSee($this->mitarbeiter->name)
        ->assertSee('Zu viele Nachtdienste');
});

it('GBU selbstmeldungQuittieren entfernt Meldung aus offener Liste', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    $meldung = SelbstmeldungUeberlastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 3,
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuScreen::class)
        ->call('selbstmeldungQuittieren', $meldung->id)
        ->assertHasNoErrors();

    $meldung->refresh();
    expect($meldung->quittiert_am)->not->toBeNull();
    expect($meldung->quittiert_von)->toBe($this->admin->id);
});

it('GBU selbstmeldungQuittieren: IDOR — fremder Tenant → 404', function () {
    $andererTenant = Tenant::create(['name' => 'Anderes Heim', 'slug' => 'anderes-heim']);

    $fremderUser = User::factory()->create(['tenant_id' => $andererTenant->id]);

    $fremdeMeldung = SelbstmeldungUeberlastung::create([
        'tenant_id' => $andererTenant->id,
        'user_id' => $fremderUser->id,
        'wert' => 5,
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->admin);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(GbuScreen::class)
        ->call('selbstmeldungQuittieren', $fremdeMeldung->id);
});

it('GBU selbstmeldungQuittieren Gate: pflegehilfskraft → 403', function () {
    $hk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $hk->assignRole('pflegehilfskraft');

    $meldung = SelbstmeldungUeberlastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 3,
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($hk);

    Livewire::test(GbuScreen::class)
        ->assertForbidden();
});

// ─── Farbverlauf-Rendering ────────────────────────────────────────────────────

it('GBU-Blade rendert Farbverlauf-Indikator (hsl) für Belastungsmeldung', function () {
    $meldung = Belastungsmeldung::create([
        'tenant_id' => $this->tenant->id,
        'wohnbereich' => 'WB Farbtest',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 70,
        'signale' => [],
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuScreen::class)
        ->assertSee('hsl(', false)
        ->assertSee('WB Farbtest');
});

it('GBU-Blade rendert Farbverlauf-Indikator für Selbst-Überlastungsmeldung', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);

    SelbstmeldungUeberlastung::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->mitarbeiter->id,
        'wert' => 2,
        'gemeldet_am' => today(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuScreen::class)
        ->assertSee('hsl(', false);
});

it('Admin sieht den persönlichen Belastungswert anderer nicht (Datenschutz-Invariante)', function () {
    aktiveFreischaltungAnlegen($this->tenant, $this->admin);
    // Mitarbeiter:in hat einen eigenen Slider-Wert gesetzt
    PersoenlicheBelastung::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->mitarbeiter->id, 'wert' => 9]);

    // Admin öffnet den Self-Care-Screen → lädt NUR den eigenen Wert (null), nie den fremden (9)
    Livewire::actingAs($this->admin)
        ->test(Energiebarometer::class)
        ->assertSet('meineBelastung', null);
});
