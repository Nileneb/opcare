<?php

use App\Domains\Arbeitsschutz\Data\BelastungsBefund;
use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Arbeitsschutz\Models\BelastungsKonfig;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Arbeitsschutz\Notifications\BelastungKritisch;
use App\Domains\Arbeitsschutz\Services\BelastungMelden;
use App\Domains\Arbeitsschutz\Services\BelastungsAnalyzer;
use App\Domains\Arbeitsschutz\Services\EntlastungErgreifen;
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
use App\Domains\Scheduling\Compliance\Data\SpitzenzeitAnalyse;
use App\Domains\Scheduling\Compliance\Data\StaffingAnalysis;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super-admin');

    $this->tenant = Tenant::create(['name' => 'Belastungs-Test-Heim', 'slug' => 'belastung-test']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->admin = User::create([
        'name' => 'Test Admin',
        'email' => 'admin@belastung.test',
        'password' => bcrypt('password'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->admin->assignRole('admin');

    $building = Building::create(['name' => 'Testgebäude']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $this->station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich Alpha']);
});

// ---------------------------------------------------------------------------
// Belastungsstufe Enum
// ---------------------------------------------------------------------------

it('Belastungsstufe ampel: Gering → green', function () {
    expect(Belastungsstufe::Gering->ampel())->toBe('green');
});

it('Belastungsstufe ampel: Erhoeht → green', function () {
    expect(Belastungsstufe::Erhoeht->ampel())->toBe('green');
});

it('Belastungsstufe ampel: Hoch → amber', function () {
    expect(Belastungsstufe::Hoch->ampel())->toBe('amber');
});

it('Belastungsstufe ampel: Kritisch → red', function () {
    expect(Belastungsstufe::Kritisch->ampel())->toBe('red');
});

it('Belastungsstufe rang: Gering=1, Erhoeht=2, Hoch=3, Kritisch=4', function () {
    expect(Belastungsstufe::Gering->rang())->toBe(1)
        ->and(Belastungsstufe::Erhoeht->rang())->toBe(2)
        ->and(Belastungsstufe::Hoch->rang())->toBe(3)
        ->and(Belastungsstufe::Kritisch->rang())->toBe(4);
});

it('Belastungsstufe istMeldepflichtig: nur Hoch und Kritisch', function () {
    expect(Belastungsstufe::Gering->istMeldepflichtig())->toBeFalse()
        ->and(Belastungsstufe::Erhoeht->istMeldepflichtig())->toBeFalse()
        ->and(Belastungsstufe::Hoch->istMeldepflichtig())->toBeTrue()
        ->and(Belastungsstufe::Kritisch->istMeldepflichtig())->toBeTrue();
});

// ---------------------------------------------------------------------------
// BelastungsKonfig
// ---------------------------------------------------------------------------

it('BelastungsKonfig::ensureFor legt mit korrekten Defaults an (keine 0-Gewichte)', function () {
    $konfig = BelastungsKonfig::ensureFor($this->tenant->id);

    expect($konfig->gewicht_pflegelast)->toBe(40)
        ->and($konfig->gewicht_deckung)->toBe(35)
        ->and($konfig->gewicht_spitzenzeit)->toBe(15)
        ->and($konfig->gewicht_ergonomie)->toBe(10)
        ->and($konfig->schwelle_hoch)->toBe(60)
        ->and($konfig->schwelle_kritisch)->toBe(80);
});

it('BelastungsKonfig::ensureFor ist idempotent (zweiter Aufruf liefert dieselbe Zeile)', function () {
    $k1 = BelastungsKonfig::ensureFor($this->tenant->id);
    $k2 = BelastungsKonfig::ensureFor($this->tenant->id);

    expect($k1->id)->toBe($k2->id);
});

// ---------------------------------------------------------------------------
// BelastungsAnalyzer — Hilfsfunktionen
// ---------------------------------------------------------------------------

/**
 * Legt Resident + SisAssessment + N eingeschätzte RiskItems für die übergebene Station an.
 *
 * @return array{resident: Resident, sis: SisAssessment}
 */
function createResidentWithRisks(Station $station, int $tenantId, int $riskCount, int $pflegegrad = 3): array
{
    $room = Room::create(['station_id' => $station->id, 'nummer' => uniqid('Z'), 'betten' => 1]);

    $resident = Resident::create([
        'room_id' => $room->id,
        'name' => 'Test Bewohner '.uniqid(),
        'geburtsdatum' => now()->subYears(80)->format('Y-m-d'),
        'geschlecht' => 'w',
        'pflegegrad' => $pflegegrad,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $adminId = User::where('tenant_id', $tenantId)->first()?->id ?? 1;

    $sis = SisAssessment::create([
        'resident_id' => $resident->id,
        'created_by' => $adminId,
        'erstellt_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    for ($i = 0; $i < $riskCount; $i++) {
        RiskItem::create([
            'sis_assessment_id' => $sis->id,
            'risiko' => RiskType::Dekubitus,
            'eingeschaetzt' => true,
        ]);
    }

    return ['resident' => $resident, 'sis' => $sis];
}

function makeStaffing(int $istGesamt = 100, int $sollGesamt = 100, int $istFachkraft = 100, int $sollFachkraft = 100): StaffingAnalysis
{
    return new StaffingAnalysis(
        pgCounts: [],
        sollVzaeGesamt: 1.0,
        sollVzaeFachkraft: 1.0,
        sollWochenstundenGesamt: (float) $sollGesamt,
        sollWochenstundenFachkraft: (float) $sollFachkraft,
        istWochenstundenGesamt: (float) $istGesamt,
        istWochenstundenFachkraft: (float) $istFachkraft,
    );
}

// ---------------------------------------------------------------------------
// BelastungsAnalyzer
// ---------------------------------------------------------------------------

it('BelastungsAnalyzer: Station mit 5 eingeschätzten RiskItems + niedriger Deckung → hoher Score', function () {
    // 5 RiskItems je Bewohner × 3 Bewohner mit Pflegegrad 5 → pflegelastScore = min(100, 15*12 + 3*8) = min(100, 204) = 100
    // deckungScore = min(100, 50 + 25) = 75; gesamt = round((100*40 + 75*35) / 100) = round(66.25) = 66 → Hoch
    foreach (range(1, 3) as $i) {
        createResidentWithRisks($this->station, $this->tenant->id, riskCount: 5, pflegegrad: 5);
    }

    $staffing = makeStaffing(istGesamt: 50, sollGesamt: 100, istFachkraft: 50, sollFachkraft: 100);

    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    expect($befunde)->toHaveCount(1);

    $befund = $befunde->first();
    expect($befund->score)->toBeGreaterThan(0)
        ->and($befund->score)->toBeLessThanOrEqual(100)
        ->and($befund->stufe->rang())->toBeGreaterThanOrEqual(Belastungsstufe::Hoch->rang());
});

it('BelastungsAnalyzer: ruhige Station (0 Risiken, gute Deckung) → Gering', function () {
    // Nur 1 Bewohner ohne Risiken, Deckung 100%
    $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'Z99', 'betten' => 1]);
    Resident::create([
        'room_id' => $room->id,
        'name' => 'Ruhig Bewohner',
        'geburtsdatum' => now()->subYears(70)->format('Y-m-d'),
        'geschlecht' => 'm',
        'pflegegrad' => 1,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $staffing = makeStaffing(100, 100, 100, 100);

    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    expect($befunde)->toHaveCount(1)
        ->and($befunde->first()->stufe)->toBe(Belastungsstufe::Gering)
        ->and($befunde->first()->score)->toBe(0);
});

it('BelastungsAnalyzer: Score liegt immer zwischen 0 und 100', function () {
    // Extremfall: viele Risiken, null Deckung
    createResidentWithRisks($this->station, $this->tenant->id, riskCount: 20, pflegegrad: 5);

    $staffing = makeStaffing(0, 100, 0, 100);

    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    expect($befunde->first()->score)->toBeGreaterThanOrEqual(0)
        ->and($befunde->first()->score)->toBeLessThanOrEqual(100);
});

it('BelastungsAnalyzer: BelastungsBefund enthält kein user_id-Feld', function () {
    $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'Z1', 'betten' => 1]);
    Resident::create([
        'room_id' => $room->id,
        'name' => 'Bewohner',
        'geburtsdatum' => now()->subYears(75)->format('Y-m-d'),
        'geschlecht' => 'm',
        'pflegegrad' => 2,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $staffing = makeStaffing();
    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    $befund = $befunde->first();
    expect($befund)->toBeInstanceOf(BelastungsBefund::class);

    // Kein Personenbezug: kein user_id auf dem DTO
    $properties = array_keys((array) $befund);
    expect($properties)->not->toContain('user_id')
        ->and($properties)->not->toContain('userId');
});

it('BelastungsAnalyzer: Signale werden befüllt', function () {
    $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'Z2', 'betten' => 1]);
    Resident::create([
        'room_id' => $room->id,
        'name' => 'Bewohner 2',
        'geburtsdatum' => now()->subYears(72)->format('Y-m-d'),
        'geschlecht' => 'w',
        'pflegegrad' => 3,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $staffing = makeStaffing();
    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    $signale = $befunde->first()->signale;
    expect($signale)->toHaveKey('Pflegelast')
        ->toHaveKey('Personaldeckung')
        ->toHaveKey('Spitzenzeit')
        ->toHaveKey('Ergonomie');
});

it('BelastungsAnalyzer: leere Station (keine aktiven Bewohner) wird übersprungen', function () {
    // Station ohne Bewohner
    $building = Building::create(['name' => 'B2']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'OG']);
    Station::create(['floor_id' => $floor->id, 'name' => 'Leer-Station']);

    $staffing = makeStaffing();
    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, []);

    expect($befunde)->toHaveCount(0);
});

it('BelastungsAnalyzer: mit SpitzenzeitAnalyse werden Unterdeckungen eingerechnet', function () {
    $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'ZS1', 'betten' => 1]);
    Resident::create([
        'room_id' => $room->id,
        'name' => 'Spitzen-Bewohner',
        'geburtsdatum' => now()->subYears(78)->format('Y-m-d'),
        'geschlecht' => 'w',
        'pflegegrad' => 2,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $staffing = makeStaffing();

    // 3 Unterdeckungs-Fenster → spitzenScore = min(100, 3*20) = 60
    $zellen = [
        1 => [
            '2026-06-08' => ['ist' => 0, 'soll' => 2, 'ampel' => 'rot', 'aktiv' => true],
            '2026-06-09' => ['ist' => 1, 'soll' => 2, 'ampel' => 'gelb', 'aktiv' => true],
        ],
        2 => [
            '2026-06-08' => ['ist' => 2, 'soll' => 2, 'ampel' => 'gruen', 'aktiv' => true],
            '2026-06-09' => ['ist' => 0, 'soll' => 2, 'ampel' => 'rot', 'aktiv' => true],
        ],
    ];

    $spitzen = new SpitzenzeitAnalyse(
        fenster: new Collection([]),
        tage: [],
        zellen: $zellen,
        vorschlaege: [],
    );

    $analyzer = app(BelastungsAnalyzer::class);
    $befunde = $analyzer->analysiere($this->tenant->id, $staffing, [], $spitzen);

    expect($befunde)->toHaveCount(1);
    $signale = $befunde->first()->signale;
    expect($signale['Spitzenzeit'])->toContain('3');
});

// ---------------------------------------------------------------------------
// BelastungMelden
// ---------------------------------------------------------------------------

it('BelastungMelden: legt Meldung an und sendet Notification an Admin', function () {
    Notification::fake();

    $room = Room::create(['station_id' => $this->station->id, 'nummer' => 'M1', 'betten' => 1]);
    Resident::create([
        'room_id' => $room->id,
        'name' => 'M Bewohner',
        'geburtsdatum' => now()->subYears(80)->format('Y-m-d'),
        'geschlecht' => 'm',
        'pflegegrad' => 4,
        'aufnahme_am' => now()->format('Y-m-d'),
        'status' => 'aktiv',
    ]);

    $befund = new BelastungsBefund(
        stationId: $this->station->id,
        wohnbereich: 'Wohnbereich Alpha',
        stufe: Belastungsstufe::Hoch,
        score: 72,
        signale: ['Pflegelast' => 'Score 60 (5 Risiken)', 'Personaldeckung' => '50 %'],
    );

    $service = app(BelastungMelden::class);
    $meldung = $service->handle($befund);

    expect($meldung)->not->toBeNull()
        ->and($meldung->stufe)->toBe(Belastungsstufe::Hoch)
        ->and($meldung->score)->toBe(72)
        ->and($meldung->wohnbereich)->toBe('Wohnbereich Alpha')
        ->and($meldung->istOffen())->toBeTrue()
        ->and($meldung->tenant_id)->toBe($this->tenant->id);

    Notification::assertSentTo($this->admin, BelastungKritisch::class);
});

it('BelastungMelden: Dedupe — zweiter Aufruf bei offener Meldung gibt null zurück', function () {
    Notification::fake();

    $befund = new BelastungsBefund(
        stationId: $this->station->id,
        wohnbereich: 'Wohnbereich Alpha',
        stufe: Belastungsstufe::Kritisch,
        score: 85,
        signale: ['Pflegelast' => 'Score 80 (7 Risiken)'],
    );

    $service = app(BelastungMelden::class);
    $erste = $service->handle($befund);
    $zweite = $service->handle($befund);

    expect($erste)->not->toBeNull()
        ->and($zweite)->toBeNull();

    expect(Belastungsmeldung::where('station_id', $this->station->id)->count())->toBe(1);
});

it('BelastungMelden: nicht-meldepflichtige Stufe → null', function () {
    Notification::fake();

    foreach ([Belastungsstufe::Gering, Belastungsstufe::Erhoeht] as $stufe) {
        $befund = new BelastungsBefund(
            stationId: $this->station->id,
            wohnbereich: 'Wohnbereich Alpha',
            stufe: $stufe,
            score: 10,
            signale: [],
        );

        $service = app(BelastungMelden::class);
        expect($service->handle($befund))->toBeNull();
    }

    Notification::assertNothingSent();
});

it('BelastungMelden: Meldung ist tenant-scoped', function () {
    Notification::fake();

    $befund = new BelastungsBefund(
        stationId: $this->station->id,
        wohnbereich: 'Wohnbereich Alpha',
        stufe: Belastungsstufe::Hoch,
        score: 65,
        signale: ['Pflegelast' => 'Score 50'],
    );

    $service = app(BelastungMelden::class);
    $meldung = $service->handle($befund);

    expect($meldung->tenant_id)->toBe($this->tenant->id);
});

// ---------------------------------------------------------------------------
// EntlastungErgreifen
// ---------------------------------------------------------------------------

it('EntlastungErgreifen: legt PsychischeBelastung-Gefaehrdung an und verknüpft Schutzmassnahme', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB Alpha',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $meldung = Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich Alpha',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 72,
        'signale' => ['Pflegelast' => 'Score 60'],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $service = app(EntlastungErgreifen::class);
    $massnahme = $service->handle($meldung, $gbu, 'Dienstplan-Entlastung', '2026-12-31');

    expect($massnahme->id)->toBeInt()
        ->and($massnahme->beschreibung)->toBe('Dienstplan-Entlastung');

    $meldung->refresh();
    expect($meldung->schutzmassnahme_id)->toBe($massnahme->id);

    $gefaehrdung = Gefaehrdung::find($massnahme->gefaehrdung_id);
    expect($gefaehrdung->faktor)->toBe(Gefaehrdungsfaktor::PsychischeBelastung);
});

it('EntlastungErgreifen: zweiter Aufruf legt KEINE zweite PsychischeBelastung-Gefaehrdung an', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB Alpha',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $meldung1 = Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich Alpha',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 72,
        'signale' => ['Pflegelast' => 'Score 60'],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $meldung2 = Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich Alpha',
        'stufe' => Belastungsstufe::Kritisch,
        'score' => 88,
        'signale' => ['Pflegelast' => 'Score 80'],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $service = app(EntlastungErgreifen::class);
    $service->handle($meldung1, $gbu, 'Erste Maßnahme');
    $service->handle($meldung2, $gbu, 'Zweite Maßnahme');

    $gefaehrdungCount = Gefaehrdung::where('gefaehrdungsbeurteilung_id', $gbu->id)
        ->where('faktor', Gefaehrdungsfaktor::PsychischeBelastung)
        ->count();

    expect($gefaehrdungCount)->toBe(1);
});

it('EntlastungErgreifen: 403 bei fremdem Tenant', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Heim', 'slug' => 'fremdes-test']);

    app(CurrentTenant::class)->set($fremderTenant);
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Fremde GBU',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $meldung = Belastungsmeldung::create([
        'station_id' => $this->station->id,
        'wohnbereich' => 'Wohnbereich Alpha',
        'stufe' => Belastungsstufe::Hoch,
        'score' => 65,
        'signale' => [],
        'gemeldet_am' => today()->toDateString(),
    ]);

    $service = app(EntlastungErgreifen::class);

    expect(fn () => $service->handle($meldung, $gbu, 'Angriff'))
        ->toThrow(HttpException::class);
});
