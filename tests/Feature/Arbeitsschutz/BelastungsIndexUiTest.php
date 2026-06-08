<?php

use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Models\BelastungsKonfig;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Livewire\Arbeitsschutz\Gefaehrdungsbeurteilung as GbuComponent;
use App\Livewire\Scheduling\Arbeitsrecht;
use App\Livewire\Scheduling\Dienstplan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super-admin');
    Role::findOrCreate('leserecht');
    Role::findOrCreate('pflegefachkraft');

    $this->tenant = Tenant::create(['name' => 'UI-Test-Heim', 'slug' => 'ui-belastung-test']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('admin');

    $building = Building::create(['name' => 'UI-Testgebäude']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $this->station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich UI-Alpha']);

    // Belastungskonfig mit niedrigen Schwellen → schnell meldepflichtige Stufe erreichbar
    BelastungsKonfig::firstOrCreate(
        ['tenant_id' => $this->tenant->id],
        [
            'gewicht_pflegelast' => 40,
            'gewicht_deckung' => 35,
            'gewicht_spitzenzeit' => 15,
            'gewicht_ergonomie' => 10,
            'schwelle_hoch' => 30,
            'schwelle_kritisch' => 70,
        ],
    );

    // Erzeuge belegte Station mit hoher Pflegelast → meldepflichtige Stufe sicher
    for ($i = 0; $i < 4; $i++) {
        $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'UT'.$i, 'betten' => 1]);
        $resident = Resident::create([
            'room_id' => $room->id,
            'name' => 'UI Bewohner '.$i,
            'geburtsdatum' => now()->subYears(82)->format('Y-m-d'),
            'geschlecht' => 'w',
            'pflegegrad' => 5,
            'aufnahme_am' => now()->format('Y-m-d'),
            'status' => 'aktiv',
        ]);
        $sis = SisAssessment::create([
            'resident_id' => $resident->id,
            'created_by' => $this->admin->id,
            'erstellt_am' => now()->format('Y-m-d'),
            'status' => 'aktiv',
        ]);
        for ($j = 0; $j < 5; $j++) {
            RiskItem::create([
                'sis_assessment_id' => $sis->id,
                'risiko' => RiskType::Dekubitus,
                'eingeschaetzt' => true,
            ]);
        }
    }

    $this->gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege Wohnbereich UI-Alpha',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);
});

// ---------------------------------------------------------------------------
// Dienstplan: Belastungs-Panel sichtbar
// ---------------------------------------------------------------------------

it('Dienstplan zeigt das Belastungs-Panel mit der belasteten Station', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dienstplan::class)
        ->assertSee('Belastungs-Index')
        ->assertSee('Wohnbereich UI-Alpha')
        ->assertSee('§ 5 Abs. 3 Nr. 6');
});

it('Dienstplan übergibt $belastung-Collection an die View', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dienstplan::class)
        ->assertViewHas('belastung', fn ($col) => $col->isNotEmpty());
});

// ---------------------------------------------------------------------------
// Dienstplan: leitungMelden
// ---------------------------------------------------------------------------

it('leitungMelden erzeugt eine Belastungsmeldung', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dienstplan::class)
        ->call('leitungMelden', $this->station->id);

    expect(Belastungsmeldung::where('station_id', $this->station->id)->count())->toBe(1);
});

it('leitungMelden: zweiter Aufruf erzeugt keine zweite Meldung (Dedupe)', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dienstplan::class)
        ->call('leitungMelden', $this->station->id)
        ->call('leitungMelden', $this->station->id);

    expect(Belastungsmeldung::where('station_id', $this->station->id)->count())->toBe(1);
});

it('leitungMelden: Dienstplan ist für User ohne manage-Berechtigung generell verboten', function () {
    $ohneRolle = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $ohneRolle->assignRole('leserecht');
    $this->actingAs($ohneRolle);

    Livewire::test(Dienstplan::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Dienstplan: entlasten / entlastenSpeichern
// ---------------------------------------------------------------------------

it('entlasten öffnet den Dialog-State für die Station', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dienstplan::class)
        ->call('entlasten', $this->station->id)
        ->assertSet('entlastenStation', $this->station->id);
});

it('entlastenSpeichern erzeugt Schutzmassnahme und verknüpft sie mit offener Meldung', function () {
    // Erst melden, dann entlasten
    $this->actingAs($this->admin);

    $component = Livewire::test(Dienstplan::class)
        ->call('leitungMelden', $this->station->id)
        ->call('entlasten', $this->station->id)
        ->set('entlastenGbuId', $this->gbu->id)
        ->set('entlastenBeschreibung', 'Dienstplan anpassen, Springer einsetzen')
        ->set('entlastenFrist', today()->addDays(14)->toDateString())
        ->call('entlastenSpeichern')
        ->assertHasNoErrors()
        ->assertSet('entlastenStation', null);

    $meldung = Belastungsmeldung::where('station_id', $this->station->id)->firstOrFail();
    expect($meldung->schutzmassnahme_id)->not->toBeNull();
});

it('entlastenSpeichern: Dienstplan ist für User ohne manage-Berechtigung generell verboten', function () {
    $ohneRolle = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $ohneRolle->assignRole('leserecht');
    $this->actingAs($ohneRolle);

    Livewire::test(Dienstplan::class)->assertForbidden();
});

it('entlastenSpeichern: IDOR — fremde GBU wird abgelehnt', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremder UI', 'slug' => 'fremder-ui']);
    app(CurrentTenant::class)->set($fremderTenant);
    $fremdeGbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Fremde GBU',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $this->actingAs($this->admin);

    // Meldung anlegen, dann fremde GBU verwenden
    Livewire::test(Dienstplan::class)
        ->call('leitungMelden', $this->station->id);

    Livewire::test(Dienstplan::class)
        ->set('entlastenGbuId', $fremdeGbu->id)
        ->set('entlastenBeschreibung', 'IDOR-Angriff auf fremde GBU')
        ->call('entlastenSpeichern')
        ->assertHasErrors('entlastenGbuId');
});

// ---------------------------------------------------------------------------
// GBU-Screen: Belastungsmeldungen
// ---------------------------------------------------------------------------

it('GBU-Screen listet offene Belastungsmeldung', function () {
    Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich UI-Alpha',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 68,
        'signale' => ['Pflegelast' => 'Score 80 (4 Risiken)'],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuComponent::class)
        ->assertSee('Belastungsmeldungen')
        ->assertSee('Wohnbereich UI-Alpha')
        ->assertSee('Hoch');
});

it('meldungQuittieren entfernt die Meldung aus der offenen Liste', function () {
    $meldung = Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich UI-Alpha',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 68,
        'signale' => ['Pflegelast' => 'Score 80'],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(GbuComponent::class)
        ->call('meldungQuittieren', $meldung->id)
        ->assertViewHas('belastungsmeldungen', fn ($col) => $col->isEmpty());

    $meldung->refresh();
    expect($meldung->quittiert_am)->not->toBeNull()
        ->and($meldung->quittiert_von)->toBe($this->admin->id);
});

it('meldungQuittieren: 403 für User ohne Rolle', function () {
    $ohneRolle = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($ohneRolle);

    // GbuComponent mount() prüft dieselbe Gate-Bedingung wie meldungQuittieren — assertForbidden
    Livewire::test(GbuComponent::class)->assertForbidden();
});

it('meldungQuittieren: IDOR — fremde Meldung wird tenant-scoped abgelehnt', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremd-UI', 'slug' => 'fremd-ui']);
    app(CurrentTenant::class)->set($fremderTenant);
    $fremdeMeldung = Belastungsmeldung::create([
        'station_id' => null,
        'wohnbereich' => 'Fremder Bereich',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 65,
        'signale' => [],
        'gemeldet_am' => today()->toDateString(),
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $this->actingAs($this->admin);

    expect(fn () => Livewire::test(GbuComponent::class)
        ->call('meldungQuittieren', $fremdeMeldung->id))
        ->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------------
// Arbeitsrecht: Config-Editor
// ---------------------------------------------------------------------------

it('Arbeitsrecht zeigt den Belastungsindex-Config-Editor', function () {
    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->assertSee('Belastungs-Index')
        ->assertSee('Gewichte')
        ->assertSee('Schwellen');
});

it('belastungsKonfigSpeichern speichert geänderte Gewichte', function () {
    $this->actingAs($this->admin);

    Livewire::test(Arbeitsrecht::class)
        ->set('bk_gewicht_pflegelast', 50)
        ->set('bk_gewicht_deckung', 30)
        ->set('bk_gewicht_spitzenzeit', 12)
        ->set('bk_gewicht_ergonomie', 8)
        ->set('bk_schwelle_hoch', 55)
        ->set('bk_schwelle_kritisch', 75)
        ->call('belastungsKonfigSpeichern')
        ->assertHasNoErrors();

    $konfig = BelastungsKonfig::where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($konfig->gewicht_pflegelast)->toBe(50)
        ->and($konfig->schwelle_hoch)->toBe(55)
        ->and($konfig->schwelle_kritisch)->toBe(75);
});

it('belastungsKonfigSpeichern: Arbeitsrecht-Seite ist für User ohne manage-Berechtigung generell verboten', function () {
    $ohneRolle = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $ohneRolle->assignRole('leserecht');
    $this->actingAs($ohneRolle);

    Livewire::test(Arbeitsrecht::class)->assertForbidden();
});
