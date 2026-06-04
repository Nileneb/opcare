# OPCare — Plan 7: QDVS-Export (Datenauswertungsstelle) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Strukturierter Export der Qualitätsindikatoren-Daten an die **Datenauswertungsstelle (DAS/DVS)** zu einem **Stichtag**: Stichtags-Kohorte bilden → je Bewohner ein **Datenpaket** (pseudonymisierte Stammdaten + Indikator-Befunde) → **validieren** (Datenqualität, wie OPDE `DAS_REGELN`) → über einen **Spec-Adapter** in ein Austauschformat rendern (CSV in v1, XML/JSON als spätere Specs) → herunterladbar, mit Audit-Eintrag.

**Architecture:** Neue Domäne `App\Domains\Qdvs`. **Spec-Adapter-Pattern** (`QdvsSpec`-Interface, Registry) — OPDEs Multi-Spec-Strategie (Spec 14/21/30/40) modernisiert: pro Spezifikation eine Klasse, austauschbar. `AssemblePackages` baut aus Kohorte + `CareEvent` (Plan 6) + Masterdata die `QdvsResidentPackage`-DTOs. `QdvsValidator` sammelt Datenqualitätsfehler. `BuildQdvsExport` orchestriert und persistiert einen `QdvsExport`-Audit-Datensatz + die Datei. Eine Livewire-Seite startet/prüft/lädt den Export.

**Tech Stack:** wie Plan 1–6. **Voraussetzung: Plan 6** (Cohort, IndicatorService, CareEvent), Plan 4 (`tenants.ik_nummer`), Plan 1 (Resident/Diagnosen).

**Referenz:** OPDE-Domänenkarte Abschnitt 2 (QdvsService/QdvsResidentInfoObject/DAS_REGELN/Spec-Versionen, Stichtag-Kohorte). Spec §Scope „QDVS-Export". Datenschutz: Export pseudonymisiert (kein Klarname), DSGVO Art. 9.

---

## File Structure (Plan 7)

```
app/Domains/Qdvs/
├── Contracts/QdvsSpec.php
├── Specs/CsvQdvsSpec.php                  # konkrete Spec v1 (CSV)
├── Support/SpecRegistry.php
├── Data/{QdvsResidentPackage, ValidationIssue}.php
├── Services/{AssemblePackages, QdvsValidator}.php
├── Actions/BuildQdvsExport.php
├── Models/QdvsExport.php
└── Database/Factories/...
app/Livewire/Qdvs/Export.php (+ view)
config/qdvs.php
database/migrations/2026_06_04_000600_create_qdvs_exports_table.php
tests/Feature/Qdvs/...
```

---

## Task 1: Datenpaket-DTO + ValidationIssue

**Files:**
- Create: `app/Domains/Qdvs/Data/{QdvsResidentPackage,ValidationIssue}.php`
- Test: `tests/Feature/Qdvs/PackageDtoTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/PackageDtoTest.php`:
```php
<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;

it('hält ein pseudonymisiertes Bewohner-Datenpaket', function () {
    $p = new QdvsResidentPackage(
        pseudonym: 'R-000123', geburtsjahr: 1940, geschlecht: 'w', pflegegrad: 3,
        aufnahme_am: '2023-03-15', icd_codes: ['F00.0', 'I10'],
        indikatoren: ['sturz' => true, 'dekubitus' => false],
    );

    expect($p->pseudonym)->toBe('R-000123')
        ->and($p->indikatoren['sturz'])->toBeTrue()
        ->and($p->icd_codes)->toContain('I10');
});
```

- [ ] **Step 2: DTOs**

`QdvsResidentPackage.php`:
```php
<?php
namespace App\Domains\Qdvs\Data;

use Spatie\LaravelData\Data;

class QdvsResidentPackage extends Data
{
    public function __construct(
        public string $pseudonym,        // KEIN Klarname (DSGVO)
        public ?int $geburtsjahr,
        public ?string $geschlecht,
        public ?int $pflegegrad,
        public ?string $aufnahme_am,
        /** @var array<int, string> ICD-10-Codes */
        public array $icd_codes = [],
        /** @var array<string, bool|string> indikator => befund (bool oder Schweregrad) */
        public array $indikatoren = [],
    ) {}
}
```
`ValidationIssue.php`:
```php
<?php
namespace App\Domains\Qdvs\Data;

use Spatie\LaravelData\Data;

class ValidationIssue extends Data
{
    public function __construct(
        public string $pseudonym,
        public string $feld,
        public string $meldung,
        public string $schwere = 'fehler',  // 'fehler' (blockt Export) | 'warnung'
    ) {}
}
```

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/PackageDtoTest.php
git add -A && git commit -m "feat(qdvs): resident package + validation issue dtos"
```

---

## Task 2: Spec-Contract + Registry + CSV-Spec

**Files:**
- Create: `app/Domains/Qdvs/Contracts/QdvsSpec.php`, `app/Domains/Qdvs/Specs/CsvQdvsSpec.php`, `app/Domains/Qdvs/Support/SpecRegistry.php`, `config/qdvs.php`
- Test: `tests/Feature/Qdvs/CsvSpecTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/CsvSpecTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Specs\CsvQdvsSpec;

it('rendert eine CSV mit Kopfzeile und einer Zeile je Bewohner', function () {
    $tenant = Tenant::make(['name' => 'Haus A', 'ik_nummer' => '260123456']);
    $spec = new CsvQdvsSpec();

    $csv = $spec->render([
        new QdvsResidentPackage('R-1', 1940, 'w', 3, '2023-01-01', ['F00.0'], ['sturz' => true, 'dekubitus' => false]),
    ], $tenant, '2026-02-15');

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    expect($lines)->toHaveCount(2)                       // Header + 1 Datenzeile
        ->and($lines[0])->toContain('pseudonym')
        ->and($lines[1])->toContain('R-1')
        ->and($spec->filename('2026-02-15'))->toBe('qdvs-export-2026-02-15.csv');
});
```

- [ ] **Step 2: Contract**

`app/Domains/Qdvs/Contracts/QdvsSpec.php`:
```php
<?php
namespace App\Domains\Qdvs\Contracts;

use App\Domains\Identity\Models\Tenant;

interface QdvsSpec
{
    public function key(): string;
    public function label(): string;

    /** @param array<int, \App\Domains\Qdvs\Data\QdvsResidentPackage> $packages */
    public function render(array $packages, Tenant $tenant, string $stichtag): string;

    public function filename(string $stichtag): string;
    public function mimeType(): string;
}
```

- [ ] **Step 3: CSV-Spec**

`app/Domains/Qdvs/Specs/CsvQdvsSpec.php`:
```php
<?php
namespace App\Domains\Qdvs\Specs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Qdvs\Contracts\QdvsSpec;
use App\Domains\Quality\Enums\QualityIndicator;

class CsvQdvsSpec implements QdvsSpec
{
    public function key(): string { return 'csv-v1'; }
    public function label(): string { return 'CSV (OPCare v1)'; }
    public function mimeType(): string { return 'text/csv'; }
    public function filename(string $stichtag): string { return "qdvs-export-{$stichtag}.csv"; }

    public function render(array $packages, Tenant $tenant, string $stichtag): string
    {
        $indikatoren = array_map(fn ($i) => $i->value, QualityIndicator::cases());
        $header = array_merge(
            ['einrichtung_ik', 'stichtag', 'pseudonym', 'geburtsjahr', 'geschlecht', 'pflegegrad', 'aufnahme_am', 'icd_codes'],
            $indikatoren,
        );

        $rows = [$this->line($header)];
        foreach ($packages as $p) {
            $row = [
                $tenant->ik_nummer, $stichtag, $p->pseudonym, $p->geburtsjahr, $p->geschlecht,
                $p->pflegegrad, $p->aufnahme_am, implode('|', $p->icd_codes),
            ];
            foreach ($indikatoren as $key) {
                $val = $p->indikatoren[$key] ?? false;
                $row[] = is_bool($val) ? ($val ? '1' : '0') : (string) $val;
            }
            $rows[] = $this->line($row);
        }

        return implode("\n", $rows)."\n";
    }

    private function line(array $fields): string
    {
        return implode(';', array_map(fn ($f) => '"'.str_replace('"', '""', (string) $f).'"', $fields));
    }
}
```

- [ ] **Step 4: Registry + Config**

`config/qdvs.php`:
```php
<?php
return [
    'default_spec' => 'csv-v1',
    'specs' => [
        \App\Domains\Qdvs\Specs\CsvQdvsSpec::class,
        // spätere Specs (XML/DAS-konform) hier registrieren
    ],
    'disk' => 'local',
    'path' => 'qdvs',
];
```
`app/Domains/Qdvs/Support/SpecRegistry.php`:
```php
<?php
namespace App\Domains\Qdvs\Support;

use App\Domains\Qdvs\Contracts\QdvsSpec;
use InvalidArgumentException;

class SpecRegistry
{
    /** @return array<string, QdvsSpec> */
    public function all(): array
    {
        $out = [];
        foreach (config('qdvs.specs') as $class) {
            $spec = app($class);
            $out[$spec->key()] = $spec;
        }
        return $out;
    }

    public function get(string $key): QdvsSpec
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unbekannte QDVS-Spec: {$key}");
    }

    public function default(): QdvsSpec
    {
        return $this->get(config('qdvs.default_spec'));
    }
}
```

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/CsvSpecTest.php
git add -A && git commit -m "feat(qdvs): spec contract, registry, csv spec v1"
```

---

## Task 3: AssemblePackages (Kohorte → Datenpakete)

**Files:**
- Create: `app/Domains/Qdvs/Services/AssemblePackages.php`
- Test: `tests/Feature/Qdvs/AssemblePackagesTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/AssemblePackagesTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{IcdCode, Resident};
use App\Domains\Qdvs\Services\AssemblePackages;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('baut pseudonymisierte Pakete inkl. aktiver Indikatoren am Stichtag', function () {
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'F00.0', 'bezeichnung' => 'Demenz']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);
    CareEvent::create(['resident_id' => $r->id, 'indicator' => QualityIndicator::Sturz, 'datum' => '2026-02-10']);

    $cohort = Cohort::atStichtag('2026-02-15');
    $packages = app(AssemblePackages::class)->handle($cohort);

    expect($packages)->toHaveCount(1)
        ->and($packages[0]->pseudonym)->toBe('R-'.$r->id)
        ->and($packages[0]->icd_codes)->toContain('F00.0')
        ->and($packages[0]->indikatoren['sturz'])->toBeTrue()
        ->and($packages[0]->indikatoren['dekubitus'])->toBeFalse();
});
```

- [ ] **Step 2: Service**

`app/Domains/Qdvs/Services/AssemblePackages.php`:
```php
<?php
namespace App\Domains\Qdvs\Services;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

class AssemblePackages
{
    /** @return array<int, QdvsResidentPackage> */
    public function handle(Cohort $cohort): array
    {
        $residents = Resident::with('diagnoses.icdCode')->whereIn('id', $cohort->ids())->get();

        // Aktive Indikatoren (am Stichtag nicht behoben) je Bewohner vorab laden.
        $aktive = CareEvent::query()
            ->whereIn('resident_id', $cohort->ids())
            ->whereDate('datum', '<=', $cohort->stichtag)
            ->where(fn ($q) => $q->whereNull('behoben_am')->orWhereDate('behoben_am', '>', $cohort->stichtag))
            ->get()
            ->groupBy('resident_id');

        return $residents->map(function (Resident $r) use ($aktive) {
            $vorhanden = ($aktive[$r->id] ?? collect())->pluck('indicator')
                ->map(fn ($i) => $i instanceof QualityIndicator ? $i->value : $i)->all();

            $indikatoren = [];
            foreach (QualityIndicator::cases() as $i) {
                $indikatoren[$i->value] = in_array($i->value, $vorhanden, true);
            }

            return new QdvsResidentPackage(
                pseudonym: 'R-'.$r->id,
                geburtsjahr: $r->geburtsdatum?->year,
                geschlecht: $r->geschlecht,
                pflegegrad: $r->pflegegrad,
                aufnahme_am: $r->aufnahme_am?->toDateString(),
                icd_codes: $r->diagnoses->pluck('icdCode.code')->filter()->values()->all(),
                indikatoren: $indikatoren,
            );
        })->all();
    }
}
```

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/AssemblePackagesTest.php
git add -A && git commit -m "feat(qdvs): assemble pseudonymized resident packages"
```

---

## Task 4: QdvsValidator (Datenqualität, wie OPDE DAS_REGELN)

**Files:**
- Create: `app/Domains/Qdvs/Services/QdvsValidator.php`
- Test: `tests/Feature/Qdvs/QdvsValidatorTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/QdvsValidatorTest.php`:
```php
<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Services\QdvsValidator;

it('meldet fehlenden Pflegegrad als Fehler, fehlende Diagnose als Warnung', function () {
    $ok = new QdvsResidentPackage('R-1', 1940, 'w', 3, '2023-01-01', ['F00.0'], []);
    $kaputt = new QdvsResidentPackage('R-2', 1942, 'm', null, '2023-01-01', [], []);

    $issues = app(QdvsValidator::class)->validate([$ok, $kaputt]);

    $fehler = collect($issues)->where('schwere', 'fehler');
    expect($fehler->pluck('pseudonym')->all())->toContain('R-2')->not->toContain('R-1')
        ->and(collect($issues)->where('schwere', 'warnung')->pluck('feld')->all())->toContain('icd_codes');
});
```

- [ ] **Step 2: Validator**

`app/Domains/Qdvs/Services/QdvsValidator.php`:
```php
<?php
namespace App\Domains\Qdvs\Services;

use App\Domains\Qdvs\Data\{QdvsResidentPackage, ValidationIssue};

class QdvsValidator
{
    /**
     * @param  array<int, QdvsResidentPackage>  $packages
     * @return array<int, ValidationIssue>
     */
    public function validate(array $packages): array
    {
        $issues = [];
        foreach ($packages as $p) {
            if ($p->pflegegrad === null || $p->pflegegrad < 1 || $p->pflegegrad > 5) {
                $issues[] = new ValidationIssue($p->pseudonym, 'pflegegrad', 'Pflegegrad fehlt oder ungültig (1–5).', 'fehler');
            }
            if (! $p->geburtsjahr) {
                $issues[] = new ValidationIssue($p->pseudonym, 'geburtsjahr', 'Geburtsjahr fehlt.', 'fehler');
            }
            if (! in_array($p->geschlecht, ['m', 'w', 'd'], true)) {
                $issues[] = new ValidationIssue($p->pseudonym, 'geschlecht', 'Geschlecht fehlt/ungültig.', 'fehler');
            }
            if (empty($p->icd_codes)) {
                $issues[] = new ValidationIssue($p->pseudonym, 'icd_codes', 'Keine Diagnose hinterlegt.', 'warnung');
            }
        }
        return $issues;
    }

    /** @param array<int, ValidationIssue> $issues */
    public function hatBlockierendeFehler(array $issues): bool
    {
        return collect($issues)->contains(fn (ValidationIssue $i) => $i->schwere === 'fehler');
    }
}
```

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/QdvsValidatorTest.php
git add -A && git commit -m "feat(qdvs): data quality validator (das-rules equivalent)"
```

---

## Task 5: QdvsExport-Audit-Modell

**Files:**
- Create: Migration `qdvs_exports`, Model `QdvsExport`
- Test: `tests/Feature/Qdvs/QdvsExportModelTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/QdvsExportModelTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Qdvs\Models\QdvsExport;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('protokolliert einen Export', function () {
    $e = QdvsExport::create([
        'stichtag' => '2026-02-15', 'spec' => 'csv-v1', 'status' => 'exportiert',
        'bewohner_count' => 12, 'pfad' => 'qdvs/x.csv', 'fehler' => [],
    ]);
    expect($e->status)->toBe('exportiert')->and($e->bewohner_count)->toBe(12);
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000600_create_qdvs_exports_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('qdvs_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('stichtag');
            $table->string('spec');
            $table->string('status')->default('entwurf'); // entwurf/validiert/exportiert/fehler
            $table->unsignedInteger('bewohner_count')->default(0);
            $table->string('pfad')->nullable();
            $table->jsonb('fehler')->nullable();           // ValidationIssue[]
            $table->unsignedBigInteger('erstellt_von')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stichtag']);
        });
    }
    public function down(): void { Schema::dropIfExists('qdvs_exports'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/Qdvs/Models/QdvsExport.php`:
```php
<?php
namespace App\Domains\Qdvs\Models;

use App\Support\Models\BaseModel;

class QdvsExport extends BaseModel
{
    protected $fillable = ['tenant_id', 'stichtag', 'spec', 'status', 'bewohner_count', 'pfad', 'fehler', 'erstellt_von'];
    protected $casts = ['stichtag' => 'date', 'fehler' => 'array', 'bewohner_count' => 'integer'];
}
```

- [ ] **Step 4: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Qdvs/QdvsExportModelTest.php
git add -A && git commit -m "feat(qdvs): export audit model"
```

---

## Task 6: BuildQdvsExport (Orchestrierung)

**Files:**
- Create: `app/Domains/Qdvs/Actions/BuildQdvsExport.php`
- Test: `tests/Feature/Qdvs/BuildQdvsExportTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/BuildQdvsExportTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{IcdCode, Resident};
use App\Domains\Qdvs\Actions\BuildQdvsExport;
use App\Domains\Qdvs\Models\QdvsExport;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260123456']));
});

it('erstellt eine valide Export-Datei und protokolliert sie', function () {
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Hypertonie']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);

    $export = app(BuildQdvsExport::class)->handle(stichtag: '2026-02-15', specKey: 'csv-v1');

    expect($export->status)->toBe('exportiert')
        ->and($export->bewohner_count)->toBe(1)
        ->and($export->pfad)->not->toBeNull();
    Storage::disk('local')->assertExists($export->pfad);
});

it('blockt den Export bei Datenfehlern und protokolliert sie', function () {
    Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => null]); // PG fehlt → Fehler

    $export = app(BuildQdvsExport::class)->handle(stichtag: '2026-02-15', specKey: 'csv-v1');

    expect($export->status)->toBe('fehler')
        ->and($export->pfad)->toBeNull()
        ->and(count($export->fehler))->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Action**

`app/Domains/Qdvs/Actions/BuildQdvsExport.php`:
```php
<?php
namespace App\Domains\Qdvs\Actions;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Qdvs\Models\QdvsExport;
use App\Domains\Qdvs\Services\{AssemblePackages, QdvsValidator};
use App\Domains\Qdvs\Support\SpecRegistry;
use App\Domains\Quality\Support\Cohort;
use Illuminate\Support\Facades\Storage;

class BuildQdvsExport
{
    public function __construct(
        private AssemblePackages $assemble,
        private QdvsValidator $validator,
        private SpecRegistry $registry,
    ) {}

    public function handle(string $stichtag, ?string $specKey = null): QdvsExport
    {
        $spec = $this->registry->get($specKey ?? config('qdvs.default_spec'));
        $tenant = app(CurrentTenant::class)->get();
        $cohort = Cohort::atStichtag($stichtag);
        $packages = $this->assemble->handle($cohort);
        $issues = $this->validator->validate($packages);

        $export = QdvsExport::create([
            'stichtag' => $stichtag,
            'spec' => $spec->key(),
            'bewohner_count' => count($packages),
            'fehler' => collect($issues)->map->toArray()->all(),
            'erstellt_von' => auth()->id(),
            'status' => 'validiert',
        ]);

        if ($this->validator->hatBlockierendeFehler($issues)) {
            $export->update(['status' => 'fehler']);
            return $export;
        }

        $inhalt = $spec->render($packages, $tenant, $stichtag);
        $pfad = trim(config('qdvs.path'), '/').'/'.$tenant->id.'-'.$spec->filename($stichtag);
        Storage::disk(config('qdvs.disk'))->put($pfad, $inhalt);

        $export->update(['status' => 'exportiert', 'pfad' => $pfad]);

        return $export;
    }
}
```

- [ ] **Step 3: Tests grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/BuildQdvsExportTest.php
git add -A && git commit -m "feat(qdvs): build export orchestration (assemble→validate→render→store)"
```

---

## Task 7: Export-UI (Livewire) + Download

**Files:**
- Create: `app/Livewire/Qdvs/Export.php` (+ view)
- Modify: `routes/web.php` (Seite + Download-Route), `layouts.app` (Nav „QDVS-Export", admin/pflegefachkraft)
- Test: `tests/Feature/Qdvs/ExportPageTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Qdvs/ExportPageTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{IcdCode, Resident};
use App\Livewire\Qdvs\Export;
use App\Domains\Qdvs\Models\QdvsExport;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260123456']);
    app(CurrentTenant::class)->set($t);
    $this->lead = User::factory()->create(['tenant_id' => $t->id]);
    $this->lead->assignRole('pflegefachkraft');
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Hypertonie']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);
});

it('erstellt einen Export über die UI', function () {
    Livewire::actingAs($this->lead)->test(Export::class)
        ->set('stichtag', '2026-02-15')->set('specKey', 'csv-v1')
        ->call('erstellen')->assertHasNoErrors();

    expect(QdvsExport::where('status', 'exportiert')->count())->toBe(1);
});
```

- [ ] **Step 2: Komponente**

`app/Livewire/Qdvs/Export.php`:
```php
<?php
namespace App\Livewire\Qdvs;

use App\Domains\Qdvs\Actions\BuildQdvsExport;
use App\Domains\Qdvs\Models\QdvsExport;
use App\Domains\Qdvs\Support\SpecRegistry;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Export extends Component
{
    public string $stichtag;
    public string $specKey = 'csv-v1';

    public function mount(): void { $this->stichtag = today()->toDateString(); }

    public function erstellen(BuildQdvsExport $build): void
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'pflegefachkraft', 'super-admin']), 403);
        $this->validate(['stichtag' => ['required', 'date'], 'specKey' => ['required', 'string']]);
        $build->handle($this->stichtag, $this->specKey);
        session()->flash('status', 'Export erstellt.');
    }

    public function render(SpecRegistry $registry)
    {
        return view('livewire.qdvs.export', [
            'specs' => $registry->all(),
            'exports' => QdvsExport::latest('id')->take(20)->get(),
        ]);
    }
}
```

- [ ] **Step 3: View** — Formular (Stichtag, Spec-`<select>` aus `$specs` mit `->label()`), Button „Export erstellen"; Tabelle der letzten Exporte (Stichtag, Spec, Status-Badge, Bewohner-Anzahl, bei `fehler` die Issues ausklappbar, bei `exportiert` Download-Link auf `route('qdvs.download', $export)`). Muster aus `admin.css`.

- [ ] **Step 4: Routen**

`routes/web.php` (im `['auth','tenant']`-Block):
```php
Route::get('/qdvs', \App\Livewire\Qdvs\Export::class)->name('qdvs.export');
Route::get('/qdvs/{export}/download', function (\App\Domains\Qdvs\Models\QdvsExport $export) {
    abort_unless($export->pfad && \Illuminate\Support\Facades\Storage::disk(config('qdvs.disk'))->exists($export->pfad), 404);
    return \Illuminate\Support\Facades\Storage::disk(config('qdvs.disk'))
        ->download($export->pfad, basename($export->pfad));
})->name('qdvs.download');
```
> Route-Model-Binding `{export}` ist tenant-scoped (BaseModel-Scope), sichert gegen fremden Download. Nav-Eintrag „QDVS-Export" → `qdvs.export` (admin/pflegefachkraft).

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Qdvs/ExportPageTest.php
git add -A && git commit -m "feat(qdvs): export ui + tenant-scoped download"
```

---

## Task 8: Gesamtsuite + Abschluss

**Files:** keine neuen — Verifikation.

- [ ] **Step 1: Frisch migrieren/seeden**

Run: `php artisan migrate:fresh --seed`
Expected: ohne Fehler (Plan 1–7).

- [ ] **Step 2: Gesamte Suite + Arch**

Run: `./vendor/bin/pest`
Expected: ALLE PASS. Arch-Tests grün (Qdvs/Quality/Medication hängen nicht an `App\Http`).

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "test(qdvs): full suite green across all domains" || echo "nothing to commit"
```

---

## Self-Review-Ergebnis (Plan 7)

- **Spec-Abdeckung:** Stichtag-Kohorte (Plan 6 `Cohort`) → Tasks 3,6. Pseudonymisierte Datenpakete (OPDE QdvsResidentInfoObject, DSGVO) → Tasks 1,3. Datenqualitäts-Validierung (OPDE DAS_REGELN, Fehler blockt Export) → Tasks 4,6. Multi-Spec-Adapter (OPDE Spec 14/21/30/40) → Tasks 2 (Contract+Registry+CSV-v1); XML/DAS-konforme Specs als weitere `QdvsSpec`-Klassen nachrüstbar. Audit-Protokoll + Download → Tasks 5,6,7.
- **Platzhalter:** keine — Assemble/Validate/Build/Render vollständig als Code; nur die eine Export-View verweist auf das bestehende `admin.css`-Muster.
- **Typ-Konsistenz:** `QdvsResidentPackage`/`ValidationIssue`, `QdvsSpec::{key,label,render,filename,mimeType}`, `SpecRegistry::{all,get,default}`, `AssemblePackages::handle(Cohort)`, `QdvsValidator::{validate,hatBlockierendeFehler}`, `BuildQdvsExport::handle(stichtag, specKey)` durchgängig identisch und kompatibel zu Plan 6 (`Cohort`, `QualityIndicator`, `CareEvent`).
- **Datenschutz:** Export trägt `pseudonym` (`R-<id>`), keinen Klarnamen; Download tenant-scoped.

## Gesamt-Reihenfolge (Pläne 4–7)
1. **Plan 4** — Mehrmandanten + Admin-UI (Fundament)
2. **Plan 5** — Medikation / BHP
3. **Plan 6** — Controlling / QMS (liefert Indikator-Datengrundlage)
4. **Plan 7** — QDVS-Export (baut auf Plan 6)
