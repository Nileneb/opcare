# OPCare — Plan 8: Kalender / Dienstplanung + Zeitbezug-Fundament — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein zentrales **Zeit- und Kalender-Fundament**: Schichten (`Shift`) je Einrichtung mit konfigurierbaren Uhrzeiten, **Dienstplan** (`ShiftAssignment`: wer arbeitet wann), wiederkehrende **Kalendertermine** (`CalendarEvent` + Recurrence-Regel) für Bewohner/Einrichtung, plus die Umstellung der hartkodierten Medikations-Tageszeiten auf die Schicht-Konfiguration. Damit docken Medikations-Soll-Zeiten, Evaluations-/Assessment-Fälligkeiten und QDVS-Stichtage an **eine** Zeitquelle an.

**Architecture:** Neue Domäne `App\Domains\Scheduling`. `Shift` definiert je Mandant benannte Schichten (Früh/Spät/Nacht) mit `beginn`/`ende` (TIME) und einer Zuordnung zu Medikations-`AdministrationTimeslot`s. `ShiftAssignment` verknüpft `User` × `Shift` × Datum (Dienstplan). `CalendarEvent` ist ein terminierter Eintrag (Arzttermin, Maßnahme, interner Termin) mit optionaler `RecurrenceRule` (FREQ/INTERVAL/BYDAY/UNTIL, RFC-5545-Teilmenge); `RecurrenceExpander` materialisiert Vorkommen für einen Zeitraum (read-only Expansion, keine Vorab-Persistenz). **KEIN eigener Console-Kernel**: der vorhandene `routes/console.php`-Scheduler + Queue-Worker (Horizon/Redis in Prod, `sync` in Tests) genügen. Alles tenant-scoped via `BaseModel`. App-Zeitzone bleibt `Europe/Berlin` (UI/Eingabe), Speicherung der Zeitpunkte UTC durch Eloquent-Casts.

**Tech Stack:** wie Plan 1–7 (Laravel 13, PHP 8.4, PostgreSQL/SQLite-Tests, Livewire 4, Pest 4 + Arch, spatie-data, spatie-activitylog). Tenant-Scope/CurrentTenant/BaseModel aus Plan 1; `Versionable` **nicht** nötig (Dienstplan/Termine sind keine append-only-Dokumente).

**Voraussetzung:** Plan 1 (Tenant, User, Resident, BaseModel, CurrentTenant, RolesSeeder), Plan 5 (Medikation: `AdministrationTimeslot`, `TimeslotClock`, `MaterializeSchedulesJob`). Plan 4 empfohlen (Tenancy gehärtet).

**Referenz:** OPDE `entity/building/{Homes,Station}` (Schicht-/Zeitlogik war dort implizit), `config/medication.php` (`timeslot_clock` — wird hier abgelöst). RFC 5545 (iCalendar RRULE) als Vorbild für `RecurrenceRule`, bewusst auf die in der Pflege gebräuchliche Teilmenge reduziert.

---

## Hinweise für ausführende Subagents

- **Tests laufen auf SQLite in-memory** (`phpunit.xml`: `DB_CONNECTION=sqlite`, `SPEECH_FAKE=true`, `QUEUE_CONNECTION=sync`). PostgreSQL ist in der Sandbox **nicht** erreichbar — keine pgsql-spezifischen Migrationen/Typen verwenden, kein `->after()` auf nicht existierende Spalten in SQLite.
- **Pest gibt JSON aus** (via `laravel/pao`). Ergebnisse so lesen:
  ```bash
  ./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['tests'],d['passed'],d.get('failed'))"
  ```
  Einzelne Datei: `./vendor/bin/pest tests/Feature/Scheduling/ShiftTest.php`.
- **Vor jedem Commit:** `vendor/bin/pint` (Code muss pint-konform sein, CI `lint.yml` prüft `--test`).
- **Zeit in Tests einfrieren:** `Illuminate\Support\Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'))` in `beforeEach`, `Carbon::setTestNow()` zum Zurücksetzen. Niemals reale `now()`-Abhängigkeit ohne Freeze testen.
- **CurrentTenant muss in jedem Feature-Test gesetzt sein** (`app(CurrentTenant::class)->set($tenant)`), sonst greift der globale `TenantScope` mit `tenant_id = null` und liefert leere Resultate.
- Rollen aus `RolesSeeder`: `admin`, `pflegefachkraft`, `pflegehilfskraft`, `leserecht`; dazu `super-admin` (Gate::before-Bypass). Dienstplan-Pflege ist Leitungssache → Guard auf `admin`/`pflegefachkraft`/`super-admin`.

---

## File Structure (Plan 8)

```
app/Domains/Scheduling/
├── Enums/{ShiftKind, RecurrenceFreq, CalendarEventType}.php
├── Models/{Shift, ShiftAssignment, CalendarEvent, RecurrenceRule}.php
├── Data/{ShiftData, ShiftAssignmentData, CalendarEventData, RecurrenceData}.php
├── Actions/{CreateShift, AssignShift, CreateCalendarEvent, CancelCalendarEvent}.php
├── Support/{RecurrenceExpander, ShiftClock}.php   # ShiftClock löst TimeslotClock ab
├── Database/{Factories/{ShiftFactory,CalendarEventFactory}.php, Seeders/ShiftSeeder.php}
└── Policies/{ShiftPolicy, CalendarEventPolicy}.php
app/Livewire/Scheduling/{Dienstplan, Kalender}.php (+ views)
database/migrations/2026_06_04_0008xx_*.php   (shifts, shift_assignments, calendar_events, recurrence_rules)
tests/Feature/Scheduling/...
tests/Unit/Scheduling/RecurrenceExpanderTest.php
```

**Geänderte Bestandsdateien:**
- `app/Domains/Medication/Support/TimeslotClock.php` — liest künftig die Schicht-Konfiguration des Mandanten statt `config('medication.timeslot_clock')`.
- `routes/web.php` — Routen `dienstplan`, `kalender`.
- `database/seeders/DatabaseSeeder.php` — `ShiftSeeder` je Mandant aufrufen (analog `MedicationReferenceSeeder`).
- `resources/views/layouts/app.blade.php` — Nav-Links Dienstplan/Kalender (Leitung).

---

## Task 1: Enums + Migrationen (Schichten, Dienstplan, Termine, Recurrence)

**Files:**
- Create: `app/Domains/Scheduling/Enums/{ShiftKind,RecurrenceFreq,CalendarEventType}.php`
- Create: `database/migrations/2026_06_04_000801_create_shifts_table.php`, `...000802_create_shift_assignments_table.php`, `...000803_create_recurrence_rules_table.php`, `...000804_create_calendar_events_table.php`
- Test: `tests/Feature/Scheduling/SchemaTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/SchemaTest.php`:
```php
<?php

use Illuminate\Support\Facades\Schema;

it('legt die Scheduling-Tabellen mit den erwarteten Spalten an', function () {
    expect(Schema::hasColumns('shifts', ['tenant_id', 'name', 'kind', 'beginn', 'ende', 'timeslots']))->toBeTrue()
        ->and(Schema::hasColumns('shift_assignments', ['tenant_id', 'user_id', 'shift_id', 'dienst_am']))->toBeTrue()
        ->and(Schema::hasColumns('recurrence_rules', ['tenant_id', 'freq', 'intervall', 'byday', 'until']))->toBeTrue()
        ->and(Schema::hasColumns('calendar_events', ['tenant_id', 'resident_id', 'type', 'titel', 'beginnt_am', 'endet_am', 'recurrence_rule_id', 'abgesagt_am']))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/SchemaTest.php`
Expected: FAIL (Tabellen existieren nicht).

- [ ] **Step 3: Enums**

`app/Domains/Scheduling/Enums/ShiftKind.php`:
```php
<?php

namespace App\Domains\Scheduling\Enums;

enum ShiftKind: string
{
    case Frueh = 'frueh';
    case Spaet = 'spaet';
    case Nacht = 'nacht';
    case Zwischendienst = 'zwischendienst';

    public function label(): string
    {
        return match ($this) {
            self::Frueh => 'Frühdienst',
            self::Spaet => 'Spätdienst',
            self::Nacht => 'Nachtdienst',
            self::Zwischendienst => 'Zwischendienst',
        };
    }
}
```

`app/Domains/Scheduling/Enums/RecurrenceFreq.php`:
```php
<?php

namespace App\Domains\Scheduling\Enums;

enum RecurrenceFreq: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
```

`app/Domains/Scheduling/Enums/CalendarEventType.php`:
```php
<?php

namespace App\Domains\Scheduling\Enums;

enum CalendarEventType: string
{
    case Arzttermin = 'arzttermin';
    case Massnahme = 'massnahme';
    case Therapie = 'therapie';
    case Besuch = 'besuch';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Arzttermin => 'Arzttermin',
            self::Massnahme => 'Pflegemaßnahme',
            self::Therapie => 'Therapie',
            self::Besuch => 'Besuch',
            self::Intern => 'Interner Termin',
        };
    }
}
```

- [ ] **Step 4: Migration `shifts`**

`database/migrations/2026_06_04_000801_create_shifts_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->time('beginn');
            $table->time('ende');
            // welche Medikations-Tageszeiten diese Schicht abdeckt: ['morgens' => '08:00', ...]
            $table->json('timeslots')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
```

- [ ] **Step 5: Migration `shift_assignments`**

`database/migrations/2026_06_04_000802_create_shift_assignments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('dienst_am');
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'shift_id', 'dienst_am'], 'shift_assignment_unique');
            $table->index(['tenant_id', 'dienst_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
```

- [ ] **Step 6: Migration `recurrence_rules`**

`database/migrations/2026_06_04_000803_create_recurrence_rules_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('freq');            // daily|weekly|monthly
            $table->unsignedSmallInteger('intervall')->default(1);
            $table->json('byday')->nullable(); // ISO-Wochentage [1..7] bei weekly; Monatstag [1..31] bei monthly
            $table->date('until')->nullable(); // exklusives Enddatum; null = unbegrenzt
            $table->unsignedSmallInteger('count')->nullable(); // alternativ: max. Anzahl Vorkommen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_rules');
    }
};
```

- [ ] **Step 7: Migration `calendar_events`**

`database/migrations/2026_06_04_000804_create_calendar_events_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->dateTime('beginnt_am');
            $table->dateTime('endet_am')->nullable();
            $table->boolean('ganztaegig')->default(false);
            $table->foreignId('recurrence_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('abgesagt_am')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'beginnt_am']);
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
```

- [ ] **Step 8: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/SchemaTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint app/Domains/Scheduling database/migrations
git add app/Domains/Scheduling database/migrations tests/Feature/Scheduling/SchemaTest.php
git commit -m "feat(scheduling): enums + migrations für Schichten, Dienstplan, Kalender, Recurrence"
```

---

## Task 2: Models (Shift, ShiftAssignment, RecurrenceRule, CalendarEvent)

**Files:**
- Create: `app/Domains/Scheduling/Models/{Shift,ShiftAssignment,RecurrenceRule,CalendarEvent}.php`
- Create: `app/Domains/Scheduling/Database/Factories/{ShiftFactory,CalendarEventFactory}.php`
- Test: `tests/Feature/Scheduling/ModelTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/ModelTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\CalendarEvent;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('castet Shift-Felder und ist tenant-scoped', function () {
    $shift = Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00'],
    ]);

    expect($shift->kind)->toBe(ShiftKind::Frueh)
        ->and($shift->timeslots)->toHaveKey('morgens')
        ->and($shift->tenant_id)->toBe($this->tenant->id);
});

it('castet CalendarEvent-Datumsfelder', function () {
    $e = CalendarEvent::create([
        'type' => CalendarEventType::Arzttermin, 'titel' => 'HNO',
        'beginnt_am' => '2026-06-20 10:00:00', 'created_by' => 1,
    ]);

    expect($e->beginnt_am)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($e->type)->toBe(CalendarEventType::Arzttermin)
        ->and($e->istAbgesagt())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ModelTest.php`
Expected: FAIL (Klassen fehlen).

- [ ] **Step 3: `Shift` model**

`app/Domains/Scheduling/Models/Shift.php`:
```php
<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Scheduling\Database\Factories\ShiftFactory;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'kind', 'beginn', 'ende', 'timeslots', 'aktiv'];

    protected $casts = [
        'kind' => ShiftKind::class,
        'timeslots' => 'array',
        'aktiv' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    protected static function newFactory(): ShiftFactory
    {
        return ShiftFactory::new();
    }
}
```

- [ ] **Step 4: `ShiftAssignment` model**

`app/Domains/Scheduling/Models/ShiftAssignment.php`:
```php
<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends BaseModel
{
    protected $fillable = ['tenant_id', 'user_id', 'shift_id', 'dienst_am', 'notiz'];

    protected $casts = ['dienst_am' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
```

- [ ] **Step 5: `RecurrenceRule` model**

`app/Domains/Scheduling/Models/RecurrenceRule.php`:
```php
<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Support\Models\BaseModel;

class RecurrenceRule extends BaseModel
{
    protected $fillable = ['tenant_id', 'freq', 'intervall', 'byday', 'until', 'count'];

    protected $casts = [
        'freq' => RecurrenceFreq::class,
        'byday' => 'array',
        'until' => 'date',
        'intervall' => 'integer',
        'count' => 'integer',
    ];
}
```

- [ ] **Step 6: `CalendarEvent` model**

`app/Domains/Scheduling/Models/CalendarEvent.php`:
```php
<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Database\Factories\CalendarEventFactory;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'resident_id', 'type', 'titel', 'beschreibung',
        'beginnt_am', 'endet_am', 'ganztaegig', 'recurrence_rule_id', 'abgesagt_am', 'created_by',
    ];

    protected $casts = [
        'type' => CalendarEventType::class,
        'beginnt_am' => 'datetime',
        'endet_am' => 'datetime',
        'abgesagt_am' => 'datetime',
        'ganztaegig' => 'boolean',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(RecurrenceRule::class);
    }

    public function istAbgesagt(): bool
    {
        return $this->abgesagt_am !== null;
    }

    public function istWiederkehrend(): bool
    {
        return $this->recurrence_rule_id !== null;
    }

    public function scopeImZeitraum($q, string $von, string $bis)
    {
        return $q->where('beginnt_am', '<=', $bis)
            ->where(function ($q) use ($von) {
                $q->whereNull('endet_am')->orWhere('endet_am', '>=', $von);
            });
    }

    protected static function newFactory(): CalendarEventFactory
    {
        return CalendarEventFactory::new();
    }
}
```

- [ ] **Step 7: Factories**

`app/Domains/Scheduling/Database/Factories/ShiftFactory.php`:
```php
<?php

namespace App\Domains\Scheduling\Database\Factories;

use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => 'Frühdienst',
            'kind' => ShiftKind::Frueh,
            'beginn' => '06:00',
            'ende' => '14:00',
            'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00'],
            'aktiv' => true,
        ];
    }
}
```

`app/Domains/Scheduling/Database/Factories/CalendarEventFactory.php`:
```php
<?php

namespace App\Domains\Scheduling\Database\Factories;

use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        return [
            'type' => CalendarEventType::Arzttermin,
            'titel' => 'Arzttermin',
            'beginnt_am' => now()->addDay()->setTime(10, 0),
            'endet_am' => now()->addDay()->setTime(10, 30),
            'ganztaegig' => false,
            'created_by' => 1,
        ];
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ModelTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint app/Domains/Scheduling
git add app/Domains/Scheduling tests/Feature/Scheduling/ModelTest.php
git commit -m "feat(scheduling): Shift/ShiftAssignment/RecurrenceRule/CalendarEvent models + factories"
```

---

## Task 3: RecurrenceExpander (Vorkommen für Zeitraum berechnen)

**Files:**
- Create: `app/Domains/Scheduling/Support/RecurrenceExpander.php`
- Test: `tests/Unit/Scheduling/RecurrenceExpanderTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/Scheduling/RecurrenceExpanderTest.php`:
```php
<?php

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Support\RecurrenceExpander;
use Illuminate\Support\Carbon;

it('expandiert tägliche Wiederholung im Zeitraum', function () {
    $start = Carbon::parse('2026-06-01 09:00');
    $rule = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => null, 'count' => null];

    $occ = (new RecurrenceExpander)->expand($start, $rule, '2026-06-01', '2026-06-05');

    expect($occ)->toHaveCount(5)
        ->and($occ[0]->format('Y-m-d H:i'))->toBe('2026-06-01 09:00')
        ->and($occ[4]->format('Y-m-d H:i'))->toBe('2026-06-05 09:00');
});

it('expandiert wöchentlich nach ISO-Wochentagen (Mo+Mi)', function () {
    $start = Carbon::parse('2026-06-01 08:00'); // Montag
    $rule = ['freq' => RecurrenceFreq::Weekly, 'intervall' => 1, 'byday' => [1, 3], 'until' => null, 'count' => null];

    $occ = (new RecurrenceExpander)->expand($start, $rule, '2026-06-01', '2026-06-07');

    expect($occ)->toHaveCount(2)
        ->and($occ[0]->dayOfWeekIso)->toBe(1)
        ->and($occ[1]->dayOfWeekIso)->toBe(3);
});

it('respektiert until und count', function () {
    $start = Carbon::parse('2026-06-01 09:00');
    $ruleUntil = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => '2026-06-03', 'count' => null];
    $ruleCount = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => null, 'count' => 2];

    expect((new RecurrenceExpander)->expand($start, $ruleUntil, '2026-06-01', '2026-06-30'))->toHaveCount(3)
        ->and((new RecurrenceExpander)->expand($start, $ruleCount, '2026-06-01', '2026-06-30'))->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Scheduling/RecurrenceExpanderTest.php`
Expected: FAIL (Klasse fehlt).

- [ ] **Step 3: Implementation**

`app/Domains/Scheduling/Support/RecurrenceExpander.php`:
```php
<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class RecurrenceExpander
{
    // WHY: read-only Expansion einer RFC-5545-Teilmenge (FREQ/INTERVAL/BYDAY/UNTIL/COUNT).
    // Vorkommen werden NICHT persistiert — die UI/Fälligkeitslogik fragt pro Fenster ab.
    // Hard-Cap gegen Endlosschleifen bei until=null & count=null.
    private const HARD_CAP = 1000;

    /**
     * @param  Carbon  $start  Erstes Vorkommen (trägt die Uhrzeit).
     * @param  array{freq:RecurrenceFreq,intervall:int,byday:?array,until:?string,count:?int}  $rule
     * @return array<int, Carbon> Vorkommen (mit Uhrzeit) im Fenster [von, bis], aufsteigend.
     */
    public function expand(Carbon $start, array $rule, string $von, string $bis): array
    {
        $freq = $rule['freq'] instanceof RecurrenceFreq ? $rule['freq'] : RecurrenceFreq::from($rule['freq']);
        $intervall = max(1, (int) ($rule['intervall'] ?? 1));
        $byday = $rule['byday'] ?? null;
        $until = $rule['until'] ? Carbon::parse($rule['until'])->endOfDay() : null;
        $maxCount = $rule['count'] ?? null;

        $fensterVon = Carbon::parse($von)->startOfDay();
        $fensterBis = Carbon::parse($bis)->endOfDay();
        $obergrenze = $until ? $until->min($fensterBis) : $fensterBis;

        $h = (int) $start->format('H');
        $m = (int) $start->format('i');

        $out = [];
        $produziert = 0;
        $stepTag = $start->copy()->startOfDay();
        $iterationen = 0;

        while ($stepTag->lte($obergrenze) && $iterationen < self::HARD_CAP) {
            $iterationen++;
            $kandidaten = $this->kandidatenFuerSchritt($freq, $stepTag, $byday);

            foreach ($kandidaten as $tag) {
                if ($tag->lt($start->copy()->startOfDay())) {
                    continue;
                }
                $occ = $tag->copy()->setTime($h, $m, 0);
                if ($maxCount !== null && $produziert >= $maxCount) {
                    return $out;
                }
                if ($until && $occ->gt($until)) {
                    return $out;
                }
                $produziert++;
                if ($occ->betweenIncluded($fensterVon, $fensterBis)) {
                    $out[] = $occ;
                }
            }

            $stepTag = $this->naechsterSchritt($freq, $stepTag, $intervall);
        }

        return $out;
    }

    /** @return array<int, Carbon> die im aktuellen Schritt erzeugten Tage */
    private function kandidatenFuerSchritt(RecurrenceFreq $freq, Carbon $stepTag, ?array $byday): array
    {
        return match ($freq) {
            RecurrenceFreq::Daily => [$stepTag->copy()],
            RecurrenceFreq::Weekly => $this->wochentageInWoche($stepTag, $byday),
            RecurrenceFreq::Monthly => $this->monatstage($stepTag, $byday),
        };
    }

    /** @return array<int, Carbon> */
    private function wochentageInWoche(Carbon $stepTag, ?array $byday): array
    {
        $tage = $byday ?: [$stepTag->dayOfWeekIso];
        sort($tage);
        $wochenStart = $stepTag->copy()->startOfWeek(Carbon::MONDAY);

        return array_map(fn ($iso) => $wochenStart->copy()->addDays($iso - 1), $tage);
    }

    /** @return array<int, Carbon> */
    private function monatstage(Carbon $stepTag, ?array $byday): array
    {
        $tage = $byday ?: [$stepTag->day];

        return array_values(array_filter(array_map(function ($tag) use ($stepTag) {
            return $tag <= $stepTag->daysInMonth
                ? $stepTag->copy()->setDay($tag)
                : null;
        }, $tage)));
    }

    private function naechsterSchritt(RecurrenceFreq $freq, Carbon $stepTag, int $intervall): Carbon
    {
        return match ($freq) {
            RecurrenceFreq::Daily => $stepTag->copy()->addDays($intervall),
            RecurrenceFreq::Weekly => $stepTag->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($intervall),
            RecurrenceFreq::Monthly => $stepTag->copy()->startOfMonth()->addMonths($intervall),
        };
    }
}
```

> Hinweis: `CarbonPeriod` ist hier importiert für lesbare Alternativen, aber die Schritt-Logik braucht es nicht — Import entfernen, falls Pint/PHPStan ihn als unused meldet.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Scheduling/RecurrenceExpanderTest.php`
Expected: PASS (3 Tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Domains/Scheduling
git add app/Domains/Scheduling/Support/RecurrenceExpander.php tests/Unit/Scheduling/RecurrenceExpanderTest.php
git commit -m "feat(scheduling): RecurrenceExpander (FREQ/INTERVAL/BYDAY/UNTIL/COUNT)"
```

---

## Task 4: DTOs + Actions (CreateShift, AssignShift, CreateCalendarEvent, CancelCalendarEvent)

**Files:**
- Create: `app/Domains/Scheduling/Data/{ShiftData,ShiftAssignmentData,RecurrenceData,CalendarEventData}.php`
- Create: `app/Domains/Scheduling/Actions/{CreateShift,AssignShift,CreateCalendarEvent,CancelCalendarEvent}.php`
- Test: `tests/Feature/Scheduling/ActionsTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/ActionsTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Actions\CancelCalendarEvent;
use App\Domains\Scheduling\Actions\CreateCalendarEvent;
use App\Domains\Scheduling\Actions\CreateShift;
use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Data\RecurrenceData;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Data\ShiftData;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ShiftAssignment;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('legt eine Schicht an', function () {
    $shift = (new CreateShift)->handle(new ShiftData(
        name: 'Spät', kind: ShiftKind::Spaet, beginn: '14:00', ende: '22:00',
        timeslots: ['nachmittags' => '15:00', 'abends' => '18:00'],
    ));

    expect($shift->name)->toBe('Spät')->and($shift->timeslots)->toHaveKey('abends');
});

it('weist eine Schicht idempotent zu (kein Doppeleintrag)', function () {
    $shift = (new CreateShift)->handle(new ShiftData(name: 'Früh', kind: ShiftKind::Frueh, beginn: '06:00', ende: '14:00'));

    $data = new ShiftAssignmentData(user_id: $this->user->id, shift_id: $shift->id, dienst_am: '2026-06-15');
    (new AssignShift)->handle($data);
    (new AssignShift)->handle($data);

    expect(ShiftAssignment::count())->toBe(1);
});

it('legt einen wiederkehrenden Kalendertermin samt RecurrenceRule an und sagt ihn ab', function () {
    $event = (new CreateCalendarEvent)->handle(new CalendarEventData(
        type: CalendarEventType::Therapie, titel: 'Physio', beginnt_am: '2026-06-15 11:00:00',
        endet_am: '2026-06-15 11:30:00', created_by: $this->user->id,
        recurrence: new RecurrenceData(freq: RecurrenceFreq::Weekly, byday: [1], intervall: 1),
    ));

    expect($event->istWiederkehrend())->toBeTrue()
        ->and($event->recurrenceRule->freq)->toBe(RecurrenceFreq::Weekly);

    (new CancelCalendarEvent)->handle($event);
    expect($event->fresh()->istAbgesagt())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ActionsTest.php`
Expected: FAIL (Klassen fehlen).

- [ ] **Step 3: DTOs**

`app/Domains/Scheduling/Data/ShiftData.php`:
```php
<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\ShiftKind;
use Spatie\LaravelData\Data;

class ShiftData extends Data
{
    public function __construct(
        public string $name,
        public ShiftKind $kind,
        public string $beginn,
        public string $ende,
        public ?array $timeslots = null,
        public bool $aktiv = true,
    ) {}
}
```

`app/Domains/Scheduling/Data/ShiftAssignmentData.php`:
```php
<?php

namespace App\Domains\Scheduling\Data;

use Spatie\LaravelData\Data;

class ShiftAssignmentData extends Data
{
    public function __construct(
        public int $user_id,
        public int $shift_id,
        public string $dienst_am,
        public ?string $notiz = null,
    ) {}
}
```

`app/Domains/Scheduling/Data/RecurrenceData.php`:
```php
<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use Spatie\LaravelData\Data;

class RecurrenceData extends Data
{
    public function __construct(
        public RecurrenceFreq $freq,
        public ?array $byday = null,
        public int $intervall = 1,
        public ?string $until = null,
        public ?int $count = null,
    ) {}
}
```

`app/Domains/Scheduling/Data/CalendarEventData.php`:
```php
<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\CalendarEventType;
use Spatie\LaravelData\Data;

class CalendarEventData extends Data
{
    public function __construct(
        public CalendarEventType $type,
        public string $titel,
        public string $beginnt_am,
        public int $created_by,
        public ?int $resident_id = null,
        public ?string $beschreibung = null,
        public ?string $endet_am = null,
        public bool $ganztaegig = false,
        public ?RecurrenceData $recurrence = null,
    ) {}
}
```

- [ ] **Step 4: Actions**

`app/Domains/Scheduling/Actions/CreateShift.php`:
```php
<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ShiftData;
use App\Domains\Scheduling\Models\Shift;

class CreateShift
{
    public function handle(ShiftData $data): Shift
    {
        return Shift::create($data->toArray());
    }
}
```

`app/Domains/Scheduling/Actions/AssignShift.php`:
```php
<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\ShiftAssignment;

class AssignShift
{
    // WHY: Dienstplan-Zuweisung ist idempotent — derselbe (user, shift, tag) darf nur einmal existieren.
    public function handle(ShiftAssignmentData $data): ShiftAssignment
    {
        return ShiftAssignment::firstOrCreate(
            ['user_id' => $data->user_id, 'shift_id' => $data->shift_id, 'dienst_am' => $data->dienst_am],
            ['notiz' => $data->notiz],
        );
    }
}
```

`app/Domains/Scheduling/Actions/CreateCalendarEvent.php`:
```php
<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Models\RecurrenceRule;
use Illuminate\Support\Facades\DB;

class CreateCalendarEvent
{
    public function handle(CalendarEventData $data): CalendarEvent
    {
        return DB::transaction(function () use ($data) {
            $ruleId = null;
            if ($data->recurrence) {
                $ruleId = RecurrenceRule::create($data->recurrence->toArray())->id;
            }

            return CalendarEvent::create([
                'resident_id' => $data->resident_id,
                'type' => $data->type,
                'titel' => $data->titel,
                'beschreibung' => $data->beschreibung,
                'beginnt_am' => $data->beginnt_am,
                'endet_am' => $data->endet_am,
                'ganztaegig' => $data->ganztaegig,
                'recurrence_rule_id' => $ruleId,
                'created_by' => $data->created_by,
            ]);
        });
    }
}
```

`app/Domains/Scheduling/Actions/CancelCalendarEvent.php`:
```php
<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Models\CalendarEvent;

class CancelCalendarEvent
{
    // WHY: Termine werden nicht hart gelöscht (Audit/Historie) — Absage über abgesagt_am.
    public function handle(CalendarEvent $event): CalendarEvent
    {
        $event->update(['abgesagt_am' => now()]);

        return $event;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ActionsTest.php`
Expected: PASS (3 Tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Domains/Scheduling
git add app/Domains/Scheduling tests/Feature/Scheduling/ActionsTest.php
git commit -m "feat(scheduling): DTOs + Actions (CreateShift/AssignShift/CreateCalendarEvent/CancelCalendarEvent)"
```

---

## Task 5: ShiftClock + TimeslotClock an Schicht-Konfiguration anbinden

**Ziel:** Die Standard-Uhrzeiten je Tageszeit kommen künftig aus den `Shift`-Datensätzen des aktuellen Mandanten. `TimeslotClock` bleibt als API erhalten (es wird von `GenerateAdministrations` genutzt), delegiert aber an `ShiftClock`. Fällt für einen Slot keine Schicht-Konfiguration vor, greift der bisherige `config('medication.timeslot_clock')`-Default — so bleiben alle Plan-5-Tests grün.

**Files:**
- Create: `app/Domains/Scheduling/Support/ShiftClock.php`
- Modify: `app/Domains/Medication/Support/TimeslotClock.php`
- Test: `tests/Feature/Scheduling/ShiftClockTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/ShiftClockTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Support\TimeslotClock;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Support\ShiftClock;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('liest die Slot-Uhrzeit aus der Schicht-Konfiguration des Mandanten', function () {
    Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['morgens' => '07:30'],
    ]);

    expect(ShiftClock::for(AdministrationTimeslot::Morgens))->toBe('07:30')
        ->and(TimeslotClock::for(AdministrationTimeslot::Morgens))->toBe('07:30');
});

it('fällt ohne Schicht-Konfiguration auf den config-Default zurück', function () {
    // kein Shift angelegt → Default aus config/medication.php
    expect(TimeslotClock::for(AdministrationTimeslot::Mittags))->toBe('12:00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ShiftClockTest.php`
Expected: FAIL (`ShiftClock` fehlt).

- [ ] **Step 3: `ShiftClock`**

`app/Domains/Scheduling/Support/ShiftClock.php`:
```php
<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Scheduling\Models\Shift;

class ShiftClock
{
    // WHY: zentrale Zeitquelle. Schicht-`timeslots` (JSON: slot => HH:MM) überschreiben den
    // statischen config-Default je Mandant. Null-Rückgabe = keine Schicht-Konfiguration vorhanden.
    public static function for(AdministrationTimeslot $slot): ?string
    {
        if (! app(CurrentTenant::class)->id()) {
            return null;
        }

        $treffer = Shift::query()
            ->where('aktiv', true)
            ->get()
            ->map(fn (Shift $s) => $s->timeslots[$slot->value] ?? null)
            ->filter()
            ->first();

        return $treffer ?: null;
    }
}
```

- [ ] **Step 4: `TimeslotClock` delegiert an `ShiftClock` (mit Fallback)**

`app/Domains/Medication/Support/TimeslotClock.php`:
```php
<?php

namespace App\Domains\Medication\Support;

use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Scheduling\Support\ShiftClock;

class TimeslotClock
{
    // WHY: Schicht-Konfiguration (Plan 8) hat Vorrang vor dem statischen config-Default (Plan 5).
    public static function for(AdministrationTimeslot $slot): string
    {
        return ShiftClock::for($slot)
            ?? config('medication.timeslot_clock.'.$slot->value, '12:00');
    }
}
```

- [ ] **Step 5: Run test to verify it passes — und Medikations-Regressionssuite**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ShiftClockTest.php tests/Feature/Medication`
Expected: PASS (alle Plan-5-Tests bleiben grün, da ohne Shift der config-Default greift).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Domains
git add app/Domains/Scheduling/Support/ShiftClock.php app/Domains/Medication/Support/TimeslotClock.php tests/Feature/Scheduling/ShiftClockTest.php
git commit -m "feat(scheduling): ShiftClock als zentrale Zeitquelle; TimeslotClock delegiert mit config-Fallback"
```

---

## Task 6: ShiftSeeder + Standard-Schichten je Mandant

**Files:**
- Create: `app/Domains/Scheduling/Database/Seeders/ShiftSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (ShiftSeeder je Mandant aufrufen, analog `MedicationReferenceSeeder`)
- Test: `tests/Feature/Scheduling/ShiftSeederTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/ShiftSeederTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Database\Seeders\ShiftSeeder;
use App\Domains\Scheduling\Models\Shift;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet drei Standard-Schichten idempotent und deckt alle sechs Tageszeiten ab', function () {
    $this->seed(ShiftSeeder::class);
    $this->seed(ShiftSeeder::class);

    expect(Shift::count())->toBe(3);

    $slots = Shift::all()->flatMap(fn ($s) => array_keys($s->timeslots ?? []))->unique()->values()->all();
    expect($slots)->toContain('nacht_mo', 'morgens', 'mittags', 'nachmittags', 'abends', 'nacht_ab');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ShiftSeederTest.php`
Expected: FAIL.

- [ ] **Step 3: `ShiftSeeder`**

`app/Domains/Scheduling/Database/Seeders/ShiftSeeder.php`:
```php
<?php

namespace App\Domains\Scheduling\Database\Seeders;

use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    // Standard-Schichtmodell; Slot-Uhrzeiten spiegeln die bisherigen config/medication-Defaults.
    public function run(): void
    {
        $defaults = [
            ['name' => 'Frühdienst', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
                'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00']],
            ['name' => 'Spätdienst', 'kind' => ShiftKind::Spaet, 'beginn' => '14:00', 'ende' => '22:00',
                'timeslots' => ['nachmittags' => '15:00', 'abends' => '18:00', 'nacht_ab' => '22:00']],
            ['name' => 'Nachtdienst', 'kind' => ShiftKind::Nacht, 'beginn' => '22:00', 'ende' => '06:00',
                'timeslots' => []],
        ];

        foreach ($defaults as $row) {
            Shift::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
```

- [ ] **Step 4: `DatabaseSeeder` erweitern**

In `database/seeders/DatabaseSeeder.php`, dort wo bereits je Mandant `MedicationReferenceSeeder` aufgerufen wird (CurrentTenant ist gesetzt), zusätzlich:
```php
$this->call(\App\Domains\Scheduling\Database\Seeders\ShiftSeeder::class);
```
(Falls die Schleife über Mandanten anders strukturiert ist: ShiftSeeder im selben `CurrentTenant`-Kontext wie MedicationReferenceSeeder ausführen.)

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/ShiftSeederTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Domains/Scheduling database/seeders
git add app/Domains/Scheduling/Database/Seeders/ShiftSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Scheduling/ShiftSeederTest.php
git commit -m "feat(scheduling): ShiftSeeder mit drei Standard-Schichten je Mandant"
```

---

## Task 7: Policies + Dienstplan-Livewire-UI (Leitung)

**Files:**
- Create: `app/Domains/Scheduling/Policies/{ShiftPolicy,CalendarEventPolicy}.php`
- Modify: `app/Providers/AuthServiceProvider.php` (Policies registrieren — falls dort registriert; sonst Auto-Discovery prüfen)
- Create: `app/Livewire/Scheduling/Dienstplan.php`, `resources/views/livewire/scheduling/dienstplan.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Scheduling/DienstplanTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/DienstplanTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Livewire\Scheduling\Dienstplan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('leserecht');
    $this->shift = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00']);
});

it('verweigert Pflegekraft mit nur Leserecht die Dienstplan-Pflege', function () {
    $pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $pfk->assignRole('leserecht');
    $this->actingAs($pfk);

    Livewire::test(Dienstplan::class)->assertForbidden();
});

it('lässt die Leitung eine Schicht zuweisen', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($leitung);

    Livewire::test(Dienstplan::class)
        ->set('userId', $mitarbeiter->id)
        ->set('shiftId', $this->shift->id)
        ->set('dienstAm', '2026-06-15')
        ->call('zuweisen')
        ->assertHasNoErrors();

    expect(ShiftAssignment::where('user_id', $mitarbeiter->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/DienstplanTest.php`
Expected: FAIL.

- [ ] **Step 3: Policies**

`app/Domains/Scheduling/Policies/ShiftPolicy.php`:
```php
<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;

class ShiftPolicy
{
    // WHY: Dienstplan/Schicht-Pflege ist Leitungssache. super-admin erhält Bypass über Gate::before.
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
```

`app/Domains/Scheduling/Policies/CalendarEventPolicy.php`:
```php
<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Models\CalendarEvent;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // alle eingeloggten Rollen dürfen Termine sehen
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function cancel(User $user, CalendarEvent $event): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
```

> Policy-Registrierung: Laravel 13 nutzt i. d. R. Policy-Auto-Discovery nur bei `App\Models`-Konvention. Da die Models unter `App\Domains` liegen, in `App\Providers\AppServiceProvider::boot()` (oder vorhandenem AuthServiceProvider) explizit registrieren:
> ```php
> use Illuminate\Support\Facades\Gate;
> Gate::policy(\App\Domains\Scheduling\Models\Shift::class, \App\Domains\Scheduling\Policies\ShiftPolicy::class);
> Gate::policy(\App\Domains\Scheduling\Models\CalendarEvent::class, \App\Domains\Scheduling\Policies\CalendarEventPolicy::class);
> ```
> Prüfe zuerst, wo bestehende Policies (z. B. `PrescriptionPolicy`, `UserPolicy`) registriert sind, und folge demselben Ort.

- [ ] **Step 4: `Dienstplan` Livewire**

`app/Livewire/Scheduling/Dienstplan.php`:
```php
<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dienstplan extends Component
{
    public ?int $userId = null;

    public ?int $shiftId = null;

    public string $dienstAm = '';

    public function mount(): void
    {
        // WHY: Nav-Verstecken ist keine Zugriffskontrolle — Guard in mount UND Action.
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->dienstAm = today()->toDateString();
    }

    public function zuweisen(AssignShift $assign): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $data = $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'shiftId' => ['required', 'exists:shifts,id'],
            'dienstAm' => ['required', 'date'],
        ]);

        $assign->handle(new ShiftAssignmentData(
            user_id: $data['userId'], shift_id: $data['shiftId'], dienst_am: $data['dienstAm'],
        ));
        session()->flash('status', 'Dienst eingetragen.');
    }

    public function entfernen(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        ShiftAssignment::findOrFail($id)->delete();
        session()->flash('status', 'Dienst entfernt.');
    }

    public function render()
    {
        return view('livewire.scheduling.dienstplan', [
            'users' => User::where('tenant_id', app(CurrentTenant::class)->id())->orderBy('name')->get(),
            'shifts' => Shift::where('aktiv', true)->orderBy('beginn')->get(),
            'eintraege' => ShiftAssignment::with(['user', 'shift'])
                ->whereDate('dienst_am', $this->dienstAm)->orderBy('shift_id')->get(),
        ]);
    }
}
```

- [ ] **Step 5: View**

`resources/views/livewire/scheduling/dienstplan.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Planung</p><h1>Dienstplan</h1>
        <p class="lead">Schichten den Mitarbeitenden zuweisen.</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Dienst eintragen</h3></div>
        <form wire:submit="zuweisen">
            <div class="form-row">
                <div class="field"><label>Datum</label><input type="date" wire:model.live="dienstAm" /></div>
                <div class="field"><label>Mitarbeiter:in</label>
                    <select wire:model="userId">
                        <option value="">– wählen –</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>@error('userId')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Schicht</label>
                    <select wire:model="shiftId">
                        <option value="">– wählen –</option>
                        @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->beginn }}–{{ $s->ende }})</option>@endforeach
                    </select>@error('shiftId')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary">Eintragen</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Dienste am {{ $dienstAm }}</h3></div>
        <table class="data"><thead><tr><th>Schicht</th><th>Mitarbeiter:in</th><th></th></tr></thead>
            <tbody>
                @forelse ($eintraege as $e)
                    <tr>
                        <td><b>{{ $e->shift?->name }}</b></td>
                        <td>{{ $e->user?->name }}</td>
                        <td><button class="btn btn-link" wire:click="entfernen({{ $e->id }})">Entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Keine Dienste eingetragen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 6: Route + Nav**

In `routes/web.php` innerhalb der `['auth','tenant']`-Gruppe (Import `use App\Livewire\Scheduling\Dienstplan;` oben ergänzen):
```php
Route::get('/dienstplan', Dienstplan::class)->name('dienstplan');
```
In `resources/views/layouts/app.blade.php` einen Nav-Link für die Leitung ergänzen (analog zu bestehenden rollen-gebundenen Links — am Muster der Admin-/Controlling-Links orientieren):
```blade
@can('manage', \App\Domains\Scheduling\Models\Shift::class)
    <a href="{{ route('dienstplan') }}" @class(['active' => request()->routeIs('dienstplan')])>Dienstplan</a>
@endcan
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/DienstplanTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app routes
git add app/Domains/Scheduling/Policies app/Livewire/Scheduling/Dienstplan.php resources/views/livewire/scheduling/dienstplan.blade.php routes/web.php resources/views/layouts/app.blade.php app/Providers tests/Feature/Scheduling/DienstplanTest.php
git commit -m "feat(scheduling): Policies + Dienstplan-UI (Leitungs-Guard in mount und Action)"
```

---

## Task 8: Kalender-Livewire-UI (Termine anlegen, Monatsfenster, Recurrence-Expansion)

**Files:**
- Create: `app/Livewire/Scheduling/Kalender.php`, `resources/views/livewire/scheduling/kalender.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Scheduling/KalenderTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Scheduling/KalenderTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Livewire\Scheduling\Kalender;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

it('legt einen Termin an', function () {
    Livewire::test(Kalender::class)
        ->set('type', CalendarEventType::Arzttermin->value)
        ->set('titel', 'Zahnarzt')
        ->set('beginntAm', '2026-06-15 10:00')
        ->call('speichern')
        ->assertHasNoErrors();

    expect(CalendarEvent::where('titel', 'Zahnarzt')->count())->toBe(1);
});

it('expandiert wöchentliche Termine im angezeigten Monatsfenster', function () {
    // wöchentlich montags ab 01.06.; im Juni 2026 fünf Montage (1,8,15,22,29)
    CalendarEvent::factory()->create([
        'titel' => 'Physio', 'beginnt_am' => '2026-06-01 11:00',
        'recurrence_rule_id' => \App\Domains\Scheduling\Models\RecurrenceRule::create([
            'freq' => 'weekly', 'intervall' => 1, 'byday' => [1],
        ])->id,
        'created_by' => $this->user->id,
    ]);

    $vorkommen = Livewire::test(Kalender::class)->set('monat', '2026-06')->instance()->vorkommen();

    expect(collect($vorkommen)->where('titel', 'Physio')->count())->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Scheduling/KalenderTest.php`
Expected: FAIL.

- [ ] **Step 3: `Kalender` Livewire**

`app/Livewire/Scheduling/Kalender.php`:
```php
<?php

namespace App\Livewire\Scheduling;

use App\Domains\Scheduling\Actions\CreateCalendarEvent;
use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Data\RecurrenceData;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Support\RecurrenceExpander;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Kalender extends Component
{
    public string $monat = '';

    public string $type = 'arzttermin';

    public string $titel = '';

    public string $beginntAm = '';

    public ?string $endetAm = null;

    public ?int $residentId = null;

    public ?string $wiederholung = null; // null|daily|weekly|monthly

    public ?array $byday = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->monat = now()->format('Y-m');
    }

    public function speichern(CreateCalendarEvent $create): void
    {
        $this->authorize('create', CalendarEvent::class);
        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', array_column(CalendarEventType::cases(), 'value'))],
            'titel' => ['required', 'string', 'max:255'],
            'beginntAm' => ['required', 'date'],
            'endetAm' => ['nullable', 'date', 'after_or_equal:beginntAm'],
            'residentId' => ['nullable', 'exists:residents,id'],
            'wiederholung' => ['nullable', 'in:daily,weekly,monthly'],
        ]);

        $recurrence = $data['wiederholung']
            ? new RecurrenceData(freq: RecurrenceFreq::from($data['wiederholung']), byday: $this->byday)
            : null;

        $create->handle(new CalendarEventData(
            type: CalendarEventType::from($data['type']),
            titel: $data['titel'],
            beginnt_am: Carbon::parse($data['beginntAm'])->toDateTimeString(),
            created_by: auth()->id(),
            resident_id: $data['residentId'] ?? null,
            endet_am: $data['endetAm'] ?? null,
            recurrence: $recurrence,
        ));

        $this->reset('titel', 'beginntAm', 'endetAm', 'wiederholung', 'byday');
        session()->flash('status', 'Termin gespeichert.');
    }

    /** @return array<int, array{titel:string, type:CalendarEventType, zeitpunkt:Carbon, resident_id:?int, event_id:int}> */
    public function vorkommen(): array
    {
        $von = Carbon::parse($this->monat.'-01')->startOfMonth();
        $bis = $von->copy()->endOfMonth();
        $expander = new RecurrenceExpander;

        $events = CalendarEvent::with('recurrenceRule')
            ->whereNull('abgesagt_am')
            ->where(function ($q) use ($bis) {
                $q->whereNotNull('recurrence_rule_id')->orWhere('beginnt_am', '<=', $bis);
            })
            ->get();

        $out = [];
        foreach ($events as $e) {
            if ($e->istWiederkehrend() && $e->recurrenceRule) {
                $rule = $e->recurrenceRule->only(['freq', 'intervall', 'byday', 'until', 'count']);
                foreach ($expander->expand($e->beginnt_am, $rule, $von->toDateString(), $bis->toDateString()) as $occ) {
                    $out[] = $this->row($e, $occ);
                }
            } elseif ($e->beginnt_am->betweenIncluded($von, $bis)) {
                $out[] = $this->row($e, $e->beginnt_am);
            }
        }

        usort($out, fn ($a, $b) => $a['zeitpunkt'] <=> $b['zeitpunkt']);

        return $out;
    }

    private function row(CalendarEvent $e, Carbon $zeitpunkt): array
    {
        return [
            'event_id' => $e->id,
            'titel' => $e->titel,
            'type' => $e->type,
            'resident_id' => $e->resident_id,
            'zeitpunkt' => $zeitpunkt,
        ];
    }

    public function render()
    {
        return view('livewire.scheduling.kalender', [
            'vorkommen' => $this->vorkommen(),
            'typen' => CalendarEventType::cases(),
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/scheduling/kalender.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Planung</p><h1>Kalender</h1>
        <p class="lead">Termine und wiederkehrende Maßnahmen.</p></div>
        <div class="field"><label>Monat</label><input type="month" wire:model.live="monat" /></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neuer Termin</h3></div>
        <form wire:submit="speichern">
            <div class="form-row">
                <div class="field"><label>Art</label>
                    <select wire:model="type">
                        @foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Titel</label><input wire:model="titel" />@error('titel')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row">
                <div class="field"><label>Beginn</label><input type="datetime-local" wire:model="beginntAm" />@error('beginntAm')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Ende</label><input type="datetime-local" wire:model="endetAm" />@error('endetAm')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Wiederholung</label>
                    <select wire:model="wiederholung">
                        <option value="">einmalig</option>
                        <option value="daily">täglich</option>
                        <option value="weekly">wöchentlich</option>
                        <option value="monthly">monatlich</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Termine im {{ $monat }}</h3></div>
        <table class="data"><thead><tr><th>Wann</th><th>Art</th><th>Titel</th></tr></thead>
            <tbody>
                @forelse ($vorkommen as $v)
                    <tr>
                        <td>{{ $v['zeitpunkt']->format('d.m.Y H:i') }}</td>
                        <td>{{ $v['type']->label() }}</td>
                        <td><b>{{ $v['titel'] }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Keine Termine in diesem Monat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Route + Nav**

In `routes/web.php` (`['auth','tenant']`-Gruppe, Import `use App\Livewire\Scheduling\Kalender;`):
```php
Route::get('/kalender', Kalender::class)->name('kalender');
```
Nav-Link in `resources/views/layouts/app.blade.php` (alle Rollen):
```blade
<a href="{{ route('kalender') }}" @class(['active' => request()->routeIs('kalender')])>Kalender</a>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Scheduling/KalenderTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Scheduling/Kalender.php resources/views/livewire/scheduling/kalender.blade.php routes/web.php resources/views/layouts/app.blade.php tests/Feature/Scheduling/KalenderTest.php
git commit -m "feat(scheduling): Kalender-UI mit Recurrence-Expansion im Monatsfenster"
```

---

## Task 9: Gesamt-Suite + Pint + Push

- [ ] **Step 1: Gesamte Suite grün**

Run:
```bash
./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print('tests',d['tests'],'passed',d['passed'],'failed',d.get('failed'))"
```
Expected: alle bestehenden Plan-1–7-Tests + die neuen Scheduling-Tests grün, `failed` = 0/None. Besonders auf `tests/Feature/Medication` achten (TimeslotClock-Umstellung).

- [ ] **Step 2: Pint clean**

Run: `vendor/bin/pint --test`
Expected: keine Findings. Falls doch: `vendor/bin/pint` und neu committen.

- [ ] **Step 3: Push**

```bash
git push origin <branch>
```
(opcare ist push-freigegeben für master; dieser Plan wird subagent-driven auf einem Feature-Branch umgesetzt und danach gemergt.)

---

## Self-Review-Ergebnis (Autor)

**Spec coverage:** Schichten (`Shift`, Task 1/2/6) ✓; Dienstplan (`ShiftAssignment` + UI, Task 1/2/4/7) ✓; Kalender/Termine + Recurrence (`CalendarEvent`/`RecurrenceRule`/`RecurrenceExpander` + UI, Task 1/2/3/4/8) ✓; Zeitbezug-Fundament/Ablösung der hartkodierten Medikations-Zeiten (`ShiftClock` ← `TimeslotClock`, Task 5) ✓; Europe/Berlin + UTC-Casts (datetime-Casts, App-tz unverändert) ✓; KEIN eigener Kernel (Task-Hinweise + Nutzung `routes/console.php`) ✓.

**Placeholder-Scan:** Keine TODO/TBD; jeder Code-Schritt enthält vollständigen Code. Einzige bewusste Unschärfe: exakter Ort der Policy-Registrierung und der Mandanten-Seed-Schleife — beide mit klarer Anweisung „bestehendem Muster folgen" versehen, weil sie vom Ist-Stand der Plan-4-Implementierung abhängen.

**Typ-Konsistenz:** `ShiftClock::for()` gibt `?string` (Null-Fallback) zurück, `TimeslotClock::for()` weiter `string` — bewusst, Fallback in TimeslotClock. `RecurrenceExpander::expand()`-Signatur identisch in Test (Task 3) und Nutzung (Task 8). Enum-Werte (`ShiftKind`, `RecurrenceFreq`, `CalendarEventType`) in Migrationen als `string` gespeichert, in Models gecastet — konsistent mit Plan-5-Konvention (`AdministrationTimeslot`). `dienst_am`/`beginnt_am` Casts (`date`/`datetime`) konsistent zwischen Migration, Model, Test.
