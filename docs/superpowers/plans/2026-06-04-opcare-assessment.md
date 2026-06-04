# OPCare — Plan 10: Assessment-Modul (Skalen / Scoring / Fälligkeiten) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein **Assessment-Modul** mit standardisierten Risiko-Skalen (Braden für Dekubitus, Sturzrisiko, BESD-Schmerz), bestehend aus versionierten **Instrumenten** (Items + Antwortoptionen), durchgeführten **Assessments** (Antworten je Bewohner) mit deterministischem **Scoring** und Risikoeinstufung, **Fälligkeiten** (nächste Durchführung) sowie Verknüpfung zu SIS-`RiskItem`s und Quality-Indikatoren.

**Architecture:** Neue Domäne `App\Domains\Assessment`. Ein `Instrument` (z. B. „Braden-Skala", Version N) bündelt `InstrumentItem`s; jedes Item trägt gewichtete `AssessmentOption`s (Punktwert je Antwort). Instrumente sind **append-only versioniert** (`Versionable`) — eine Änderung erzeugt eine neue Version, laufende Assessments behalten ihre Instrument-Version. Ein durchgeführtes `Assessment` referenziert eine Instrument-Version + Bewohner und speichert die gewählten Options als `AssessmentAnswer`; `ScoreCalculator` summiert die Punkte und `RiskBands` (je Instrument konfiguriert) bildet den Score auf eine Risikostufe (z. B. „hoch/mittel/gering") ab. `Assessment` ist ebenfalls `Versionable` (Wiederholungsmessung = neue Version), trägt `faellig_am` für die nächste Durchführung. Optionale Verknüpfung: ein abgeschlossenes Assessment kann einen passenden SIS-`RiskItem` setzen/aktualisieren und bei kritischem Score ein `CareEvent` (Quality) auslösen — beides als Action, lose gekoppelt. Alles tenant-scoped via `BaseModel`.

**Tech Stack:** wie Plan 1–9. Livewire 4, spatie-data, spatie-activitylog, `Versionable`-Concern (Plan 1), Pest 4 + Arch. Fälligkeiten nutzen `App\Domains\Scheduling` (Plan 8) sofern vorhanden, sonst ein simples `faellig_am`-Datum.

**Voraussetzung:** Plan 1 (Resident, BaseModel, CurrentTenant, Versionable, Rollen), Plan 2 (CarePlanning: `SisAssessment`, `RiskItem`, `RiskType`). Plan 6 empfohlen (Quality `CareEvent`/`QualityIndicator` für die Eskalation). Plan 8 optional (Fälligkeits-Kalenderanbindung).

**Referenz:** OPDE `resinfo/*`-XMLs (Assessment-Instrumente: Braden/Dekubitus, Sturzrisiko, BESD-Schmerz) unter `~/Desktop/WebDev/Offene-Pflege.de/src/main/resources/` — Vorlage für Items/Optionen/Punktwerte, **kein Code-Port**. Bestehend: `App\Domains\CarePlanning\Models\RiskItem` (`risiko: RiskType`, `eingeschaetzt: bool`, `begruendung`), `RiskType` (dekubitus/sturz/schmerz/ernaehrung/inkontinenz/kontraktur), `App\Domains\Quality\Models\CareEvent` (`indicator: QualityIndicator`, `severity: EventSeverity`, `datum`, `details: array`). `Versionable`: `scopeCurrent()`, `reviseWith(array)`, Felder `version`+`superseded_by`, `$attributes = ['version' => 1]`.

---

## Hinweise für ausführende Subagents

- **Tests laufen auf SQLite in-memory** (`phpunit.xml`: `DB_CONNECTION=sqlite`, `SPEECH_FAKE=true`, `QUEUE_CONNECTION=sync`).
- **Pest gibt JSON aus** (laravel/pao). Ergebnis lesen:
  ```bash
  ./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['tests'],d['passed'],d.get('failed'))"
  ```
- **Vor jedem Commit:** `vendor/bin/pint`.
- **CurrentTenant** in jedem Feature-Test setzen.
- **`Versionable`-Vertrag** (am Ist-Code verifiziert): Model braucht Spalten `version` (int) + `superseded_by` (nullable FK auf sich selbst), `protected $attributes = ['version' => 1]`, optional `status` im `$fillable` (dann setzt `reviseWith` automatisch `aktiv`/`abgelöst`). `scopeCurrent()` filtert `superseded_by IS NULL`.
- **Scoring ist eine reine Funktion** (kein DB-Zugriff im Kern-Algorithmus) → in `tests/Unit` testbar.
- **Rollen** (`RolesSeeder`): Durchführen eines Assessments dürfen `admin`/`pflegefachkraft`/`pflegehilfskraft` (+super-admin); Instrumente verwalten (anlegen/revidieren) ist Leitung: `admin`/`pflegefachkraft` (+super-admin). Guard in mount UND Action.
- **Bezug zu existierenden Risiken:** `Assessment.risk_type` ist ein `RiskType` (wiederverwendet aus CarePlanning) — die drei Start-Instrumente mappen: Braden→`RiskType::Dekubitus`, Sturz→`RiskType::Sturz`, BESD→`RiskType::Schmerz`.

---

## File Structure (Plan 10)

```
app/Domains/Assessment/
├── Enums/{RiskBand, ScaleDirection}.php
├── Models/{Instrument, InstrumentItem, AssessmentOption, Assessment, AssessmentAnswer}.php
├── Data/{InstrumentData, ItemData, OptionData, AssessmentInputData}.php
├── Support/{ScoreCalculator, RiskBandResolver}.php
├── Actions/{ConductAssessment, ReviseAssessment, SyncRiskItem, EscalateToQuality}.php
├── Database/{Factories/*, Seeders/InstrumentSeeder.php}
├── Support/InstrumentReferenceData.php   # Braden/Sturz/BESD-Items+Optionen (aus OPDE resinfo)
└── Policies/{InstrumentPolicy, AssessmentPolicy}.php
app/Livewire/Assessment/{Instrumente, AssessmentDurchfuehren, AssessmentVerlauf}.php (+ views)
database/migrations/2026_06_04_0010xx_*.php
tests/Feature/Assessment/... , tests/Unit/Assessment/ScoreCalculatorTest.php
```

**Geänderte Bestandsdateien:** `routes/web.php`, `resources/views/layouts/app.blade.php`, `resources/views/livewire/resident-show.blade.php` (Assessment-Verlinkung), `database/seeders/DatabaseSeeder.php` (InstrumentSeeder je Mandant).

---

## Task 1: Enums + Migrationen

**Files:**
- Create: `app/Domains/Assessment/Enums/{RiskBand,ScaleDirection}.php`
- Create migrations: `...001001_create_instruments_table.php`, `...001002_create_instrument_items_table.php`, `...001003_create_assessment_options_table.php`, `...001004_create_assessments_table.php`, `...001005_create_assessment_answers_table.php`
- Test: `tests/Feature/Assessment/SchemaTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/SchemaTest.php`:
```php
<?php

use Illuminate\Support\Facades\Schema;

it('legt die Assessment-Tabellen mit den erwarteten Spalten an', function () {
    expect(Schema::hasColumns('instruments', ['tenant_id', 'name', 'risk_type', 'direction', 'risk_bands', 'version', 'superseded_by', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('instrument_items', ['tenant_id', 'instrument_id', 'label', 'reihenfolge']))->toBeTrue()
        ->and(Schema::hasColumns('assessment_options', ['tenant_id', 'instrument_item_id', 'label', 'punkte']))->toBeTrue()
        ->and(Schema::hasColumns('assessments', ['tenant_id', 'resident_id', 'instrument_id', 'score', 'risk_band', 'durchgefuehrt_am', 'faellig_am', 'version', 'superseded_by', 'status', 'created_by']))->toBeTrue()
        ->and(Schema::hasColumns('assessment_answers', ['tenant_id', 'assessment_id', 'instrument_item_id', 'assessment_option_id', 'punkte']))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/SchemaTest.php`
Expected: FAIL.

- [ ] **Step 3: Enums**

`app/Domains/Assessment/Enums/RiskBand.php`:
```php
<?php

namespace App\Domains\Assessment\Enums;

enum RiskBand: string
{
    case Kein = 'kein';
    case Gering = 'gering';
    case Mittel = 'mittel';
    case Hoch = 'hoch';
    case SehrHoch = 'sehr_hoch';

    public function label(): string
    {
        return match ($this) {
            self::Kein => 'Kein Risiko',
            self::Gering => 'Geringes Risiko',
            self::Mittel => 'Mittleres Risiko',
            self::Hoch => 'Hohes Risiko',
            self::SehrHoch => 'Sehr hohes Risiko',
        };
    }

    public function istKritisch(): bool
    {
        return in_array($this, [self::Hoch, self::SehrHoch], true);
    }
}
```

`app/Domains/Assessment/Enums/ScaleDirection.php`:
```php
<?php

namespace App\Domains\Assessment\Enums;

enum ScaleDirection: string
{
    // WHY: bei Braden bedeutet ein NIEDRIGER Score höheres Risiko (lower_is_worse),
    // bei Sturz-/Schmerzskalen ein HÖHERER Score höheres Risiko (higher_is_worse).
    case LowerIsWorse = 'lower_is_worse';
    case HigherIsWorse = 'higher_is_worse';
}
```

- [ ] **Step 4: Migration `instruments`**

`database/migrations/2026_06_04_001001_create_instruments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('risk_type');     // RiskType-Wert (dekubitus|sturz|schmerz|...)
            $table->string('direction');     // ScaleDirection
            // [{ "band": "hoch", "min": null, "max": 12 }, ...] — Score-Schwellen je Risikostufe
            $table->json('risk_bands');
            $table->string('beschreibung')->nullable();
            $table->unsignedInteger('intervall_tage')->default(90); // Standard-Wiedervorlage
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('superseded_by')->nullable()->constrained('instruments')->nullOnDelete();
            $table->string('status')->default('aktiv');
            $table->timestamps();
            $table->index(['tenant_id', 'risk_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
```

- [ ] **Step 5: Migration `instrument_items`**

`database/migrations/2026_06_04_001002_create_instrument_items_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('hilfetext')->nullable();
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_items');
    }
};
```

- [ ] **Step 6: Migration `assessment_options`**

`database/migrations/2026_06_04_001003_create_assessment_options_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_item_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('punkte');
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_options');
    }
};
```

- [ ] **Step 7: Migration `assessments`**

`database/migrations/2026_06_04_001004_create_assessments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained();
            $table->integer('score')->nullable();
            $table->string('risk_band')->nullable();
            $table->date('durchgefuehrt_am');
            $table->date('faellig_am')->nullable();
            $table->text('notiz')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('superseded_by')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('status')->default('aktiv');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
```

- [ ] **Step 8: Migration `assessment_answers`**

`database/migrations/2026_06_04_001005_create_assessment_answers_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_item_id')->constrained();
            $table->foreignId('assessment_option_id')->constrained();
            $table->integer('punkte');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
```

- [ ] **Step 9: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/SchemaTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint app/Domains/Assessment database/migrations
git add app/Domains/Assessment database/migrations tests/Feature/Assessment/SchemaTest.php
git commit -m "feat(assessment): enums + migrations (instruments, items, options, assessments, answers)"
```

---

## Task 2: Models (Instrument, InstrumentItem, AssessmentOption, Assessment, AssessmentAnswer)

**Files:**
- Create: `app/Domains/Assessment/Models/{Instrument,InstrumentItem,AssessmentOption,Assessment,AssessmentAnswer}.php`
- Create: `app/Domains/Assessment/Database/Factories/{InstrumentFactory,AssessmentFactory}.php`
- Test: `tests/Feature/Assessment/ModelTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/ModelTest.php`:
```php
<?php

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('castet Instrument-Felder und ist versionierbar/tenant-scoped', function () {
    $instr = Instrument::create([
        'name' => 'Braden', 'risk_type' => RiskType::Dekubitus, 'direction' => ScaleDirection::LowerIsWorse,
        'risk_bands' => [['band' => 'hoch', 'min' => null, 'max' => 12]],
    ]);

    expect($instr->risk_type)->toBe(RiskType::Dekubitus)
        ->and($instr->direction)->toBe(ScaleDirection::LowerIsWorse)
        ->and($instr->version)->toBe(1)
        ->and(Instrument::current()->count())->toBe(1);

    $v2 = $instr->reviseWith(['name' => 'Braden (rev.)']);
    expect($v2->version)->toBe(2)
        ->and(Instrument::current()->count())->toBe(1)
        ->and($instr->fresh()->isSuperseded())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/ModelTest.php`
Expected: FAIL.

- [ ] **Step 3: `Instrument` model**

`app/Domains/Assessment/Models/Instrument.php`:
```php
<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\InstrumentFactory;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'name', 'risk_type', 'direction', 'risk_bands', 'beschreibung',
        'intervall_tage', 'version', 'superseded_by', 'status',
    ];

    protected $casts = [
        'risk_type' => RiskType::class,
        'direction' => ScaleDirection::class,
        'risk_bands' => 'array',
        'version' => 'integer',
        'intervall_tage' => 'integer',
    ];

    protected $attributes = ['version' => 1, 'status' => 'aktiv'];

    public function items(): HasMany
    {
        return $this->hasMany(InstrumentItem::class)->orderBy('reihenfolge');
    }

    protected static function newFactory(): InstrumentFactory
    {
        return InstrumentFactory::new();
    }
}
```

- [ ] **Step 4: `InstrumentItem` model**

`app/Domains/Assessment/Models/InstrumentItem.php`:
```php
<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_id', 'label', 'hilfetext', 'reihenfolge'];

    protected $casts = ['reihenfolge' => 'integer'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssessmentOption::class)->orderBy('reihenfolge');
    }
}
```

- [ ] **Step 5: `AssessmentOption` model**

`app/Domains/Assessment/Models/AssessmentOption.php`:
```php
<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentOption extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_item_id', 'label', 'punkte', 'reihenfolge'];

    protected $casts = ['punkte' => 'integer', 'reihenfolge' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InstrumentItem::class, 'instrument_item_id');
    }
}
```

- [ ] **Step 6: `Assessment` model**

`app/Domains/Assessment/Models/Assessment.php`:
```php
<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\AssessmentFactory;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'instrument_id', 'score', 'risk_band',
        'durchgefuehrt_am', 'faellig_am', 'notiz', 'version', 'superseded_by', 'status', 'created_by',
    ];

    protected $casts = [
        'risk_band' => RiskBand::class,
        'score' => 'integer',
        'durchgefuehrt_am' => 'date',
        'faellig_am' => 'date',
        'version' => 'integer',
    ];

    protected $attributes = ['version' => 1, 'status' => 'aktiv'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function istFaellig(): bool
    {
        return $this->faellig_am !== null && $this->faellig_am->isToday() || ($this->faellig_am?->isPast() ?? false);
    }

    protected static function newFactory(): AssessmentFactory
    {
        return AssessmentFactory::new();
    }
}
```

- [ ] **Step 7: `AssessmentAnswer` model**

`app/Domains/Assessment/Models/AssessmentAnswer.php`:
```php
<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends BaseModel
{
    protected $fillable = ['tenant_id', 'assessment_id', 'instrument_item_id', 'assessment_option_id', 'punkte'];

    protected $casts = ['punkte' => 'integer'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssessmentOption::class, 'assessment_option_id');
    }
}
```

- [ ] **Step 8: Factories**

`app/Domains/Assessment/Database/Factories/InstrumentFactory.php`:
```php
<?php

namespace App\Domains\Assessment\Database\Factories;

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition(): array
    {
        return [
            'name' => 'Braden-Skala',
            'risk_type' => RiskType::Dekubitus,
            'direction' => ScaleDirection::LowerIsWorse,
            'risk_bands' => [
                ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
                ['band' => 'hoch', 'min' => 10, 'max' => 12],
                ['band' => 'mittel', 'min' => 13, 'max' => 14],
                ['band' => 'gering', 'min' => 15, 'max' => 18],
                ['band' => 'kein', 'min' => 19, 'max' => null],
            ],
            'intervall_tage' => 90,
        ];
    }
}
```

`app/Domains/Assessment/Database/Factories/AssessmentFactory.php`:
```php
<?php

namespace App\Domains\Assessment\Database\Factories;

use App\Domains\Assessment\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'durchgefuehrt_am' => now()->toDateString(),
            'created_by' => 1,
        ];
    }
}
```

- [ ] **Step 9: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/ModelTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint app/Domains/Assessment
git add app/Domains/Assessment tests/Feature/Assessment/ModelTest.php
git commit -m "feat(assessment): Models (Instrument/Item/Option/Assessment/Answer) + Versionable + factories"
```

---

## Task 3: ScoreCalculator + RiskBandResolver (reine Funktionen)

**Files:**
- Create: `app/Domains/Assessment/Support/{ScoreCalculator,RiskBandResolver}.php`
- Test: `tests/Unit/Assessment/ScoreCalculatorTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/Assessment/ScoreCalculatorTest.php`:
```php
<?php

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Support\RiskBandResolver;
use App\Domains\Assessment\Support\ScoreCalculator;

it('summiert die Punkte der gewählten Optionen', function () {
    expect((new ScoreCalculator)->sum([3, 2, 4, 1]))->toBe(10);
});

it('bildet den Braden-Score (lower_is_worse) auf die Risikostufe ab', function () {
    $bands = [
        ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
        ['band' => 'hoch', 'min' => 10, 'max' => 12],
        ['band' => 'mittel', 'min' => 13, 'max' => 14],
        ['band' => 'gering', 'min' => 15, 'max' => 18],
        ['band' => 'kein', 'min' => 19, 'max' => null],
    ];
    $resolver = new RiskBandResolver;

    expect($resolver->resolve(11, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::Hoch)
        ->and($resolver->resolve(8, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::SehrHoch)
        ->and($resolver->resolve(20, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::Kein);
});

it('bildet eine higher_is_worse-Skala korrekt ab', function () {
    $bands = [
        ['band' => 'gering', 'min' => null, 'max' => 3],
        ['band' => 'mittel', 'min' => 4, 'max' => 6],
        ['band' => 'hoch', 'min' => 7, 'max' => null],
    ];

    expect((new RiskBandResolver)->resolve(8, $bands, ScaleDirection::HigherIsWorse))->toBe(RiskBand::Hoch);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Assessment/ScoreCalculatorTest.php`
Expected: FAIL.

- [ ] **Step 3: `ScoreCalculator`**

`app/Domains/Assessment/Support/ScoreCalculator.php`:
```php
<?php

namespace App\Domains\Assessment\Support;

class ScoreCalculator
{
    /** @param array<int, int> $punkte */
    public function sum(array $punkte): int
    {
        return array_sum($punkte);
    }
}
```

- [ ] **Step 4: `RiskBandResolver`**

`app/Domains/Assessment/Support/RiskBandResolver.php`:
```php
<?php

namespace App\Domains\Assessment\Support;

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Enums\ScaleDirection;

class RiskBandResolver
{
    /**
     * @param  array<int, array{band:string, min:?int, max:?int}>  $bands
     *
     * Findet das Band, dessen [min, max]-Intervall den Score einschließt (null = offenes Ende).
     * Die `direction` ist dokumentarisch (die Schwellen sind bereits skalenkonform definiert);
     * sie wird zur Validierung herangezogen, falls kein Band passt.
     */
    public function resolve(int $score, array $bands, ScaleDirection $direction): RiskBand
    {
        foreach ($bands as $band) {
            $min = $band['min'];
            $max = $band['max'];
            if (($min === null || $score >= $min) && ($max === null || $score <= $max)) {
                return RiskBand::from($band['band']);
            }
        }

        // WHY: kein definiertes Band getroffen → konservativ das schlechtere Ende annehmen.
        return $direction === ScaleDirection::LowerIsWorse ? RiskBand::SehrHoch : RiskBand::Hoch;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Assessment/ScoreCalculatorTest.php`
Expected: PASS (3 Tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Domains/Assessment
git add app/Domains/Assessment/Support tests/Unit/Assessment/ScoreCalculatorTest.php
git commit -m "feat(assessment): ScoreCalculator + RiskBandResolver (reine Scoring-Funktionen)"
```

---

## Task 4: DTOs + ConductAssessment (Antworten → Score → Band → Fälligkeit) + ReviseAssessment

**Files:**
- Create: `app/Domains/Assessment/Data/{AssessmentInputData}.php`
- Create: `app/Domains/Assessment/Actions/{ConductAssessment,ReviseAssessment}.php`
- Test: `tests/Feature/Assessment/ConductAssessmentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/ConductAssessmentTest.php`:
```php
<?php

use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Models\InstrumentItem;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->instrument = Instrument::factory()->create(['intervall_tage' => 30]);
    // zwei Items mit je zwei Optionen (Braden: niedrig = schlecht)
    foreach (['Mobilität', 'Feuchtigkeit'] as $i => $label) {
        $item = InstrumentItem::create(['instrument_id' => $this->instrument->id, 'label' => $label, 'reihenfolge' => $i]);
        AssessmentOption::create(['instrument_item_id' => $item->id, 'label' => 'stark eingeschränkt', 'punkte' => 1]);
        AssessmentOption::create(['instrument_item_id' => $item->id, 'label' => 'normal', 'punkte' => 4]);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('berechnet Score + Band, persistiert Antworten und setzt die Fälligkeit', function () {
    $items = $this->instrument->items()->with('options')->get();
    // beide Items „stark eingeschränkt" (1 Punkt) → Score 2 → sehr hohes Risiko (max 9)
    $answers = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->first()->id])->all();

    $assessment = (new ConductAssessment)->handle(new AssessmentInputData(
        resident_id: $this->resident->id,
        instrument_id: $this->instrument->id,
        created_by: $this->user->id,
        answers: $answers,
    ));

    expect($assessment->score)->toBe(2)
        ->and($assessment->risk_band)->toBe(RiskBand::SehrHoch)
        ->and($assessment->answers()->count())->toBe(2)
        ->and($assessment->faellig_am->toDateString())->toBe('2026-07-15'); // +30 Tage
});

it('revidiert ein Assessment append-only (neue Version, alte abgelöst)', function () {
    $items = $this->instrument->items()->with('options')->get();
    $low = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->first()->id])->all();
    $high = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->last()->id])->all();

    $action = new ConductAssessment;
    $v1 = $action->handle(new AssessmentInputData($this->resident->id, $this->instrument->id, $this->user->id, $low));
    $v2 = (new \App\Domains\Assessment\Actions\ReviseAssessment)->handle($v1, new AssessmentInputData(
        $this->resident->id, $this->instrument->id, $this->user->id, $high,
    ));

    expect($v2->version)->toBe(2)
        ->and($v1->fresh()->isSuperseded())->toBeTrue()
        ->and(Assessment::current()->where('resident_id', $this->resident->id)->count())->toBe(1)
        ->and($v2->score)->toBe(8);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/ConductAssessmentTest.php`
Expected: FAIL.

- [ ] **Step 3: `AssessmentInputData` DTO**

`app/Domains/Assessment/Data/AssessmentInputData.php`:
```php
<?php

namespace App\Domains\Assessment\Data;

use Spatie\LaravelData\Data;

class AssessmentInputData extends Data
{
    /**
     * @param  array<int, int>  $answers  instrument_item_id => assessment_option_id
     */
    public function __construct(
        public int $resident_id,
        public int $instrument_id,
        public int $created_by,
        public array $answers,
        public ?string $durchgefuehrt_am = null,
        public ?string $notiz = null,
    ) {}
}
```

- [ ] **Step 4: `ConductAssessment` Action**

`app/Domains/Assessment/Actions/ConductAssessment.php`:
```php
<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Support\RiskBandResolver;
use App\Domains\Assessment\Support\ScoreCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConductAssessment
{
    public function __construct(
        private ScoreCalculator $calculator = new ScoreCalculator,
        private RiskBandResolver $resolver = new RiskBandResolver,
    ) {}

    public function handle(AssessmentInputData $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $instrument = Instrument::with('items')->findOrFail($data->instrument_id);

            // Punkte der gewählten Optionen laden (nur Optionen, die zu Items dieses Instruments gehören)
            $erlaubteItems = $instrument->items->pluck('id')->all();
            $optionen = AssessmentOption::whereIn('id', array_values($data->answers))
                ->whereIn('instrument_item_id', $erlaubteItems)
                ->get()->keyBy('id');

            $punkte = [];
            foreach ($data->answers as $itemId => $optionId) {
                $option = $optionen->get($optionId);
                if ($option && (int) $option->instrument_item_id === (int) $itemId) {
                    $punkte[$itemId] = $option->punkte;
                }
            }

            $score = $this->calculator->sum(array_values($punkte));
            $band = $this->resolver->resolve($score, $instrument->risk_bands, $instrument->direction);

            $durchgefuehrt = Carbon::parse($data->durchgefuehrt_am ?? now()->toDateString());

            $assessment = Assessment::create([
                'resident_id' => $data->resident_id,
                'instrument_id' => $instrument->id,
                'score' => $score,
                'risk_band' => $band,
                'durchgefuehrt_am' => $durchgefuehrt->toDateString(),
                'faellig_am' => $durchgefuehrt->copy()->addDays($instrument->intervall_tage)->toDateString(),
                'notiz' => $data->notiz,
                'created_by' => $data->created_by,
            ]);

            foreach ($punkte as $itemId => $p) {
                $assessment->answers()->create([
                    'instrument_item_id' => $itemId,
                    'assessment_option_id' => $data->answers[$itemId],
                    'punkte' => $p,
                ]);
            }

            return $assessment->fresh('answers');
        });
    }
}
```

- [ ] **Step 5: `ReviseAssessment` Action**

`app/Domains/Assessment/Actions/ReviseAssessment.php`:
```php
<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use Illuminate\Support\Facades\DB;

class ReviseAssessment
{
    public function __construct(private ConductAssessment $conduct = new ConductAssessment) {}

    // WHY: Wiederholungsmessung ist append-only — neue Durchführung als Folgeversion, alte wird abgelöst.
    public function handle(Assessment $previous, AssessmentInputData $data): Assessment
    {
        return DB::transaction(function () use ($previous, $data) {
            $neu = $this->conduct->handle($data);
            $neu->forceFill(['version' => $previous->version + 1])->save();
            $previous->forceFill(['superseded_by' => $neu->id, 'status' => 'abgelöst'])->save();

            return $neu;
        });
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/ConductAssessmentTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Domains/Assessment
git add app/Domains/Assessment tests/Feature/Assessment/ConductAssessmentTest.php
git commit -m "feat(assessment): ConductAssessment (Scoring+Fälligkeit) + ReviseAssessment (append-only)"
```

---

## Task 5: SyncRiskItem + EscalateToQuality (Verknüpfung zu SIS + Controlling)

**Ziel:** Ein abgeschlossenes Assessment kann (a) das passende SIS-`RiskItem` der aktuellen `SisAssessment` des Bewohners setzen/aktualisieren und (b) bei kritischem Band ein `CareEvent` (Quality) erzeugen. Beides als eigene, lose gekoppelte Actions; `ConductAssessment` bleibt unverändert (Single-Responsibility), die Verknüpfung ruft die UI explizit auf.

**Files:**
- Create: `app/Domains/Assessment/Actions/{SyncRiskItem,EscalateToQuality}.php`
- Test: `tests/Feature/Assessment/LinkageTest.php`

**Vorab-Schritt (Pflicht):** `app/Domains/Quality/Models/CareEvent.php` + `app/Domains/Quality/Enums/{QualityIndicator,EventSeverity}.php` lesen, um die echten Enum-Cases zu verwenden (unten als `QualityIndicator::tryFrom(...)` defensiv gelöst). `RiskItem`-Felder sind verifiziert: `sis_assessment_id`, `risiko` (RiskType), `eingeschaetzt` (bool), `begruendung`.

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/LinkageTest.php`:
```php
<?php

use App\Domains\Assessment\Actions\SyncRiskItem;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sis = SisAssessment::create([
        'resident_id' => $this->resident->id, 'created_by' => $this->user->id,
        'erstellt_am' => now()->toDateString(), 'status' => 'aktiv',
    ]);
    $this->instrument = Instrument::factory()->create(['risk_type' => RiskType::Dekubitus]);
});

it('setzt das passende SIS-RiskItem aus einem kritischen Assessment', function () {
    $assessment = Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 8, 'risk_band' => RiskBand::SehrHoch, 'created_by' => $this->user->id,
    ]);

    (new SyncRiskItem)->handle($assessment);

    $risk = RiskItem::where('sis_assessment_id', $this->sis->id)->where('risiko', RiskType::Dekubitus)->first();
    expect($risk)->not->toBeNull()
        ->and($risk->eingeschaetzt)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/LinkageTest.php`
Expected: FAIL.

- [ ] **Step 3: `SyncRiskItem` Action**

`app/Domains/Assessment/Actions/SyncRiskItem.php`:
```php
<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;

class SyncRiskItem
{
    // WHY: ein Assessment-Ergebnis soll im SIS-Risikoteil sichtbar werden. Es schreibt in die
    // aktuelle (nicht abgelöste) SisAssessment des Bewohners; existiert keine, passiert nichts.
    public function handle(Assessment $assessment): ?RiskItem
    {
        $assessment->loadMissing('instrument');
        $riskType = $assessment->instrument->risk_type;

        $sis = SisAssessment::current()
            ->where('resident_id', $assessment->resident_id)
            ->latest('erstellt_am')
            ->first();

        if (! $sis) {
            return null;
        }

        $band = $assessment->risk_band;
        $eingeschaetzt = $band !== null && $band->istKritisch();

        $risk = RiskItem::firstOrNew([
            'sis_assessment_id' => $sis->id,
            'risiko' => $riskType,
        ]);
        $risk->eingeschaetzt = $eingeschaetzt;
        $risk->begruendung = sprintf(
            '%s: Score %d (%s) am %s',
            $assessment->instrument->name,
            $assessment->score,
            $band?->label() ?? '—',
            $assessment->durchgefuehrt_am?->format('d.m.Y') ?? '—',
        );
        $risk->save();

        return $risk;
    }
}
```

- [ ] **Step 4: `EscalateToQuality` Action** (Enum-Cases am Ist-Code verifizieren)

`app/Domains/Assessment/Actions/EscalateToQuality.php`:
```php
<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;

class EscalateToQuality
{
    // WHY: bei kritischem Risiko ein Controlling-Ereignis dokumentieren (z. B. Dekubitusrisiko hoch).
    // Mappt RiskType→QualityIndicator defensiv; ohne passenden Indikator wird nicht eskaliert.
    public function handle(Assessment $assessment): ?CareEvent
    {
        $assessment->loadMissing('instrument');
        if (! $assessment->risk_band?->istKritisch()) {
            return null;
        }

        $indicator = QualityIndicator::tryFrom($assessment->instrument->risk_type->value);
        if (! $indicator) {
            return null; // kein 1:1-Indikator vorhanden → keine Eskalation
        }

        return CareEvent::create([
            'resident_id' => $assessment->resident_id,
            'indicator' => $indicator,
            'datum' => $assessment->durchgefuehrt_am?->toDateString() ?? now()->toDateString(),
            'severity' => EventSeverity::cases()[0] ?? null,
            'details' => [
                'quelle' => 'assessment',
                'instrument' => $assessment->instrument->name,
                'score' => $assessment->score,
                'band' => $assessment->risk_band->value,
            ],
            'reported_by' => $assessment->created_by,
        ]);
    }
}
```

> **Hinweis:** `QualityIndicator`/`EventSeverity` zuerst lesen. Falls die Indikator-Werte nicht den `RiskType`-Werten entsprechen (wahrscheinlich), das Mapping explizit ausschreiben (`match`), statt `tryFrom`. Falls `EventSeverity` keine sinnvolle Default-Stufe hat, die passende Stufe (z. B. `EventSeverity::Hoch`) direkt benennen. Diese Action ist **optional** für die Kern-Funktionalität — bei Unklarheit über die Quality-Enums kann sie als TODO im Verlauf-UI weggelassen werden, ohne den Plan zu blockieren (dann diesen Test überspringen und im Commit vermerken).

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/LinkageTest.php`
Expected: PASS (`SyncRiskItem`-Test). `EscalateToQuality` ggf. mit eigenem Test, sobald die Enum-Cases bestätigt sind.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Domains/Assessment
git add app/Domains/Assessment/Actions tests/Feature/Assessment/LinkageTest.php
git commit -m "feat(assessment): SyncRiskItem (SIS-Verknüpfung) + EscalateToQuality (Controlling)"
```

---

## Task 6: InstrumentReferenceData + InstrumentSeeder (Braden / Sturz / BESD)

**Ziel:** Drei einsatzfertige Start-Instrumente je Mandant seeden, abgeleitet aus den OPDE-`resinfo`-Vorlagen. Items/Optionen/Punktwerte als statisches PHP-Array (analog `MedicationReferenceData`), idempotent über den Instrument-Namen.

**Files:**
- Create: `app/Domains/Assessment/Support/InstrumentReferenceData.php`
- Create: `app/Domains/Assessment/Database/Seeders/InstrumentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Assessment/InstrumentSeederTest.php`

**Vorab-Schritt (empfohlen):** OPDE-`resinfo`-XMLs sichten (`~/Desktop/WebDev/Offene-Pflege.de/src/main/resources/`) für die exakten Item-Texte/Punktwerte. Sind sie nicht greifbar, die im Code hinterlegten anerkannten Standardwerte (Braden 6 Items je 1–4, Sturz-Checkliste, BESD 5 Items je 0–2) verwenden.

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/InstrumentSeederTest.php`:
```php
<?php

use App\Domains\Assessment\Database\Seeders\InstrumentSeeder;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Models\InstrumentItem;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet Braden/Sturz/BESD idempotent mit Items und Optionen', function () {
    $this->seed(InstrumentSeeder::class);
    $this->seed(InstrumentSeeder::class); // kein Duplikat

    expect(Instrument::count())->toBe(3)
        ->and(Instrument::where('risk_type', RiskType::Dekubitus->value)->exists())->toBeTrue()
        ->and(Instrument::where('risk_type', RiskType::Sturz->value)->exists())->toBeTrue()
        ->and(Instrument::where('risk_type', RiskType::Schmerz->value)->exists())->toBeTrue();

    $braden = Instrument::where('name', 'Braden-Skala')->first();
    expect($braden->items()->count())->toBe(6)
        ->and(AssessmentOption::whereIn('instrument_item_id', $braden->items()->pluck('id'))->count())->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/InstrumentSeederTest.php`
Expected: FAIL.

- [ ] **Step 3: `InstrumentReferenceData`**

`app/Domains/Assessment/Support/InstrumentReferenceData.php`:
```php
<?php

namespace App\Domains\Assessment\Support;

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;

class InstrumentReferenceData
{
    /**
     * @return array<int, array{
     *   name:string, risk_type:RiskType, direction:ScaleDirection, intervall_tage:int,
     *   risk_bands:array, items:array<int, array{label:string, options:array<int, array{label:string, punkte:int}>}>
     * }>
     */
    public static function instruments(): array
    {
        return [self::braden(), self::sturz(), self::besd()];
    }

    private static function braden(): array
    {
        // 6 Items je 1–4 Punkte; niedriger Gesamtscore = höheres Risiko.
        $skalen = [
            'Sensorisches Empfindungsvermögen' => ['fehlt', 'stark eingeschränkt', 'leicht eingeschränkt', 'vorhanden'],
            'Feuchtigkeit' => ['ständig feucht', 'oft feucht', 'manchmal feucht', 'selten feucht'],
            'Aktivität' => ['bettlägerig', 'sitzt auf', 'geht wenig', 'geht regelmäßig'],
            'Mobilität' => ['komplett immobil', 'stark eingeschränkt', 'gering eingeschränkt', 'mobil'],
            'Ernährung' => ['sehr schlecht', 'mäßig', 'ausreichend', 'gut'],
            'Reibung/Scherkräfte' => ['Problem', 'potenzielles Problem', 'kein Problem', 'kein Problem'],
        ];
        $items = [];
        foreach ($skalen as $label => $stufen) {
            $options = [];
            foreach (array_values($stufen) as $i => $stufe) {
                $options[] = ['label' => $stufe, 'punkte' => $i + 1];
            }
            $items[] = ['label' => $label, 'options' => $options];
        }

        return [
            'name' => 'Braden-Skala',
            'risk_type' => RiskType::Dekubitus,
            'direction' => ScaleDirection::LowerIsWorse,
            'intervall_tage' => 90,
            'risk_bands' => [
                ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
                ['band' => 'hoch', 'min' => 10, 'max' => 12],
                ['band' => 'mittel', 'min' => 13, 'max' => 14],
                ['band' => 'gering', 'min' => 15, 'max' => 18],
                ['band' => 'kein', 'min' => 19, 'max' => null],
            ],
            'items' => $items,
        ];
    }

    private static function sturz(): array
    {
        // Risikofaktoren-Checkliste: je zutreffend = Punkte; höherer Score = höheres Risiko.
        $faktoren = [
            'Sturz in den letzten 12 Monaten' => 2,
            'Gang-/Standunsicherheit' => 2,
            'Sehbeeinträchtigung' => 1,
            'Psychopharmaka / sedierende Medikation' => 1,
            'Kognitive Einschränkung / Desorientiertheit' => 1,
            'Inkontinenz / häufiger Toilettengang' => 1,
        ];
        $items = [];
        foreach ($faktoren as $label => $p) {
            $items[] = ['label' => $label, 'options' => [
                ['label' => 'nein', 'punkte' => 0],
                ['label' => 'ja', 'punkte' => $p],
            ]];
        }

        return [
            'name' => 'Sturzrisiko-Checkliste',
            'risk_type' => RiskType::Sturz,
            'direction' => ScaleDirection::HigherIsWorse,
            'intervall_tage' => 90,
            'risk_bands' => [
                ['band' => 'gering', 'min' => null, 'max' => 1],
                ['band' => 'mittel', 'min' => 2, 'max' => 3],
                ['band' => 'hoch', 'min' => 4, 'max' => null],
            ],
            'items' => $items,
        ];
    }

    private static function besd(): array
    {
        // BESD (Schmerzbeurteilung bei Demenz): 5 Items je 0–2 Punkte; höherer Score = mehr Schmerz.
        $items = [];
        foreach (['Atmung', 'Negative Lautäußerung', 'Gesichtsausdruck', 'Körpersprache', 'Trost'] as $label) {
            $items[] = ['label' => $label, 'options' => [
                ['label' => 'normal/0', 'punkte' => 0],
                ['label' => 'leicht/1', 'punkte' => 1],
                ['label' => 'deutlich/2', 'punkte' => 2],
            ]];
        }

        return [
            'name' => 'BESD-Schmerzskala',
            'risk_type' => RiskType::Schmerz,
            'direction' => ScaleDirection::HigherIsWorse,
            'intervall_tage' => 30,
            'risk_bands' => [
                ['band' => 'kein', 'min' => null, 'max' => 1],
                ['band' => 'gering', 'min' => 2, 'max' => 3],
                ['band' => 'mittel', 'min' => 4, 'max' => 6],
                ['band' => 'hoch', 'min' => 7, 'max' => null],
            ],
            'items' => $items,
        ];
    }
}
```

- [ ] **Step 4: `InstrumentSeeder`**

`app/Domains/Assessment/Database/Seeders/InstrumentSeeder.php`:
```php
<?php

namespace App\Domains\Assessment\Database\Seeders;

use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Support\InstrumentReferenceData;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    // Legt die Start-Instrumente für den AKTUELLEN Mandanten an. Idempotent über den Namen.
    public function run(): void
    {
        foreach (InstrumentReferenceData::instruments() as $def) {
            $instrument = Instrument::firstOrCreate(
                ['name' => $def['name']],
                [
                    'risk_type' => $def['risk_type'],
                    'direction' => $def['direction'],
                    'risk_bands' => $def['risk_bands'],
                    'intervall_tage' => $def['intervall_tage'],
                ],
            );

            if ($instrument->items()->exists()) {
                continue; // bereits befüllt
            }

            foreach ($def['items'] as $i => $itemDef) {
                $item = $instrument->items()->create(['label' => $itemDef['label'], 'reihenfolge' => $i]);
                foreach ($itemDef['options'] as $o => $optDef) {
                    $item->options()->create([
                        'label' => $optDef['label'], 'punkte' => $optDef['punkte'], 'reihenfolge' => $o,
                    ]);
                }
            }
        }
    }
}
```

> `instrument_item_id`/`tenant_id` werden über die Relationen + `BelongsToTenant` automatisch gesetzt; CurrentTenant muss gesetzt sein.

- [ ] **Step 5: `DatabaseSeeder` erweitern**

Im selben Mandanten-Kontext wie `MedicationReferenceSeeder` (und ggf. `ShiftSeeder` aus Plan 8) zusätzlich:
```php
$this->call(\App\Domains\Assessment\Database\Seeders\InstrumentSeeder::class);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/InstrumentSeederTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Domains/Assessment database/seeders
git add app/Domains/Assessment/Support/InstrumentReferenceData.php app/Domains/Assessment/Database/Seeders/InstrumentSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Assessment/InstrumentSeederTest.php
git commit -m "feat(assessment): InstrumentReferenceData + Seeder (Braden/Sturz/BESD je Mandant)"
```

---

## Task 7: Policies + Assessment-Durchführen-UI

**Files:**
- Create: `app/Domains/Assessment/Policies/{InstrumentPolicy,AssessmentPolicy}.php`
- Create: `app/Livewire/Assessment/AssessmentDurchfuehren.php`, `resources/views/livewire/assessment/assessment-durchfuehren.blade.php`
- Modify: `routes/web.php`, Policy-Registrierung (wie bestehende Policies), `resources/views/livewire/resident-show.blade.php`
- Test: `tests/Feature/Assessment/DurchfuehrenUiTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/DurchfuehrenUiTest.php`:
```php
<?php

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Models\InstrumentItem;
use App\Livewire\Assessment\AssessmentDurchfuehren;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegehilfskraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegehilfskraft');
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->instrument = Instrument::factory()->create();
    $this->item = InstrumentItem::create(['instrument_id' => $this->instrument->id, 'label' => 'Mobilität', 'reihenfolge' => 0]);
    $this->optLow = AssessmentOption::create(['instrument_item_id' => $this->item->id, 'label' => 'immobil', 'punkte' => 1]);
});

it('führt ein Assessment über die UI durch und speichert Score+Band', function () {
    Livewire::test(AssessmentDurchfuehren::class, ['resident' => $this->resident, 'instrument' => $this->instrument])
        ->set("answers.{$this->item->id}", $this->optLow->id)
        ->call('speichern')
        ->assertHasNoErrors();

    $assessment = Assessment::where('resident_id', $this->resident->id)->first();
    expect($assessment)->not->toBeNull()
        ->and($assessment->score)->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/DurchfuehrenUiTest.php`
Expected: FAIL.

- [ ] **Step 3: Policies**

`app/Domains/Assessment/Policies/AssessmentPolicy.php`:
```php
<?php

namespace App\Domains\Assessment\Policies;

use App\Domains\Identity\Models\User;

class AssessmentPolicy
{
    public function conduct(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
```

`app/Domains/Assessment/Policies/InstrumentPolicy.php`:
```php
<?php

namespace App\Domains\Assessment\Policies;

use App\Domains\Identity\Models\User;

class InstrumentPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
```

> Registrierung am bestehenden Ort (wie `PrescriptionPolicy`/`UserPolicy`):
> ```php
> Gate::policy(\App\Domains\Assessment\Models\Assessment::class, \App\Domains\Assessment\Policies\AssessmentPolicy::class);
> Gate::policy(\App\Domains\Assessment\Models\Instrument::class, \App\Domains\Assessment\Policies\InstrumentPolicy::class);
> ```

- [ ] **Step 4: `AssessmentDurchfuehren` Livewire**

`app/Livewire/Assessment/AssessmentDurchfuehren.php`:
```php
<?php

namespace App\Livewire\Assessment;

use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Actions\EscalateToQuality;
use App\Domains\Assessment\Actions\SyncRiskItem;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class AssessmentDurchfuehren extends Component
{
    #[Locked]
    public Resident $resident;

    #[Locked]
    public Instrument $instrument;

    /** instrument_item_id => assessment_option_id */
    public array $answers = [];

    public string $notiz = '';

    public function mount(Resident $resident, Instrument $instrument): void
    {
        $this->authorize('conduct', Assessment::class);
        $this->resident = $resident;
        $this->instrument = $instrument->load('items.options');
    }

    public function speichern(ConductAssessment $conduct, SyncRiskItem $sync, EscalateToQuality $escalate): void
    {
        $this->authorize('conduct', Assessment::class);
        // jedes Item braucht eine Antwort
        $itemIds = $this->instrument->items->pluck('id')->all();
        $this->validate(
            collect($itemIds)->mapWithKeys(fn ($id) => ["answers.$id" => ['required', 'exists:assessment_options,id']])->all(),
            [],
            collect($itemIds)->mapWithKeys(fn ($id) => ["answers.$id" => 'Antwort'])->all(),
        );

        $assessment = $conduct->handle(new AssessmentInputData(
            resident_id: $this->resident->id,
            instrument_id: $this->instrument->id,
            created_by: auth()->id(),
            answers: array_map('intval', $this->answers),
            notiz: trim($this->notiz) ?: null,
        ));

        // lose gekoppelte Folgeaktionen
        $sync->handle($assessment);
        $escalate->handle($assessment);

        session()->flash('status', 'Assessment gespeichert: '.$assessment->risk_band?->label());
        $this->redirectRoute('assessment.verlauf', ['resident' => $this->resident->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.assessment-durchfuehren');
    }
}
```

- [ ] **Step 5: View** (mit `<x-voice-field>` für die Notiz)

`resources/views/livewire/assessment/assessment-durchfuehren.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Assessment</p><h1>{{ $instrument->name }}</h1>
        <p class="lead">für {{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form wire:submit="speichern">
        <div class="card">
            @foreach ($instrument->items as $item)
                <div class="field">
                    <label>{{ $item->label }}</label>
                    @if ($item->hilfetext)<small class="muted">{{ $item->hilfetext }}</small>@endif
                    <select wire:model="answers.{{ $item->id }}">
                        <option value="">– wählen –</option>
                        @foreach ($item->options as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->label }} ({{ $opt->punkte }})</option>
                        @endforeach
                    </select>
                    @error("answers.{$item->id}")<span class="err">{{ $message }}</span>@enderror
                </div>
            @endforeach
        </div>

        <div class="card">
            <x-voice-field model="notiz" label="Notiz / Begründung" :rows="2" />
        </div>

        <button class="btn btn-primary">Assessment abschließen</button>
    </form>
</div>
```

- [ ] **Step 6: Route + Verlinkung**

In `routes/web.php` (Import `use App\Livewire\Assessment\AssessmentDurchfuehren;`):
```php
Route::get('/bewohner/{resident}/assessment/{instrument}', AssessmentDurchfuehren::class)->name('assessment.durchfuehren');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/DurchfuehrenUiTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Domains/Assessment/Policies app/Livewire/Assessment/AssessmentDurchfuehren.php resources/views/livewire/assessment/assessment-durchfuehren.blade.php routes/web.php app/Providers tests/Feature/Assessment/DurchfuehrenUiTest.php
git commit -m "feat(assessment): Policies + Durchführen-UI (Scoring, SIS-Sync, Quality-Eskalation)"
```

---

## Task 8: Assessment-Verlauf-UI (je Bewohner: aktuelle Risiken, Historie, Fälligkeiten)

**Files:**
- Create: `app/Livewire/Assessment/AssessmentVerlauf.php`, `resources/views/livewire/assessment/assessment-verlauf.blade.php`
- Modify: `routes/web.php`, `resources/views/livewire/resident-show.blade.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Assessment/VerlaufUiTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Assessment/VerlaufUiTest.php`:
```php
<?php

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Livewire\Assessment\AssessmentVerlauf;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->instrument = Instrument::factory()->create();
});

it('zeigt das aktuelle Assessment je Instrument und die verfügbaren Instrumente', function () {
    Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 11, 'risk_band' => RiskBand::Hoch, 'created_by' => $this->user->id,
    ]);

    Livewire::test(AssessmentVerlauf::class, ['resident' => $this->resident])
        ->assertSee('Braden-Skala')
        ->assertSee(RiskBand::Hoch->label());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Assessment/VerlaufUiTest.php`
Expected: FAIL.

- [ ] **Step 3: `AssessmentVerlauf` Livewire**

`app/Livewire/Assessment/AssessmentVerlauf.php`:
```php
<?php

namespace App\Livewire\Assessment;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class AssessmentVerlauf extends Component
{
    #[Locked]
    public Resident $resident;

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    public function render()
    {
        $aktuelle = Assessment::current()
            ->with('instrument')
            ->where('resident_id', $this->resident->id)
            ->latest('durchgefuehrt_am')
            ->get()
            ->unique('instrument_id');

        $historie = Assessment::with('instrument')
            ->where('resident_id', $this->resident->id)
            ->orderByDesc('durchgefuehrt_am')
            ->limit(50)
            ->get();

        return view('livewire.assessment.assessment-verlauf', [
            'aktuelle' => $aktuelle,
            'historie' => $historie,
            'instrumente' => Instrument::current()->orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/assessment/assessment-verlauf.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Assessment</p><h1>Risiko-Assessments</h1>
        <p class="lead">{{ $resident->name }}</p></div></div>

    <div class="card">
        <div class="card-head"><h3>Neues Assessment durchführen</h3></div>
        <div class="btn-row">
            @foreach ($instrumente as $instr)
                <a class="btn" href="{{ route('assessment.durchfuehren', [$resident, $instr]) }}" wire:navigate>{{ $instr->name }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Aktuelle Einstufung</h3></div>
        <table class="data"><thead><tr><th>Instrument</th><th>Score</th><th>Risiko</th><th>Durchgeführt</th><th>Fällig</th></tr></thead>
            <tbody>
                @forelse ($aktuelle as $a)
                    <tr @class(['row-warn' => $a->risk_band?->istKritisch()])>
                        <td><b>{{ $a->instrument?->name }}</b></td>
                        <td>{{ $a->score }}</td>
                        <td>{{ $a->risk_band?->label() }}</td>
                        <td>{{ optional($a->durchgefuehrt_am)->format('d.m.Y') }}</td>
                        <td @class(['err' => $a->istFaellig()])>{{ optional($a->faellig_am)->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Assessments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Verlauf</h3></div>
        <table class="data"><tbody>
            @foreach ($historie as $a)
                <tr>
                    <td>{{ optional($a->durchgefuehrt_am)->format('d.m.Y') }}</td>
                    <td>{{ $a->instrument?->name }} (v{{ $a->version }})</td>
                    <td>Score {{ $a->score }} — {{ $a->risk_band?->label() }}</td>
                </tr>
            @endforeach
        </tbody></table>
    </div>
</div>
```

- [ ] **Step 5: Route + Verlinkung + Nav**

In `routes/web.php` (Import `use App\Livewire\Assessment\AssessmentVerlauf;`):
```php
Route::get('/bewohner/{resident}/assessments', AssessmentVerlauf::class)->name('assessment.verlauf');
```
Im Bewohner-Detail (`resident-show.blade.php`) ergänzen:
```blade
<a class="btn" href="{{ route('assessment.verlauf', $resident) }}" wire:navigate>Assessments</a>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Assessment/VerlaufUiTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Assessment/AssessmentVerlauf.php resources/views/livewire/assessment/assessment-verlauf.blade.php routes/web.php resources/views/livewire/resident-show.blade.php resources/views/layouts/app.blade.php tests/Feature/Assessment/VerlaufUiTest.php
git commit -m "feat(assessment): Verlauf-UI (aktuelle Einstufung, Historie, Fälligkeiten)"
```

---

## Task 9: Gesamt-Suite + Pint + Push

- [ ] **Step 1: Gesamte Suite grün**

Run:
```bash
./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print('tests',d['tests'],'passed',d['passed'],'failed',d.get('failed'))"
```
Expected: alle Tests grün (`failed` = 0/None).

- [ ] **Step 2: Pint clean**

Run: `vendor/bin/pint --test`
Expected: keine Findings.

- [ ] **Step 3: Push**

```bash
git push origin <branch>
```

---

## Self-Review-Ergebnis (Autor)

**Spec coverage:** Versionierte Instrumente mit Items/Optionen (Task 1/2, `Versionable`) ✓; deterministisches Scoring + Risikoband (Task 3, reine Funktionen + Unit-Test, beide Skalenrichtungen) ✓; durchgeführte Assessments mit Antworten + Fälligkeit + append-only-Wiederholung (Task 4, `ConductAssessment`/`ReviseAssessment`) ✓; Verknüpfung zu SIS-`RiskItem` + Quality-`CareEvent` (Task 5, lose gekoppelt) ✓; Start-Instrumente Braden/Sturz/BESD aus OPDE-Vorlage (Task 6, Seeder je Mandant, idempotent) ✓; Durchführen-UI + Verlauf-UI mit Fälligkeitsanzeige + Voice-Notiz (Task 7/8) ✓; Rollen-Guards in mount UND Action/Policy ✓.

**Placeholder-Scan:** Keine TODO/TBD im Kern. Bewusst markierte Ist-Code-Abhängigkeiten: (a) `QualityIndicator`/`EventSeverity`-Cases in `EscalateToQuality` (Task 5) — als **Pflicht-Vorab-Schritt** ausgewiesen, Action explizit als optional/überspringbar gekennzeichnet, damit sie den Plan nicht blockiert; (b) Policy-Registrierungsort + Mandanten-Seed-Schleife — „bestehendem Muster folgen", da vom Ist-Stand abhängig.

**Typ-Konsistenz:** `RiskType` aus CarePlanning wiederverwendet (kein Duplikat); `Instrument.risk_type`/`Assessment.risk_band` durchgängig als Enum gecastet, in Migrationen als String. `ConductAssessment` und `ReviseAssessment` teilen `AssessmentInputData` (Felder `resident_id`/`instrument_id`/`created_by`/`answers`/`durchgefuehrt_am`/`notiz`) — identisch in Tests (Task 4/7) und Actions. `RiskBandResolver::resolve(int, array, ScaleDirection): RiskBand` identisch zwischen Unit-Test (Task 3) und `ConductAssessment` (Task 4). `risk_bands`-Format `[{band,min,max}]` konsistent zwischen Factory, `InstrumentReferenceData`, Resolver und Tests. `Versionable`-Nutzung (`current()`, `reviseWith`/`forceFill superseded_by`, `$attributes=['version'=>1]`) am verifizierten Concern ausgerichtet.
