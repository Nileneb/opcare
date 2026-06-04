# OPCare — Plan 6: Controlling / QMS (Qualitätsmanagement) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Qualitätsmanagement & Controlling: qualitätsrelevante **Ereignisse** strukturiert erfassen (Sturz, Dekubitus, FEM, Gewichtsverlust …), daraus **Indikatoren** über **Stichtags-Kohorten** berechnen (Prävalenz/Inzidenz) und in einem **Controlling-Dashboard** mit KPIs (Pflegegrad-Verteilung, Belegung, Schmerz-Mittelwert, Indikator-Trends) sichtbar machen. Liefert die geprüfte Datengrundlage für den QDVS-Export (Plan 7).

**Architecture:** Neue Domäne `App\Domains\Quality`. Ein `CareEvent` (tenant-scoped, append-only) ist die zentrale, getaggte Datenquelle (Indikator + Datum + Schweregrad + Details). Ein `Cohort`-Wertobjekt bildet „alle Bewohner am Stichtag" ab. Ein `IndicatorService` rechnet Prävalenz/Inzidenz/KPIs als **DB-Aggregation** (nicht im RAM wie OPDEs `HashMap<Resident,DB>`). Schmerz-KPI kommt aus `VitalReading` (Plan 5). Reports sind Livewire-Seiten mit Widgets.

**Tech Stack:** wie Plan 1–5. Nutzt `VitalReading` (Plan 5), `Resident`/`Room` (Plan 1).

**Voraussetzung:** Plan 1 (Resident/Room/Pflegegrad). Plan 5 empfohlen (Schmerz-Vitalwerte). Plan 4 empfohlen (Tenant-Härtung).

**Referenz:** OPDE-Domänenkarte Abschnitt 3 (ControllingTools, Commontags, ResValue) + Abschnitt 2 (QProcess, Stichtag-Kohorte). Spec §Scope „Controlling/QMS".

---

## File Structure (Plan 6)

```
app/Domains/Quality/
├── Enums/{QualityIndicator, EventSeverity}.php
├── Models/CareEvent.php
├── Data/{CareEventData, IndicatorResult, KpiSnapshot}.php
├── Support/Cohort.php                     # Bewohner am Stichtag
├── Services/IndicatorService.php          # Prävalenz/Inzidenz/KPIs (DB-Aggregation)
├── Actions/RecordCareEvent.php
├── Policies/CareEventPolicy.php
└── Database/Factories/CareEventFactory.php
app/Livewire/Quality/{Controlling, QualityReport}.php (+ views)
database/migrations/2026_06_04_0005xx_create_care_events_table.php
tests/Feature/Quality/...
```

---

## Task 1: QualityIndicator-Enum + EventSeverity

**Files:**
- Create: `app/Domains/Quality/Enums/{QualityIndicator,EventSeverity}.php`
- Test: `tests/Feature/Quality/IndicatorEnumTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/IndicatorEnumTest.php`:
```php
<?php

use App\Domains\Quality\Enums\QualityIndicator;

it('kennt die QS-Indikatoren mit Labels', function () {
    expect(QualityIndicator::Sturz->label())->toBe('Sturz')
        ->and(QualityIndicator::Dekubitus->label())->toBe('Dekubitus (neu erworben)')
        ->and(count(QualityIndicator::cases()))->toBeGreaterThanOrEqual(6);
});
```

- [ ] **Step 2: Enums**

`app/Domains/Quality/Enums/QualityIndicator.php`:
```php
<?php
namespace App\Domains\Quality\Enums;

// Pragmatische QS-Indikatoren der stationären Pflege. Das exakte Mapping auf die
// offiziellen QDVS-Ergebnisindikatoren erfolgt in Plan 7 (QDVS-Export).
enum QualityIndicator: string
{
    case Sturz = 'sturz';
    case Dekubitus = 'dekubitus';
    case Gewichtsverlust = 'gewichtsverlust';
    case Schmerz = 'schmerz';
    case Inkontinenz = 'inkontinenz';
    case Fem = 'fem';                       // freiheitsentziehende Maßnahme
    case Wunde = 'wunde';
    case Mangelernaehrung = 'mangelernaehrung';

    public function label(): string
    {
        return match ($this) {
            self::Sturz => 'Sturz',
            self::Dekubitus => 'Dekubitus (neu erworben)',
            self::Gewichtsverlust => 'Unbeabsichtigter Gewichtsverlust',
            self::Schmerz => 'Schmerz',
            self::Inkontinenz => 'Harninkontinenz',
            self::Fem => 'Freiheitsentziehende Maßnahme',
            self::Wunde => 'Chronische Wunde',
            self::Mangelernaehrung => 'Mangelernährungsrisiko',
        };
    }
}
```
`EventSeverity.php`: `Leicht='leicht'`, `Mittel='mittel'`, `Schwer='schwer'`, `OhneFolgen='ohne_folgen'`, `MitFolgen='mit_folgen'`.

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/IndicatorEnumTest.php
git add -A && git commit -m "feat(quality): quality indicators + severity enums"
```

---

## Task 2: CareEvent — Migration, Model, Factory

**Files:**
- Create: Migration `care_events`, Model `CareEvent`, Factory
- Test: `tests/Feature/Quality/CareEventTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/CareEventTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\{EventSeverity, QualityIndicator};
use App\Domains\Quality\Models\CareEvent;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erfasst ein Sturz-Ereignis mit Schweregrad', function () {
    $resident = Resident::factory()->create();
    $e = CareEvent::create([
        'resident_id' => $resident->id,
        'indicator' => QualityIndicator::Sturz,
        'datum' => '2026-02-15',
        'severity' => EventSeverity::MitFolgen,
        'details' => ['ort' => 'Bad', 'verletzung' => 'Platzwunde'],
    ]);

    expect($e->indicator)->toBe(QualityIndicator::Sturz)
        ->and($e->severity)->toBe(EventSeverity::MitFolgen)
        ->and($e->details['ort'])->toBe('Bad');
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000500_create_care_events_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('care_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('indicator');                 // QualityIndicator
            $table->date('datum');
            $table->date('behoben_am')->nullable();      // für Prävalenz: Ereignis "aktiv" bis behoben
            $table->string('severity')->nullable();      // EventSeverity
            $table->jsonb('details')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'indicator', 'datum']);
            $table->index(['resident_id', 'indicator']);
        });
    }
    public function down(): void { Schema::dropIfExists('care_events'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/Quality/Models/CareEvent.php`:
```php
<?php
namespace App\Domains\Quality\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\{EventSeverity, QualityIndicator};
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'resident_id', 'indicator', 'datum', 'behoben_am', 'severity', 'details', 'reported_by'];
    protected $casts = [
        'indicator' => QualityIndicator::class, 'severity' => EventSeverity::class,
        'datum' => 'date', 'behoben_am' => 'date', 'details' => 'array',
    ];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }

    protected static function newFactory(): \App\Domains\Quality\Database\Factories\CareEventFactory
    {
        return \App\Domains\Quality\Database\Factories\CareEventFactory::new();
    }
}
```

- [ ] **Step 4: Factory** `CareEventFactory` (resident_id => Resident::factory(), indicator random, datum recent, severity random).

- [ ] **Step 5: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Quality/CareEventTest.php
git add -A && git commit -m "feat(quality): care event model + migration + factory"
```

---

## Task 3: RecordCareEvent-Action + Policy

**Files:**
- Create: `app/Domains/Quality/Data/CareEventData.php`, `app/Domains/Quality/Actions/RecordCareEvent.php`, `app/Domains/Quality/Policies/CareEventPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Quality/RecordCareEventTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/RecordCareEventTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Actions\RecordCareEvent;
use App\Domains\Quality\Data\CareEventData;
use App\Domains\Quality\Enums\QualityIndicator;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erfasst ein Ereignis über die Action', function () {
    $resident = Resident::factory()->create();
    $e = app(RecordCareEvent::class)->handle(new CareEventData(
        resident_id: $resident->id, indicator: QualityIndicator::Dekubitus->value, datum: '2026-03-01', severity: 'mittel',
    ));
    expect($e->indicator)->toBe(QualityIndicator::Dekubitus);
});
```

- [ ] **Step 2: DTO + Action + Policy**

`CareEventData.php`:
```php
<?php
namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class CareEventData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $indicator,
        public string $datum,
        public ?string $severity = null,
        public ?array $details = null,
        public ?string $behoben_am = null,
        public ?int $reported_by = null,
    ) {}
}
```
`RecordCareEvent.php`:
```php
<?php
namespace App\Domains\Quality\Actions;

use App\Domains\Quality\Data\CareEventData;
use App\Domains\Quality\Models\CareEvent;

class RecordCareEvent
{
    public function handle(CareEventData $data): CareEvent
    {
        return CareEvent::create([...$data->toArray(), 'reported_by' => $data->reported_by ?? auth()->id()]);
    }
}
```
`CareEventPolicy.php`: viewAny (alle Pflegerollen + leserecht), create/update (`admin`,`pflegefachkraft`,`pflegehilfskraft`). Registrieren.

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/RecordCareEventTest.php
git add -A && git commit -m "feat(quality): record care event action + policy"
```

---

## Task 4: Stichtags-Kohorte (Cohort)

**Files:**
- Create: `app/Domains/Quality/Support/Cohort.php`
- Test: `tests/Feature/Quality/CohortTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/CohortTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('enthält nur am Stichtag anwesende Bewohner', function () {
    Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'entlassung_am' => null, 'status' => 'aktiv']); // anwesend
    Resident::factory()->create(['aufnahme_am' => '2026-03-01', 'entlassung_am' => null, 'status' => 'aktiv']); // erst nach Stichtag
    Resident::factory()->create(['aufnahme_am' => '2025-06-01', 'entlassung_am' => '2026-01-10', 'status' => 'entlassen']); // vor Stichtag entlassen

    $cohort = Cohort::atStichtag('2026-02-15');
    expect($cohort->count())->toBe(1);
});
```

- [ ] **Step 2: Cohort**

`app/Domains/Quality/Support/Cohort.php`:
```php
<?php
namespace App\Domains\Quality\Support;

use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Cohort
{
    public function __construct(public string $stichtag, public Collection $residents) {}

    public static function atStichtag(string $stichtag): self
    {
        $datum = Carbon::parse($stichtag)->toDateString();

        $residents = Resident::query()
            ->whereDate('aufnahme_am', '<=', $datum)
            ->where(fn ($q) => $q->whereNull('entlassung_am')->orWhereDate('entlassung_am', '>=', $datum))
            ->get();

        return new self($datum, $residents);
    }

    public function count(): int { return $this->residents->count(); }
    public function ids(): array { return $this->residents->pluck('id')->all(); }
}
```

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/CohortTest.php
git add -A && git commit -m "feat(quality): stichtag cohort"
```

---

## Task 5: IndicatorService — Prävalenz & Inzidenz

**Files:**
- Create: `app/Domains/Quality/Data/IndicatorResult.php`, `app/Domains/Quality/Services/IndicatorService.php`
- Test: `tests/Feature/Quality/IndicatorServiceTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/IndicatorServiceTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('berechnet Inzidenz und Prävalenz eines Indikators', function () {
    $r1 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);
    $r2 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);
    $r3 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);

    // r1: Dekubitus im Zeitraum, noch nicht behoben (zählt für Prävalenz am Stichtag)
    CareEvent::create(['resident_id' => $r1->id, 'indicator' => QualityIndicator::Dekubitus, 'datum' => '2026-02-10']);
    // r2: Dekubitus im Zeitraum, vor Stichtag behoben (zählt für Inzidenz, NICHT Prävalenz)
    CareEvent::create(['resident_id' => $r2->id, 'indicator' => QualityIndicator::Dekubitus, 'datum' => '2026-02-01', 'behoben_am' => '2026-02-05']);

    $svc = app(IndicatorService::class);
    $cohort = Cohort::atStichtag('2026-02-15');

    $inz = $svc->incidence(QualityIndicator::Dekubitus, '2026-02-01', '2026-02-28', $cohort);
    $prev = $svc->prevalence(QualityIndicator::Dekubitus, $cohort);

    expect($inz->betroffene)->toBe(2)->and($inz->kohorte)->toBe(3)
        ->and($prev->betroffene)->toBe(1);
});
```

- [ ] **Step 2: DTO**

`IndicatorResult.php`:
```php
<?php
namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class IndicatorResult extends Data
{
    public function __construct(
        public string $indicator,
        public string $art,          // 'inzidenz' | 'praevalenz'
        public int $betroffene,
        public int $kohorte,
    ) {}

    public function quote(): float
    {
        return $this->kohorte > 0 ? round($this->betroffene / $this->kohorte * 100, 1) : 0.0;
    }
}
```

- [ ] **Step 3: Service**

`app/Domains/Quality/Services/IndicatorService.php`:
```php
<?php
namespace App\Domains\Quality\Services;

use App\Domains\Quality\Data\IndicatorResult;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

class IndicatorService
{
    /** Inzidenz: Bewohner der Kohorte mit NEUEM Ereignis im Zeitraum. */
    public function incidence(QualityIndicator $indicator, string $von, string $bis, Cohort $cohort): IndicatorResult
    {
        $betroffene = CareEvent::query()
            ->where('indicator', $indicator->value)
            ->whereIn('resident_id', $cohort->ids())
            ->whereBetween('datum', [$von, $bis])
            ->distinct('resident_id')->count('resident_id');

        return new IndicatorResult($indicator->value, 'inzidenz', $betroffene, $cohort->count());
    }

    /** Prävalenz: Bewohner der Kohorte mit am Stichtag AKTIVEM (nicht behobenem) Ereignis. */
    public function prevalence(QualityIndicator $indicator, Cohort $cohort): IndicatorResult
    {
        $betroffene = CareEvent::query()
            ->where('indicator', $indicator->value)
            ->whereIn('resident_id', $cohort->ids())
            ->whereDate('datum', '<=', $cohort->stichtag)
            ->where(fn ($q) => $q->whereNull('behoben_am')->orWhereDate('behoben_am', '>', $cohort->stichtag))
            ->distinct('resident_id')->count('resident_id');

        return new IndicatorResult($indicator->value, 'praevalenz', $betroffene, $cohort->count());
    }

    /** Alle Indikatoren als Inzidenz im Zeitraum (für Report-Tabelle). */
    public function allIncidences(string $von, string $bis, Cohort $cohort): array
    {
        return array_map(fn ($i) => $this->incidence($i, $von, $bis, $cohort), QualityIndicator::cases());
    }
}
```

- [ ] **Step 4: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/IndicatorServiceTest.php
git add -A && git commit -m "feat(quality): indicator service (incidence + prevalence, db aggregation)"
```

---

## Task 6: KPI-Snapshot (Pflegegrad, Belegung, Schmerz)

**Files:**
- Create: `app/Domains/Quality/Data/KpiSnapshot.php`, Erweiterung `IndicatorService::kpis()`
- Test: `tests/Feature/Quality/KpiTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/KpiTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Building, Floor, Resident, Room, Station};
use App\Domains\Quality\Services\IndicatorService;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('liefert Pflegegrad-Verteilung und Belegung', function () {
    $b = Building::create(['name' => 'H']);
    $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
    $s = Station::create(['floor_id' => $f->id, 'name' => 'WB1']);
    $room = Room::create(['station_id' => $s->id, 'nummer' => '1', 'betten' => 4]);
    Resident::factory()->count(3)->create(['room_id' => $room->id, 'pflegegrad' => 3, 'status' => 'aktiv']);

    $kpi = app(IndicatorService::class)->kpis();
    expect($kpi->pflegegradVerteilung[3])->toBe(3)
        ->and($kpi->betten)->toBe(4)
        ->and($kpi->belegt)->toBe(3);
});
```

- [ ] **Step 2: DTO + Service-Methode**

`KpiSnapshot.php`:
```php
<?php
namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class KpiSnapshot extends Data
{
    public function __construct(
        public int $bewohnerAktiv,
        public array $pflegegradVerteilung,   // [pg => anzahl]
        public int $betten,
        public int $belegt,
    ) {}

    public function auslastung(): float
    {
        return $this->betten > 0 ? round($this->belegt / $this->betten * 100, 1) : 0.0;
    }
}
```
In `IndicatorService` ergänzen:
```php
use App\Domains\Masterdata\Models\{Resident, Room};
use App\Domains\Quality\Data\KpiSnapshot;

public function kpis(): KpiSnapshot
{
    $aktive = Resident::where('status', 'aktiv')->get();
    $verteilung = $aktive->groupBy('pflegegrad')->map->count()
        ->mapWithKeys(fn ($n, $pg) => [(int) $pg => $n])->all();

    return new KpiSnapshot(
        bewohnerAktiv: $aktive->count(),
        pflegegradVerteilung: $verteilung,
        betten: (int) Room::sum('betten'),
        belegt: $aktive->whereNotNull('room_id')->count(),
    );
}
```

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/KpiTest.php
git add -A && git commit -m "feat(quality): kpi snapshot (pflegegrad, belegung)"
```

---

## Task 7: Controlling-Dashboard (Livewire) + Report-Seite

**Files:**
- Create: `app/Livewire/Quality/Controlling.php` (+ view), `app/Livewire/Quality/QualityReport.php` (+ view)
- Modify: `routes/web.php`, `layouts.app` (Nav „Controlling", admin/pflegefachkraft)
- Test: `tests/Feature/Quality/ControllingPageTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Quality/ControllingPageTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Quality\{Controlling, QualityReport};
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->lead = User::factory()->create(['tenant_id' => $t->id]);
    $this->lead->assignRole('pflegefachkraft');
    Resident::factory()->count(2)->create(['status' => 'aktiv', 'aufnahme_am' => '2026-01-01']);
});

it('rendert das Controlling-Dashboard mit KPIs', function () {
    Livewire::actingAs($this->lead)->test(Controlling::class)->assertOk()->assertSee('Belegung');
});

it('rendert den Qualitäts-Report für einen Stichtag', function () {
    Livewire::actingAs($this->lead)->test(QualityReport::class)
        ->set('stichtag', '2026-02-15')->set('von', '2026-01-01')->set('bis', '2026-03-31')
        ->call('berechnen')->assertOk()->assertSee('Sturz');
});
```

- [ ] **Step 2: Controlling-Komponente**

`app/Livewire/Quality/Controlling.php`:
```php
<?php
namespace App\Livewire\Quality;

use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Controlling extends Component
{
    public function render(IndicatorService $svc)
    {
        $cohort = Cohort::atStichtag(today()->toDateString());
        return view('livewire.quality.controlling', [
            'kpi' => $svc->kpis(),
            'incidences' => $svc->allIncidences(today()->subMonths(3)->toDateString(), today()->toDateString(), $cohort),
        ]);
    }
}
```

- [ ] **Step 3: Controlling-View** — `.page-head` + `.grid-4` Stat-Karten (Bewohner aktiv, Belegung `{{ $kpi->auslastung() }} %`, Betten, …), Pflegegrad-Verteilung als Balken (Breite ∝ Anteil), Indikator-Tabelle (Indikator-Label, Betroffene/Kohorte, Quote %). Muster + Klassen aus `admin.css` (`.stat`, `.card`, `table.data`, `.badge`).

- [ ] **Step 4: QualityReport-Komponente**

`app/Livewire/Quality/QualityReport.php`:
```php
<?php
namespace App\Livewire\Quality;

use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QualityReport extends Component
{
    public string $stichtag;
    public string $von;
    public string $bis;
    public array $ergebnisse = [];
    public int $kohorte = 0;

    public function mount(): void
    {
        $this->stichtag = today()->toDateString();
        $this->von = today()->startOfQuarter()->toDateString();
        $this->bis = today()->endOfQuarter()->toDateString();
    }

    public function berechnen(IndicatorService $svc): void
    {
        $cohort = Cohort::atStichtag($this->stichtag);
        $this->kohorte = $cohort->count();
        $this->ergebnisse = collect($svc->allIncidences($this->von, $this->bis, $cohort))
            ->map(fn ($r) => ['indicator' => $r->indicator, 'betroffene' => $r->betroffene, 'quote' => $r->quote()])
            ->all();
    }

    public function render()
    {
        return view('livewire.quality.quality-report');
    }
}
```

- [ ] **Step 5: QualityReport-View** — Filter (Stichtag/von/bis + Button „berechnen"), Ergebnis-Tabelle (Indikator-Label via `QualityIndicator::from($e['indicator'])->label()`, Betroffene, Quote %), Kohorten-Größe. Muster wie oben.

- [ ] **Step 6: Route + Nav**

`routes/web.php`:
```php
Route::get('/controlling', \App\Livewire\Quality\Controlling::class)->name('controlling');
Route::get('/qualitaet/report', \App\Livewire\Quality\QualityReport::class)->name('quality.report');
```
Nav in `layouts.app` (nur `admin`/`pflegefachkraft`): „Controlling" → `controlling`.

- [ ] **Step 7: Tests grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Quality/ControllingPageTest.php
git add -A && git commit -m "feat(quality): controlling dashboard + quality report ui"
```

---

## Task 8: Demo-Seed + Gesamtsuite

**Files:**
- Modify: `DemoSeeder` (einige `CareEvent` für realistische Indikatoren)

- [ ] **Step 1: DemoSeeder erweitern**

In `DemoSeeder::run()`: für 2–3 Bewohner je 1–2 `CareEvent` (Sturz mit/ohne Folgen, Dekubitus behoben/aktiv) im aktuellen Quartal anlegen, damit Controlling/Report sofort Zahlen zeigen. WHY: kein „Feature ohne Outcome".

- [ ] **Step 2: Frisch migrieren/seeden + Gesamtsuite**

Run: `php artisan migrate:fresh --seed && ./vendor/bin/pest`
Expected: ALLE PASS (Plan 1–6).

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat(quality): demo care events; full suite green"
```

---

## Self-Review-Ergebnis (Plan 6)

- **Spec-Abdeckung:** Getaggte Ereigniserfassung (OPDE Commontags/NReport) → `CareEvent` (Tasks 2,3). Stichtag-Kohorte (OPDE QDVS-Kohortenansatz) → Task 4. Indikatoren Prävalenz/Inzidenz als DB-Aggregation (statt OPDE-`HashMap`) → Task 5. KPIs Pflegegrad/Belegung/Schmerz → Task 6 (Schmerz-Mittelwert nutzt `VitalReading` aus Plan 5; optional als KPI-Erweiterung ergänzbar). Dashboard + Report-UI → Task 7. Demo-Outcome → Task 8.
- **Platzhalter:** keine — Kern (Cohort, Inzidenz/Prävalenz, KPI) vollständig als Code; Views verweisen auf das exakte `admin.css`-Muster.
- **Typ-Konsistenz:** `QualityIndicator`/`EventSeverity`, `CareEventData`, `IndicatorResult{betroffene,kohorte,quote()}`, `KpiSnapshot{auslastung()}`, `Cohort::atStichtag()/ids()/count()/stichtag`, `IndicatorService::incidence/prevalence/allIncidences/kpis` durchgängig identisch — und exakt die Signaturen, die Plan 7 (QDVS) konsumiert.

## Folge-Plan
- **Plan 7:** QDVS-Export — `docs/superpowers/plans/2026-06-04-opcare-qdvs-export.md` (nutzt `Cohort`, `IndicatorService`, `CareEvent` + Masterdata).
