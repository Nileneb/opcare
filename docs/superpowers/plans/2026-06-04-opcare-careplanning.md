# OPCare — Plan 2: CarePlanning (SIS®) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das SIS®-Strukturmodell als Backend: Informationssammlung (SIS) mit 6 Themenfeldern + Risikomatrix, tagesstrukturierte Maßnahmenplanung, Berichteblatt und Evaluation — rechtssicher **append-only / versioniert**.

**Architecture:** Baut auf Plan 1 (`App\Domains\…`, `BaseModel`, `TenantScope`, RBAC). Neue Domäne `App\Domains\CarePlanning`. Versionierung über ein `Versionable`-Concern: „Bearbeiten" erzeugt eine neue Zeile (`version+1`), die alte wird via `superseded_by` verkettet und als `abgelöst` markiert; ein `current()`-Scope liefert nur die jeweils gültige Version.

**Tech Stack:** wie Plan 1 (Laravel 12, PHP 8.5, PostgreSQL, Pest 3, spatie/laravel-data).

**Voraussetzung:** Plan 1 vollständig implementiert (Tenant, User, Resident, BaseModel, Rollen).

**Referenz-Spec:** `docs/superpowers/specs/2026-06-04-pflegeplanung-laravel-design.md`.

---

## File Structure (Plan 2)

```
app/
├── Support/Concerns/Versionable.php
└── Domains/CarePlanning/
    ├── Enums/{SisTopicField.php, RiskType.php, Shift.php, ZielErreichung.php}
    ├── Models/{SisAssessment,SisTopicFieldEntry,RiskItem,CareMeasure,
    │           MeasureSchedule,CareReport,Evaluation}.php
    ├── Actions/{CreateSisAssessment,ReviseSisAssessment,CreateCareMeasure,
    │            ReviseCareMeasure,CreateCareReport,ReviseCareReport,CreateEvaluation}.php
    ├── Data/{SisAssessmentData,CareMeasureData,CareReportData,EvaluationData}.php
    ├── Policies/{SisAssessmentPolicy,CareMeasurePolicy,CareReportPolicy}.php
    └── Database/factories/
tests/Feature/CarePlanning/...
```

---

## Task 1: Versionable-Concern + Enums

**Files:**
- Create: `app/Support/Concerns/Versionable.php`, `app/Domains/CarePlanning/Enums/{SisTopicField,RiskType,Shift,ZielErreichung}.php`
- Test: `tests/Feature/CarePlanning/EnumsTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/EnumsTest.php`:
```php
<?php

use App\Domains\CarePlanning\Enums\SisTopicField;

it('hat sechs SIS-Themenfelder', function () {
    expect(SisTopicField::cases())->toHaveCount(6)
        ->and(SisTopicField::Kognition->label())->toBe('Kognition & Kommunikation');
});
```

- [ ] **Step 2: Enums anlegen**

`app/Domains/CarePlanning/Enums/SisTopicField.php`:
```php
<?php

namespace App\Domains\CarePlanning\Enums;

enum SisTopicField: string
{
    case Kognition = 'kognition';
    case Mobilitaet = 'mobilitaet';
    case Krankheitsbezogen = 'krankheitsbezogen';
    case Selbstversorgung = 'selbstversorgung';
    case SozialeBeziehungen = 'soziale_beziehungen';
    case Wohnen = 'wohnen';

    public function label(): string
    {
        return match ($this) {
            self::Kognition => 'Kognition & Kommunikation',
            self::Mobilitaet => 'Mobilität & Beweglichkeit',
            self::Krankheitsbezogen => 'Krankheitsbezogene Anforderungen & Belastungen',
            self::Selbstversorgung => 'Selbstversorgung',
            self::SozialeBeziehungen => 'Leben in sozialen Beziehungen',
            self::Wohnen => 'Wohnen & Häuslichkeit',
        };
    }
}
```

`app/Domains/CarePlanning/Enums/RiskType.php`:
```php
<?php

namespace App\Domains\CarePlanning\Enums;

enum RiskType: string
{
    case Dekubitus = 'dekubitus';
    case Sturz = 'sturz';
    case Schmerz = 'schmerz';
    case Ernaehrung = 'ernaehrung';
    case Inkontinenz = 'inkontinenz';
    case Kontraktur = 'kontraktur';
}
```

`app/Domains/CarePlanning/Enums/Shift.php`:
```php
<?php

namespace App\Domains\CarePlanning\Enums;

enum Shift: string
{
    case Frueh = 'frueh';
    case Spaet = 'spaet';
    case Nacht = 'nacht';
}
```

`app/Domains/CarePlanning/Enums/ZielErreichung.php`:
```php
<?php

namespace App\Domains\CarePlanning\Enums;

enum ZielErreichung: string
{
    case Erreicht = 'erreicht';
    case Teilweise = 'teilweise';
    case Nicht = 'nicht';
}
```

- [ ] **Step 3: Versionable-Concern**

`app/Support/Concerns/Versionable.php`:
```php
<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Versionable
{
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_by');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by !== null;
    }

    /**
     * Erzeugt eine neue Version mit den geänderten Attributen, markiert die
     * aktuelle als abgelöst und verkettet sie via superseded_by.
     */
    public function reviseWith(array $attributes): static
    {
        $new = $this->replicate(['superseded_by', 'created_at', 'updated_at']);
        $new->fill($attributes);
        $new->version = $this->version + 1;
        if (in_array('status', $this->getFillable(), true)) {
            $new->status = 'aktiv';
        }
        $new->save();

        $update = ['superseded_by' => $new->id];
        if (in_array('status', $this->getFillable(), true)) {
            $update['status'] = 'abgelöst';
        }
        $this->forceFill($update)->save();

        return $new;
    }
}
```

- [ ] **Step 4: Test grün**

Run: `./vendor/bin/pest tests/Feature/CarePlanning/EnumsTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(careplanning): versionable concern + sis enums"
```

---

## Task 2: SIS-Assessment — Migration, Model, Factory

**Files:**
- Create: `database/migrations/xxxx_create_sis_assessments_table.php`, `app/Domains/CarePlanning/Models/SisAssessment.php`, `app/Domains/CarePlanning/Database/factories/SisAssessmentFactory.php`
- Test: `tests/Feature/CarePlanning/SisAssessmentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/SisAssessmentTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt eine SIS in Version 1 an', function () {
    $resident = Resident::factory()->create();
    $sis = SisAssessment::create([
        'resident_id' => $resident->id,
        'created_by' => 1,
        'erstellt_am' => '2026-03-01',
        'status' => 'aktiv',
        'eingangsfrage' => 'Frau M. möchte selbständig bleiben.',
    ]);

    expect($sis->version)->toBe(1)
        ->and($sis->isSuperseded())->toBeFalse()
        ->and(SisAssessment::current()->count())->toBe(1);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000100_create_sis_assessments_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sis_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('sis_assessments')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->date('erstellt_am');
            $table->string('status')->default('entwurf'); // entwurf/aktiv/abgelöst
            $table->text('eingangsfrage')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('sis_assessments'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/CarePlanning/Models/SisAssessment.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SisAssessment extends BaseModel
{
    use Versionable, HasFactory;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'erstellt_am', 'status', 'eingangsfrage',
    ];
    protected $casts = ['erstellt_am' => 'date', 'version' => 'integer'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function topicFields(): HasMany { return $this->hasMany(SisTopicFieldEntry::class); }
    public function riskItems(): HasMany { return $this->hasMany(RiskItem::class); }

    protected static function newFactory(): \App\Domains\CarePlanning\Database\Factories\SisAssessmentFactory
    {
        return \App\Domains\CarePlanning\Database\Factories\SisAssessmentFactory::new();
    }
}
```

- [ ] **Step 4: Factory**

`app/Domains/CarePlanning/Database/factories/SisAssessmentFactory.php`:
```php
<?php

namespace App\Domains\CarePlanning\Database\Factories;

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class SisAssessmentFactory extends Factory
{
    protected $model = SisAssessment::class;

    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'created_by' => 1,
            'erstellt_am' => now()->format('Y-m-d'),
            'status' => 'aktiv',
            'eingangsfrage' => $this->faker->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/SisAssessmentTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(careplanning): sis assessment model + migration + factory"
```

---

## Task 3: SIS-Themenfelder

**Files:**
- Create: `database/migrations/xxxx_create_sis_topic_field_entries_table.php`, `app/Domains/CarePlanning/Models/SisTopicFieldEntry.php`
- Test: `tests/Feature/CarePlanning/SisTopicFieldTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/SisTopicFieldTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\CarePlanning\Models\{SisAssessment, SisTopicFieldEntry};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('speichert ein Themenfeld mit Freitext und Strukturdaten', function () {
    $sis = SisAssessment::factory()->create();
    $entry = SisTopicFieldEntry::create([
        'sis_assessment_id' => $sis->id,
        'themenfeld' => SisTopicField::Mobilitaet,
        'freitext' => 'Geht am Rollator.',
        'strukturdaten' => ['rollator' => true, 'sturzrisiko' => 'mittel'],
    ]);

    expect($entry->themenfeld)->toBe(SisTopicField::Mobilitaet)
        ->and($entry->strukturdaten['rollator'])->toBeTrue()
        ->and($sis->topicFields)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000101_create_sis_topic_field_entries_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sis_topic_field_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sis_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('themenfeld');
            $table->text('freitext')->nullable();
            $table->jsonb('strukturdaten')->nullable();
            $table->timestamps();
            $table->unique(['sis_assessment_id', 'themenfeld']);
        });
    }
    public function down(): void { Schema::dropIfExists('sis_topic_field_entries'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/CarePlanning/Models/SisTopicFieldEntry.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisTopicFieldEntry extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'themenfeld', 'freitext', 'strukturdaten'];
    protected $casts = [
        'themenfeld' => SisTopicField::class,
        'strukturdaten' => 'array',
    ];

    public function sisAssessment(): BelongsTo { return $this->belongsTo(SisAssessment::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/SisTopicFieldTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(careplanning): sis topic field entries"
```

---

## Task 4: Risikomatrix

**Files:**
- Create: `database/migrations/xxxx_create_risk_items_table.php`, `app/Domains/CarePlanning/Models/RiskItem.php`
- Test: `tests/Feature/CarePlanning/RiskItemTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/RiskItemTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Models\{SisAssessment, RiskItem};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erfasst ein eingeschätztes Risiko', function () {
    $sis = SisAssessment::factory()->create();
    $risk = RiskItem::create([
        'sis_assessment_id' => $sis->id,
        'risiko' => RiskType::Sturz,
        'eingeschaetzt' => true,
        'begruendung' => 'Gangunsicherheit, Rollator.',
    ]);

    expect($risk->risiko)->toBe(RiskType::Sturz)
        ->and($risk->eingeschaetzt)->toBeTrue()
        ->and($sis->riskItems)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000102_create_risk_items_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('risk_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sis_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('risiko');
            $table->boolean('eingeschaetzt')->default(false);
            $table->text('begruendung')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('risk_items'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/CarePlanning/Models/RiskItem.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\RiskType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'risiko', 'eingeschaetzt', 'begruendung'];
    protected $casts = ['risiko' => RiskType::class, 'eingeschaetzt' => 'boolean'];

    public function sisAssessment(): BelongsTo { return $this->belongsTo(SisAssessment::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/RiskItemTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(careplanning): risk items"
```

---

## Task 5: SIS erstellen — Action + DTO + Policy

**Files:**
- Create: `app/Domains/CarePlanning/Data/SisAssessmentData.php`, `app/Domains/CarePlanning/Actions/CreateSisAssessment.php`, `app/Domains/CarePlanning/Policies/SisAssessmentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/CarePlanning/CreateSisAssessmentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/CreateSisAssessmentTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt eine SIS mit Themenfeldern über die Action', function () {
    $resident = Resident::factory()->create();

    $sis = app(CreateSisAssessment::class)->handle(new SisAssessmentData(
        resident_id: $resident->id,
        created_by: 1,
        erstellt_am: '2026-03-01',
        eingangsfrage: 'Möchte mobil bleiben.',
        themenfelder: [
            ['themenfeld' => 'mobilitaet', 'freitext' => 'Rollator', 'strukturdaten' => null],
        ],
    ));

    expect($sis->version)->toBe(1)
        ->and($sis->status)->toBe('aktiv')
        ->and($sis->topicFields)->toHaveCount(1);
});
```

- [ ] **Step 2: DTO**

`app/Domains/CarePlanning/Data/SisAssessmentData.php`:
```php
<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class SisAssessmentData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public string $erstellt_am,
        public ?string $eingangsfrage = null,
        /** @var array<int, array{themenfeld:string, freitext:?string, strukturdaten:?array}> */
        public array $themenfelder = [],
    ) {}
}
```

- [ ] **Step 3: Action**

`app/Domains/CarePlanning/Actions/CreateSisAssessment.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\CarePlanning\Models\SisAssessment;
use Illuminate\Support\Facades\DB;

class CreateSisAssessment
{
    public function handle(SisAssessmentData $data): SisAssessment
    {
        return DB::transaction(function () use ($data) {
            $sis = SisAssessment::create([
                'resident_id' => $data->resident_id,
                'created_by' => $data->created_by,
                'erstellt_am' => $data->erstellt_am,
                'status' => 'aktiv',
                'eingangsfrage' => $data->eingangsfrage,
            ]);

            foreach ($data->themenfelder as $feld) {
                $sis->topicFields()->create([
                    'themenfeld' => $feld['themenfeld'],
                    'freitext' => $feld['freitext'] ?? null,
                    'strukturdaten' => $feld['strukturdaten'] ?? null,
                ]);
            }

            return $sis->load('topicFields');
        });
    }
}
```

- [ ] **Step 4: Policy + Registrierung**

`app/Domains/CarePlanning/Policies/SisAssessmentPolicy.php`:
```php
<?php

namespace App\Domains\CarePlanning\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\CarePlanning\Models\SisAssessment;

class SisAssessmentPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']); }
    public function view(User $user, SisAssessment $s): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft']); }
    public function update(User $user, SisAssessment $s): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft']); }
}
```
In `app/Providers/AppServiceProvider.php` (`boot`):
```php
Gate::policy(\App\Domains\CarePlanning\Models\SisAssessment::class, \App\Domains\CarePlanning\Policies\SisAssessmentPolicy::class);
```

- [ ] **Step 5: Test grün**

Run: `./vendor/bin/pest tests/Feature/CarePlanning/CreateSisAssessmentTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(careplanning): create-sis action, data, policy"
```

---

## Task 6: SIS revidieren (Versionierung)

**Files:**
- Create: `app/Domains/CarePlanning/Actions/ReviseSisAssessment.php`
- Test: `tests/Feature/CarePlanning/ReviseSisAssessmentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/ReviseSisAssessmentTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Actions\ReviseSisAssessment;
use App\Domains\CarePlanning\Models\SisAssessment;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erzeugt eine neue Version und löst die alte ab', function () {
    $sis = SisAssessment::factory()->create(['eingangsfrage' => 'Alt']);

    $v2 = app(ReviseSisAssessment::class)->handle($sis, ['eingangsfrage' => 'Neu']);

    expect($v2->version)->toBe(2)
        ->and($v2->eingangsfrage)->toBe('Neu')
        ->and($v2->status)->toBe('aktiv')
        ->and($sis->fresh()->superseded_by)->toBe($v2->id)
        ->and($sis->fresh()->status)->toBe('abgelöst')
        ->and(SisAssessment::current()->count())->toBe(1);
});
```

- [ ] **Step 2: Action**

`app/Domains/CarePlanning/Actions/ReviseSisAssessment.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\SisAssessment;
use Illuminate\Support\Facades\DB;

class ReviseSisAssessment
{
    public function handle(SisAssessment $current, array $changes): SisAssessment
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
```

- [ ] **Step 3: Test grün**

Run: `./vendor/bin/pest tests/Feature/CarePlanning/ReviseSisAssessmentTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(careplanning): revise sis (append-only versioning)"
```

---

## Task 7: Maßnahmen + Zeitplanung

**Files:**
- Create: Migrationen `care_measures`, `measure_schedules`; Modelle `CareMeasure`, `MeasureSchedule`
- Test: `tests/Feature/CarePlanning/CareMeasureTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/CareMeasureTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\CarePlanning\Models\{CareMeasure, MeasureSchedule};
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('plant eine Maßnahme mit Turnus', function () {
    $resident = Resident::factory()->create();
    $measure = CareMeasure::create([
        'resident_id' => $resident->id,
        'themenfeld' => SisTopicField::Mobilitaet,
        'beschreibung' => 'Mobilisation 2x täglich',
        'ziel' => 'Erhalt der Gehfähigkeit',
    ]);
    $schedule = MeasureSchedule::create([
        'care_measure_id' => $measure->id,
        'turnus_typ' => 'schicht',
        'turnus_daten' => ['schichten' => ['frueh', 'spaet']],
    ]);

    expect($measure->version)->toBe(1)
        ->and($measure->schedules)->toHaveCount(1)
        ->and($schedule->turnus_daten['schichten'])->toContain('frueh');
});
```

- [ ] **Step 2: Migrationen**

`database/migrations/2026_06_04_000110_create_care_measures_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('care_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('superseded_by')->nullable()->constrained('care_measures')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->string('themenfeld');
            $table->text('beschreibung');
            $table->text('ziel')->nullable();
            $table->string('verantwortlich')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('care_measures'); }
};
```

`...000111_create_measure_schedules_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('measure_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_measure_id')->constrained()->cascadeOnDelete();
            $table->string('turnus_typ'); // schicht/uhrzeit/intervall
            $table->jsonb('turnus_daten');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('measure_schedules'); }
};
```

- [ ] **Step 3: Modelle**

`app/Domains/CarePlanning/Models/CareMeasure.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CareMeasure extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'superseded_by', 'version',
        'themenfeld', 'beschreibung', 'ziel', 'verantwortlich', 'aktiv',
    ];
    protected $casts = ['themenfeld' => SisTopicField::class, 'aktiv' => 'boolean', 'version' => 'integer'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function schedules(): HasMany { return $this->hasMany(MeasureSchedule::class); }
}
```
> `reviseWith` nutzt `status` nur, wenn im `$fillable`; `CareMeasure` hat kein `status`, daher wird nur `superseded_by`/`version` gesetzt — vom Concern bereits korrekt behandelt.

`app/Domains/CarePlanning/Models/MeasureSchedule.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureSchedule extends BaseModel
{
    protected $fillable = ['tenant_id', 'care_measure_id', 'turnus_typ', 'turnus_daten'];
    protected $casts = ['turnus_daten' => 'array'];

    public function careMeasure(): BelongsTo { return $this->belongsTo(CareMeasure::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/CareMeasureTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(careplanning): care measures + schedules"
```

---

## Task 8: Maßnahmen-Actions (Create + Revise)

**Files:**
- Create: `app/Domains/CarePlanning/Data/CareMeasureData.php`, `app/Domains/CarePlanning/Actions/CreateCareMeasure.php`, `app/Domains/CarePlanning/Actions/ReviseCareMeasure.php`
- Test: `tests/Feature/CarePlanning/CareMeasureActionTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/CareMeasureActionTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Actions\{CreateCareMeasure, ReviseCareMeasure};
use App\Domains\CarePlanning\Data\CareMeasureData;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt und revidiert eine Maßnahme', function () {
    $resident = Resident::factory()->create();
    $m = app(CreateCareMeasure::class)->handle(new CareMeasureData(
        resident_id: $resident->id,
        themenfeld: 'mobilitaet',
        beschreibung: 'Gehübung',
        ziel: 'Mobilität',
    ));
    expect($m->version)->toBe(1);

    $v2 = app(ReviseCareMeasure::class)->handle($m, ['beschreibung' => 'Gehübung 3x']);
    expect($v2->version)->toBe(2)
        ->and(CareMeasure::current()->count())->toBe(1)
        ->and($m->fresh()->superseded_by)->toBe($v2->id);
});
```

- [ ] **Step 2: DTO**

`app/Domains/CarePlanning/Data/CareMeasureData.php`:
```php
<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class CareMeasureData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $themenfeld,
        public string $beschreibung,
        public ?string $ziel = null,
        public ?string $verantwortlich = null,
    ) {}
}
```

- [ ] **Step 3: Actions**

`app/Domains/CarePlanning/Actions/CreateCareMeasure.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\CareMeasureData;
use App\Domains\CarePlanning\Models\CareMeasure;

class CreateCareMeasure
{
    public function handle(CareMeasureData $data): CareMeasure
    {
        return CareMeasure::create([
            'resident_id' => $data->resident_id,
            'themenfeld' => $data->themenfeld,
            'beschreibung' => $data->beschreibung,
            'ziel' => $data->ziel,
            'verantwortlich' => $data->verantwortlich,
        ]);
    }
}
```

`app/Domains/CarePlanning/Actions/ReviseCareMeasure.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\CareMeasure;
use Illuminate\Support\Facades\DB;

class ReviseCareMeasure
{
    public function handle(CareMeasure $current, array $changes): CareMeasure
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
```

- [ ] **Step 4: Test grün**

Run: `./vendor/bin/pest tests/Feature/CarePlanning/CareMeasureActionTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(careplanning): care measure create/revise actions"
```

---

## Task 9: Berichteblatt (append-only)

**Files:**
- Create: Migration `care_reports`, Modell `CareReport`, DTO `CareReportData`, Actions `CreateCareReport`/`ReviseCareReport`, Policy `CareReportPolicy`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/CarePlanning/CareReportTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/CareReportTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Actions\{CreateCareReport, ReviseCareReport};
use App\Domains\CarePlanning\Data\CareReportData;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('schreibt einen Bericht und korrigiert ihn versioniert', function () {
    $resident = Resident::factory()->create();
    $report = app(CreateCareReport::class)->handle(new CareReportData(
        resident_id: $resident->id,
        created_by: 1,
        datum: '2026-03-02 08:00:00',
        schicht: 'frueh',
        text: 'Bewohnerin gut gelaunt.',
    ));
    expect($report->version)->toBe(1);

    $v2 = app(ReviseCareReport::class)->handle($report, ['text' => 'Bewohnerin gut gelaunt, hat gefrühstückt.']);
    expect($v2->version)->toBe(2)
        ->and(CareReport::current()->count())->toBe(1)
        ->and($report->fresh()->superseded_by)->toBe($v2->id);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000120_create_care_reports_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('care_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('care_reports')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->timestamp('datum');
            $table->string('schicht'); // frueh/spaet/nacht
            $table->text('text');
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('care_reports'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/CarePlanning/Models/CareReport.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\Shift;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareReport extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'datum', 'schicht', 'text',
    ];
    protected $casts = ['datum' => 'datetime', 'schicht' => Shift::class, 'version' => 'integer'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
}
```

- [ ] **Step 4: DTO + Actions + Policy**

`app/Domains/CarePlanning/Data/CareReportData.php`:
```php
<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class CareReportData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public string $datum,
        public string $schicht,
        public string $text,
    ) {}
}
```

`app/Domains/CarePlanning/Actions/CreateCareReport.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\CareReportData;
use App\Domains\CarePlanning\Models\CareReport;

class CreateCareReport
{
    public function handle(CareReportData $data): CareReport
    {
        return CareReport::create($data->toArray());
    }
}
```

`app/Domains/CarePlanning/Actions/ReviseCareReport.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\CareReport;
use Illuminate\Support\Facades\DB;

class ReviseCareReport
{
    public function handle(CareReport $current, array $changes): CareReport
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
```

`app/Domains/CarePlanning/Policies/CareReportPolicy.php`:
```php
<?php

namespace App\Domains\CarePlanning\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\CarePlanning\Models\CareReport;

class CareReportPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']); }
    public function create(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']); }
    public function update(User $user, CareReport $r): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']); }
}
```
In `AppServiceProvider::boot`:
```php
Gate::policy(\App\Domains\CarePlanning\Models\CareReport::class, \App\Domains\CarePlanning\Policies\CareReportPolicy::class);
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/CareReportTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(careplanning): care reports (append-only) + actions + policy"
```

---

## Task 10: Evaluation (polymorph, append-only)

**Files:**
- Create: Migration `evaluations`, Modell `Evaluation`, DTO `EvaluationData`, Action `CreateEvaluation`
- Test: `tests/Feature/CarePlanning/EvaluationTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/CarePlanning/EvaluationTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Actions\CreateEvaluation;
use App\Domains\CarePlanning\Data\EvaluationData;
use App\Domains\CarePlanning\Enums\ZielErreichung;
use App\Domains\CarePlanning\Models\{CareMeasure, Evaluation};
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('bewertet die Zielerreichung einer Maßnahme polymorph', function () {
    $resident = Resident::factory()->create();
    $measure = CareMeasure::create([
        'resident_id' => $resident->id, 'themenfeld' => 'mobilitaet', 'beschreibung' => 'Gehen',
    ]);

    $eval = app(CreateEvaluation::class)->handle(new EvaluationData(
        evaluable_type: CareMeasure::class,
        evaluable_id: $measure->id,
        created_by: 1,
        datum: '2026-04-01',
        zielerreichung: 'teilweise',
        anlass: 'Quartalsevaluation',
    ));

    expect($eval->zielerreichung)->toBe(ZielErreichung::Teilweise)
        ->and($eval->evaluable->is($measure))->toBeTrue()
        ->and($measure->evaluations)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000130_create_evaluations_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('evaluable_type');
            $table->unsignedBigInteger('evaluable_id');
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->date('datum');
            $table->string('zielerreichung'); // erreicht/teilweise/nicht
            $table->string('anlass')->nullable();
            $table->timestamps();
            $table->index(['evaluable_type', 'evaluable_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('evaluations'); }
};
```

- [ ] **Step 3: Model + polymorphe Rückrelation**

`app/Domains/CarePlanning/Models/Evaluation.php`:
```php
<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\ZielErreichung;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Evaluation extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'evaluable_type', 'evaluable_id', 'created_by',
        'superseded_by', 'version', 'datum', 'zielerreichung', 'anlass',
    ];
    protected $casts = ['datum' => 'date', 'zielerreichung' => ZielErreichung::class, 'version' => 'integer'];

    public function evaluable(): MorphTo { return $this->morphTo(); }
}
```
In `CareMeasure.php` Rückrelation ergänzen:
```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function evaluations(): MorphMany
{
    return $this->morphMany(Evaluation::class, 'evaluable');
}
```
In `SisAssessment.php` ebenfalls (optional, gleiche Signatur):
```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function evaluations(): MorphMany
{
    return $this->morphMany(Evaluation::class, 'evaluable');
}
```

- [ ] **Step 4: DTO + Action**

`app/Domains/CarePlanning/Data/EvaluationData.php`:
```php
<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class EvaluationData extends Data
{
    public function __construct(
        public string $evaluable_type,
        public int $evaluable_id,
        public int $created_by,
        public string $datum,
        public string $zielerreichung,
        public ?string $anlass = null,
    ) {}
}
```

`app/Domains/CarePlanning/Actions/CreateEvaluation.php`:
```php
<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\EvaluationData;
use App\Domains\CarePlanning\Models\Evaluation;

class CreateEvaluation
{
    public function handle(EvaluationData $data): Evaluation
    {
        return Evaluation::create($data->toArray());
    }
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/CarePlanning/EvaluationTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(careplanning): polymorphic evaluations (append-only)"
```

---

## Task 11: Gesamtsuite + Demo-Erweiterung

**Files:**
- Modify: `app/Domains/Identity/Database/seeders/DemoSeeder.php`

- [ ] **Step 1: DemoSeeder um eine SIS erweitern**

In `DemoSeeder::run()` nach dem Anlegen der Residents ergänzen:
```php
use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;

$resident = \App\Domains\Masterdata\Models\Resident::query()->first();
app(CreateSisAssessment::class)->handle(new SisAssessmentData(
    resident_id: $resident->id,
    created_by: $admin->id,
    erstellt_am: now()->format('Y-m-d'),
    eingangsfrage: 'Möchte so selbständig wie möglich bleiben.',
    themenfelder: [
        ['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.', 'strukturdaten' => null],
        ['themenfeld' => 'selbstversorgung', 'freitext' => 'Braucht Hilfe beim Waschen.', 'strukturdaten' => null],
    ],
));
```

- [ ] **Step 2: Frisch migrieren + seeden**

Run: `php artisan migrate:fresh --seed`
Expected: ohne Fehler.

- [ ] **Step 3: Gesamte Suite**

Run: `./vendor/bin/pest`
Expected: ALLE PASS (Plan 1 + Plan 2).

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(careplanning): seed demo sis assessment"
```

---

## Self-Review-Ergebnis (Plan 2)

- **Spec-Abdeckung:** SIS-Strukturmodell 4 Elemente (§4) → SIS (Task 2,3,4,5,6), Maßnahmen (7,8), Bericht (9), Evaluation (10). Append-only/Versionierung (§4,§6) → `Versionable` (Task 1) + Revise-Actions (6,8,9) + Evaluation versioniert (10). 6 Themenfelder + Risikomatrix (§4) → Enums (1), Tasks 3 & 4.
- **Platzhalter:** keine.
- **Typ-Konsistenz:** `reviseWith()`, `scopeCurrent()`, `isSuperseded()` durchgängig; Enums (`SisTopicField`, `RiskType`, `Shift`, `ZielErreichung`) konsistent in Migrations-Strings ↔ Model-Casts; `handle()`-Actions + DTO-Felder stimmen mit Tests überein.

## Folge-Plan
- **Plan 3:** Speech-Workflow — `docs/superpowers/plans/2026-06-04-opcare-speech.md`
