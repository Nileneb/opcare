# OPCare — Plan 5: Medikation / BHP (Behandlungspflege) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das Medikations- und Behandlungspflege-Modul: ärztliche **Verordnung** → **Stellplan** (Turnus/Dosis je Tageszeit) → automatisch generierte **Gaben** → **Quittierung am Bett** mit **Bestandsbuchung**, plus Bedarfsmedikation, BtM-Kennzeichnung und **Vitalwerte**. Manipulationssicher (append-only Gaben, Audit-Trail).

**Architecture:** Neue Domäne `App\Domains\Medication`. Übernimmt OPDEs bewährtes Modell (Prescription / PrescriptionSchedule / BHP / MedStock / MedStockTransaction), modernisiert: ein Stellplan trägt Dosis je Tageszeit als JSON (statt 7 Wochentag-Spalten als JSON-Array), Turnus als `frequenz + intervall + wochentage`. Gaben (`MedicationAdministration`) sind **Ereignisse** (append-only, kein Hard-Delete; Korrektur über Status + Notiz, Audit via activitylog). `GenerateAdministrations` erzeugt aus einem Stellplan die geplanten Gaben für einen Zeitraum (idempotent). Quittierung bucht den Bestand über `MedStockTransaction` ab. Alles tenant-scoped (`BaseModel`).

**Tech Stack:** wie Plan 1–4 (Laravel 13, PHP 8.4, PostgreSQL, Livewire 4, Pest 3). Tenant-Scope/CurrentTenant/BaseModel aus Plan 1, Versionable nicht nötig (Gaben sind Events).

**Voraussetzung:** Plan 1 (Resident, BaseModel, Tenant, Rollen, Physician). Plan 4 empfohlen (Tenancy gehärtet), nicht zwingend.

**Referenz:** OPDE-Domänenkarte Abschnitt 1 (Prescription/PrescriptionSchedule/BHP/MedProducts/TradeForm/MedInventory/MedStock/MedStockTransaction/Situations/GP). Spec §Scope „Bewusst später: Medikation/BHP".

---

## File Structure (Plan 5)

```
app/Domains/Medication/
├── Enums/{AdministrationTimeslot, AdministrationStatus, ScheduleFrequency, VitalType, StockStatus, StockTransactionType}.php
├── Models/{TradeForm, MedProduct, Situation, Prescription, PrescriptionSchedule,
│           MedicationAdministration, MedInventory, MedStock, MedStockTransaction, VitalReading}.php
├── Data/{PrescriptionData, ScheduleData, AdministerData, VitalData, StockData}.php
├── Actions/{CreatePrescription, AddSchedule, DiscontinuePrescription, GenerateAdministrations,
│            AdministerMedication, RefuseMedication, AddStock, RecordVital}.php
├── Support/TimeslotClock.php          # Standard-Uhrzeiten je Tageszeit (konfigurierbar)
├── Policies/{PrescriptionPolicy, MedicationAdministrationPolicy}.php
└── Database/Factories/{...}.php
app/Livewire/Medication/{Stellplan, Verordnungen}.php (+ views)
config/medication.php
database/migrations/2026_06_04_0004xx_*.php
tests/Feature/Medication/...
```

---

## Task 1: Enums + Konfiguration

**Files:**
- Create: 6 Enums (s. o.), `config/medication.php`, `app/Domains/Medication/Support/TimeslotClock.php`
- Test: `tests/Feature/Medication/EnumsTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/EnumsTest.php`:
```php
<?php

use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Support\TimeslotClock;

it('kennt sechs feste Tageszeiten + Bedarf und liefert Standard-Uhrzeiten', function () {
    expect(AdministrationTimeslot::scheduled())->toHaveCount(6)
        ->and(AdministrationTimeslot::Morgens->label())->toBe('Morgens')
        ->and(TimeslotClock::for(AdministrationTimeslot::Morgens))->toBe('08:00');
});

it('liefert Einheiten je Vitalwert', function () {
    expect(VitalType::Blutdruck->einheit())->toBe('mmHg')
        ->and(VitalType::Schmerz->einheit())->toBe('NRS 0–10');
});
```

- [ ] **Step 2: AdministrationTimeslot**

`app/Domains/Medication/Enums/AdministrationTimeslot.php`:
```php
<?php
namespace App\Domains\Medication\Enums;

enum AdministrationTimeslot: string
{
    case NachtMo = 'nacht_mo';
    case Morgens = 'morgens';
    case Mittags = 'mittags';
    case Nachmittags = 'nachmittags';
    case Abends = 'abends';
    case NachtAb = 'nacht_ab';
    case BeiBedarf = 'bei_bedarf';

    /** @return array<int, self> die 6 planbaren Tageszeiten (ohne Bedarf) */
    public static function scheduled(): array
    {
        return [self::NachtMo, self::Morgens, self::Mittags, self::Nachmittags, self::Abends, self::NachtAb];
    }

    public function label(): string
    {
        return match ($this) {
            self::NachtMo => 'Nacht (früh)', self::Morgens => 'Morgens', self::Mittags => 'Mittags',
            self::Nachmittags => 'Nachmittags', self::Abends => 'Abends', self::NachtAb => 'Nacht (spät)',
            self::BeiBedarf => 'Bei Bedarf',
        };
    }
}
```

- [ ] **Step 3: weitere Enums**

`AdministrationStatus.php`: `Geplant='geplant'`, `Gegeben='gegeben'`, `Abgelehnt='abgelehnt'`, `Ausgelassen='ausgelassen'`.
`ScheduleFrequency.php`: `Taeglich='taeglich'`, `Woechentlich='woechentlich'`, `Monatlich='monatlich'`, `BeiBedarf='bei_bedarf'`.
`StockStatus.php`: `Vorraetig='vorraetig'`, `Angebrochen='angebrochen'`, `Leer='leer'`, `Verfallen='verfallen'`.
`StockTransactionType.php`: `Zugang='zugang'`, `Entnahme='entnahme'`, `Korrektur='korrektur'`, `Verfall='verfall'`.
`VitalType.php` mit `einheit()` + `label()`:
```php
<?php
namespace App\Domains\Medication\Enums;

enum VitalType: string
{
    case Blutdruck = 'blutdruck';      // syst./diast. als zwei Messungen oder im Wert kodiert
    case Puls = 'puls';
    case Temperatur = 'temperatur';
    case Gewicht = 'gewicht';
    case Blutzucker = 'blutzucker';
    case Schmerz = 'schmerz';
    case SpO2 = 'spo2';
    case Atemfrequenz = 'atemfrequenz';

    public function einheit(): string
    {
        return match ($this) {
            self::Blutdruck => 'mmHg', self::Puls => '/min', self::Temperatur => '°C',
            self::Gewicht => 'kg', self::Blutzucker => 'mg/dl', self::Schmerz => 'NRS 0–10',
            self::SpO2 => '%', self::Atemfrequenz => '/min',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Blutdruck => 'Blutdruck', self::Puls => 'Puls', self::Temperatur => 'Temperatur',
            self::Gewicht => 'Gewicht', self::Blutzucker => 'Blutzucker', self::Schmerz => 'Schmerz',
            self::SpO2 => 'Sauerstoffsättigung', self::Atemfrequenz => 'Atemfrequenz',
        };
    }
}
```

- [ ] **Step 4: Config + TimeslotClock**

`config/medication.php`:
```php
<?php
return [
    // Standard-Uhrzeiten je Tageszeit (Einrichtung kann via tenant settings überschreiben).
    'timeslot_clock' => [
        'nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00',
        'nachmittags' => '15:00', 'abends' => '18:00', 'nacht_ab' => '22:00',
    ],
];
```
`app/Domains/Medication/Support/TimeslotClock.php`:
```php
<?php
namespace App\Domains\Medication\Support;

use App\Domains\Medication\Enums\AdministrationTimeslot;

class TimeslotClock
{
    public static function for(AdministrationTimeslot $slot): string
    {
        return config('medication.timeslot_clock.'.$slot->value, '12:00');
    }
}
```

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Medication/EnumsTest.php
git add -A && git commit -m "feat(medication): enums, timeslot config + clock"
```

---

## Task 2: Stamm-Tabellen — Darreichung, Medikament, Bedarf-Situation

**Files:**
- Create: Migrationen `trade_forms`, `med_products`, `situations`; Modelle gleichen Namens; Factories
- Test: `tests/Feature/Medication/MedProductTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/MedProductTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\{MedProduct, TradeForm};

beforeEach(function () {
    app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a']));
});

it('legt ein Medikament mit Darreichungsform an', function () {
    $tf = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stück', 'teilbar' => true]);
    $p = MedProduct::create(['name' => 'Ramipril', 'wirkstoff' => 'Ramipril', 'staerke' => '5 mg', 'trade_form_id' => $tf->id, 'btm' => false]);

    expect($p->tradeForm->name)->toBe('Tablette')->and($p->btm)->toBeFalse();
});
```

- [ ] **Step 2: Migrationen**

`2026_06_04_000400_create_trade_forms_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('trade_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // Tablette/Kapsel/Tropfen/Injektion/Salbe
            $table->string('einheit');              // Stück/ml/mg/Hub
            $table->boolean('teilbar')->default(false);
            $table->timestamps();
            $table->index('tenant_id');
        });
    }
    public function down(): void { Schema::dropIfExists('trade_forms'); }
};
```
`...000401_create_med_products_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('med_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                 // Handelsname
            $table->string('wirkstoff')->nullable();
            $table->string('staerke')->nullable();  // "5 mg"
            $table->string('atc_code')->nullable();
            $table->string('pzn')->nullable();      // Pharmazentralnummer
            $table->boolean('btm')->default(false); // Betäubungsmittel
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
        });
    }
    public function down(): void { Schema::dropIfExists('med_products'); }
};
```
`...000402_create_situations_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('situations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // "Schmerzen", "Unruhe", "Schlaflosigkeit"
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('situations'); }
};
```

- [ ] **Step 3: Modelle (alle `extends BaseModel`)**

`TradeForm.php`: `$fillable = ['tenant_id','name','einheit','teilbar']; $casts = ['teilbar'=>'boolean'];` + `products(): HasMany`.
`Situation.php`: `$fillable = ['tenant_id','name'];`.
`MedProduct.php`:
```php
<?php
namespace App\Domains\Medication\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedProduct extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'trade_form_id', 'name', 'wirkstoff', 'staerke', 'atc_code', 'pzn', 'btm'];
    protected $casts = ['btm' => 'boolean'];

    public function tradeForm(): BelongsTo { return $this->belongsTo(TradeForm::class); }

    protected static function newFactory(): \App\Domains\Medication\Database\Factories\MedProductFactory
    {
        return \App\Domains\Medication\Database\Factories\MedProductFactory::new();
    }
}
```

- [ ] **Step 4: Factory** `MedProductFactory` (name fake word, btm false, trade_form_id => TradeForm::factory()). `TradeFormFactory` (name 'Tablette', einheit 'Stück').

- [ ] **Step 5: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/MedProductTest.php
git add -A && git commit -m "feat(medication): trade forms, products, situations"
```

---

## Task 3: Verordnung (Prescription) — Migration, Model, Action, Policy

**Files:**
- Create: Migration `prescriptions`, Model `Prescription`, DTO `PrescriptionData`, Action `CreatePrescription`, Policy `PrescriptionPolicy`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Medication/PrescriptionTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/PrescriptionTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erstellt eine Medikamenten-Verordnung', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();

    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id,
        created_by: 1,
        med_product_id: $product->id,
        gueltig_von: now()->toDateString(),
    ));

    expect($rx->resident_id)->toBe($resident->id)
        ->and($rx->medProduct->is($product))->toBeTrue()
        ->and($rx->ist_aktiv)->toBeTrue();
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000410_create_prescriptions_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_product_id')->nullable()->constrained()->nullOnDelete(); // null = reine BHP
            $table->text('bhp_text')->nullable();        // BHP-Maßnahme ohne Medikament (z. B. "RR messen")
            $table->foreignId('physician_id')->nullable()->constrained('physicians')->nullOnDelete();
            $table->foreignId('situation_id')->nullable()->constrained()->nullOnDelete();   // Bedarf-Anlass
            $table->boolean('bei_bedarf')->default(false);
            $table->date('gueltig_von');
            $table->date('gueltig_bis')->nullable();      // null = bis auf Weiteres
            $table->date('abgesetzt_am')->nullable();
            $table->unsignedBigInteger('abgesetzt_von')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->text('hinweis')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('prescriptions'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/Medication/Models/Prescription.php`:
```php
<?php
namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\{Physician, Resident};
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Prescription extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'med_product_id', 'bhp_text', 'physician_id', 'situation_id',
        'bei_bedarf', 'gueltig_von', 'gueltig_bis', 'abgesetzt_am', 'abgesetzt_von', 'created_by', 'hinweis',
    ];
    protected $casts = [
        'bei_bedarf' => 'boolean', 'gueltig_von' => 'date', 'gueltig_bis' => 'date', 'abgesetzt_am' => 'date',
    ];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function medProduct(): BelongsTo { return $this->belongsTo(MedProduct::class); }
    public function physician(): BelongsTo { return $this->belongsTo(Physician::class); }
    public function situation(): BelongsTo { return $this->belongsTo(Situation::class); }
    public function schedules(): HasMany { return $this->hasMany(PrescriptionSchedule::class); }

    public function getIstAktivAttribute(): bool
    {
        return $this->abgesetzt_am === null
            && ($this->gueltig_bis === null || $this->gueltig_bis->isFuture() || $this->gueltig_bis->isToday());
    }

    public function scopeAktiv($q) { return $q->whereNull('abgesetzt_am'); }
}
```

- [ ] **Step 4: DTO + Action + Policy**

`PrescriptionData.php`:
```php
<?php
namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class PrescriptionData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public ?int $med_product_id = null,
        public ?string $bhp_text = null,
        public ?int $physician_id = null,
        public ?int $situation_id = null,
        public bool $bei_bedarf = false,
        public ?string $gueltig_von = null,
        public ?string $gueltig_bis = null,
        public ?string $hinweis = null,
    ) {}
}
```
`CreatePrescription.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Models\Prescription;

class CreatePrescription
{
    public function handle(PrescriptionData $data): Prescription
    {
        return Prescription::create([
            ...$data->toArray(),
            'gueltig_von' => $data->gueltig_von ?? now()->toDateString(),
        ]);
    }
}
```
`PrescriptionPolicy.php`: viewAny (alle Pflegerollen), create/update (`admin`, `pflegefachkraft`). Registrieren `Gate::policy(Prescription::class, PrescriptionPolicy::class)`.

- [ ] **Step 5: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/PrescriptionTest.php
git add -A && git commit -m "feat(medication): prescription model + action + policy"
```

---

## Task 4: Stellplan (PrescriptionSchedule)

**Files:**
- Create: Migration `prescription_schedules`, Model `PrescriptionSchedule`, DTO `ScheduleData`, Action `AddSchedule`
- Test: `tests/Feature/Medication/ScheduleTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/ScheduleTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AddSchedule, CreatePrescription};
use App\Domains\Medication\Data\{PrescriptionData, ScheduleData};
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('hängt einen täglichen Stellplan mit Dosis je Tageszeit an', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id,
    ));

    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::Taeglich->value,
        dosis: ['morgens' => 1, 'abends' => 0.5],
    ));

    expect($schedule->frequenz)->toBe(ScheduleFrequency::Taeglich)
        ->and($schedule->dosis['morgens'])->toBe(1)
        ->and($rx->schedules)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000411_create_prescription_schedules_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('prescription_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('frequenz');                       // taeglich/woechentlich/monatlich/bei_bedarf
            $table->unsignedSmallInteger('intervall')->default(1); // alle X Tage/Wochen/Monate
            $table->jsonb('wochentage')->nullable();          // [1,3,5] (1=Mo) bei woechentlich
            $table->jsonb('dosis');                            // {morgens:1, abends:0.5} ODER [{uhrzeit:"14:00", dosis:1}]
            $table->decimal('max_anzahl_taeglich', 5, 2)->nullable(); // Bedarf: max Gaben/Tag
            $table->decimal('max_einzeldosis', 8, 3)->nullable();
            $table->timestamps();
            $table->index('prescription_id');
        });
    }
    public function down(): void { Schema::dropIfExists('prescription_schedules'); }
};
```

- [ ] **Step 3: Model**

`PrescriptionSchedule.php`:
```php
<?php
namespace App\Domains\Medication\Models;

use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PrescriptionSchedule extends BaseModel
{
    protected $fillable = ['tenant_id', 'prescription_id', 'frequenz', 'intervall', 'wochentage', 'dosis', 'max_anzahl_taeglich', 'max_einzeldosis'];
    protected $casts = [
        'frequenz' => ScheduleFrequency::class, 'wochentage' => 'array', 'dosis' => 'array',
        'intervall' => 'integer', 'max_anzahl_taeglich' => 'decimal:2', 'max_einzeldosis' => 'decimal:3',
    ];

    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function administrations(): HasMany { return $this->hasMany(MedicationAdministration::class); }
}
```

- [ ] **Step 4: DTO + Action**

`ScheduleData.php`:
```php
<?php
namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class ScheduleData extends Data
{
    public function __construct(
        public string $frequenz,
        public array $dosis,                 // {tageszeit: menge} oder [{uhrzeit, dosis}]
        public int $intervall = 1,
        public ?array $wochentage = null,
        public ?float $max_anzahl_taeglich = null,
        public ?float $max_einzeldosis = null,
    ) {}
}
```
`AddSchedule.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Models\{Prescription, PrescriptionSchedule};

class AddSchedule
{
    public function handle(Prescription $prescription, ScheduleData $data): PrescriptionSchedule
    {
        return $prescription->schedules()->create($data->toArray());
    }
}
```

- [ ] **Step 5: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/ScheduleTest.php
git add -A && git commit -m "feat(medication): prescription schedules (turnus + dosis)"
```

---

## Task 5: Gaben (MedicationAdministration) — Migration + Model

**Files:**
- Create: Migration `medication_administrations`, Model `MedicationAdministration`
- Test: `tests/Feature/Medication/AdministrationModelTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/AdministrationModelTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\{AdministrationStatus, AdministrationTimeslot};
use App\Domains\Medication\Models\MedicationAdministration;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('legt eine geplante Gabe an', function () {
    $resident = Resident::factory()->create();
    $a = MedicationAdministration::create([
        'resident_id' => $resident->id,
        'soll_zeitpunkt' => now()->setTime(8, 0),
        'tageszeit' => AdministrationTimeslot::Morgens,
        'dosis' => 1,
        'status' => AdministrationStatus::Geplant,
    ]);

    expect($a->status)->toBe(AdministrationStatus::Geplant)
        ->and($a->tageszeit)->toBe(AdministrationTimeslot::Morgens);
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000412_create_medication_administrations_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('soll_zeitpunkt');
            $table->string('tageszeit');                 // AdministrationTimeslot
            $table->decimal('dosis', 8, 3);
            $table->string('status')->default('geplant'); // geplant/gegeben/abgelehnt/ausgelassen
            $table->timestamp('ist_zeitpunkt')->nullable();
            $table->unsignedBigInteger('quittiert_von')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'soll_zeitpunkt']);
            $table->index(['prescription_schedule_id', 'soll_zeitpunkt']); // Idempotenz-Lookup
        });
    }
    public function down(): void { Schema::dropIfExists('medication_administrations'); }
};
```

- [ ] **Step 3: Model**

`MedicationAdministration.php`:
```php
<?php
namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\{AdministrationStatus, AdministrationTimeslot};
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class MedicationAdministration extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'prescription_schedule_id', 'soll_zeitpunkt', 'tageszeit',
        'dosis', 'status', 'ist_zeitpunkt', 'quittiert_von', 'notiz',
    ];
    protected $casts = [
        'soll_zeitpunkt' => 'datetime', 'ist_zeitpunkt' => 'datetime', 'dosis' => 'decimal:3',
        'tageszeit' => AdministrationTimeslot::class, 'status' => AdministrationStatus::class,
    ];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(PrescriptionSchedule::class, 'prescription_schedule_id'); }
    public function stockTransactions(): HasMany { return $this->hasMany(MedStockTransaction::class, 'administration_id'); }

    public function scopeOffen($q) { return $q->where('status', AdministrationStatus::Geplant->value); }
}
```

- [ ] **Step 4: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/AdministrationModelTest.php
git add -A && git commit -m "feat(medication): administration events model"
```

---

## Task 6: Stellplan → Gaben generieren (GenerateAdministrations)

**Files:**
- Create: `app/Domains/Medication/Actions/GenerateAdministrations.php`
- Test: `tests/Feature/Medication/GenerateAdministrationsTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/GenerateAdministrationsTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AddSchedule, CreatePrescription, GenerateAdministrations};
use App\Domains\Medication\Data\{PrescriptionData, ScheduleData};
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\{MedProduct, MedicationAdministration};

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erzeugt geplante Gaben je Tageszeit über einen Zeitraum — idempotent', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id,
        gueltig_von: '2026-06-01',
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1, 'abends' => 1],
    ));

    $created = app(GenerateAdministrations::class)->handle($schedule, '2026-06-01', '2026-06-03');
    // 3 Tage × 2 Tageszeiten = 6 geplante Gaben
    expect($created)->toBe(6)
        ->and(MedicationAdministration::count())->toBe(6);

    // Erneuter Lauf erzeugt KEINE Duplikate.
    $again = app(GenerateAdministrations::class)->handle($schedule, '2026-06-01', '2026-06-03');
    expect($again)->toBe(0)->and(MedicationAdministration::count())->toBe(6);
});
```

- [ ] **Step 2: Action**

`app/Domains/Medication/Actions/GenerateAdministrations.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\{AdministrationStatus, AdministrationTimeslot, ScheduleFrequency};
use App\Domains\Medication\Models\{MedicationAdministration, PrescriptionSchedule};
use App\Domains\Medication\Support\TimeslotClock;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class GenerateAdministrations
{
    /** Erzeugt geplante Gaben für [von..bis] (inkl.). Idempotent über (schedule, soll_zeitpunkt, tageszeit). */
    public function handle(PrescriptionSchedule $schedule, string $von, string $bis): int
    {
        // Bedarfsmedikation hat keinen festen Stellplan → keine Vorab-Gaben.
        if ($schedule->frequenz === ScheduleFrequency::BeiBedarf) {
            return 0;
        }

        $rx = $schedule->prescription;
        $start = Carbon::parse($von)->max(Carbon::parse($rx->gueltig_von));
        $ende = Carbon::parse($bis);
        if ($rx->gueltig_bis) {
            $ende = $ende->min(Carbon::parse($rx->gueltig_bis));
        }
        if ($start->gt($ende)) {
            return 0;
        }

        $created = 0;
        foreach (CarbonPeriod::create($start, $ende) as $tag) {
            if (! $this->trifftZu($schedule, $tag)) {
                continue;
            }
            foreach (AdministrationTimeslot::scheduled() as $slot) {
                $menge = $schedule->dosis[$slot->value] ?? 0;
                if ($menge <= 0) {
                    continue;
                }
                [$h, $m] = explode(':', TimeslotClock::for($slot));
                $soll = $tag->copy()->setTime((int) $h, (int) $m);

                // Idempotenz: existiert die Gabe schon, überspringen.
                $exists = MedicationAdministration::where('prescription_schedule_id', $schedule->id)
                    ->where('soll_zeitpunkt', $soll)
                    ->where('tageszeit', $slot->value)
                    ->exists();
                if ($exists) {
                    continue;
                }

                MedicationAdministration::create([
                    'resident_id' => $rx->resident_id,
                    'prescription_schedule_id' => $schedule->id,
                    'soll_zeitpunkt' => $soll,
                    'tageszeit' => $slot,
                    'dosis' => $menge,
                    'status' => AdministrationStatus::Geplant,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function trifftZu(PrescriptionSchedule $schedule, Carbon $tag): bool
    {
        return match ($schedule->frequenz) {
            ScheduleFrequency::Taeglich => true,
            ScheduleFrequency::Woechentlich => in_array($tag->dayOfWeekIso, $schedule->wochentage ?? [], true),
            ScheduleFrequency::Monatlich => $tag->day === ($schedule->wochentage[0] ?? 1), // Tag-im-Monat in wochentage[0]
            default => false,
        };
    }
}
```
> WHY(idempotenz): ein nächtlicher Job kann den Stellplan rollierend für die nächsten N Tage materialisieren, ohne Duplikate. Der eindeutige Lookup ist (`prescription_schedule_id`, `soll_zeitpunkt`, `tageszeit`).

- [ ] **Step 3: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Medication/GenerateAdministrationsTest.php
git add -A && git commit -m "feat(medication): generate scheduled administrations (idempotent)"
```

---

## Task 7: Bestand — Inventar, Charge, Buchung (+ AddStock)

**Files:**
- Create: Migrationen `med_inventories`, `med_stocks`, `med_stock_transactions`; Modelle; DTO `StockData`; Action `AddStock`
- Test: `tests/Feature/Medication/StockTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/StockTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\StockStatus;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('bucht einen Bestandszugang und legt Inventar + Charge an', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();

    $stock = app(AddStock::class)->handle(new StockData(
        resident_id: $resident->id, med_product_id: $product->id, menge: 100, einheit: 'Stück',
    ));

    expect((float) $stock->menge_aktuell)->toBe(100.0)
        ->and($stock->status)->toBe(StockStatus::Vorraetig)
        ->and($stock->transactions)->toHaveCount(1); // Zugangs-Buchung
});
```

- [ ] **Step 2: Migrationen**

`...000420_create_med_inventories_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('med_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resident_id', 'med_product_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('med_inventories'); }
};
```
`...000421_create_med_stocks_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('med_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_inventory_id')->constrained()->cascadeOnDelete();
            $table->decimal('menge_initial', 10, 3);
            $table->decimal('menge_aktuell', 10, 3);
            $table->string('einheit');
            $table->string('charge')->nullable();
            $table->date('eingang_am');
            $table->date('geoeffnet_am')->nullable();
            $table->date('verfall_am')->nullable();
            $table->string('status')->default('vorraetig'); // vorraetig/angebrochen/leer/verfallen
            $table->timestamps();
            $table->index('med_inventory_id');
        });
    }
    public function down(): void { Schema::dropIfExists('med_stocks'); }
};
```
`...000422_create_med_stock_transactions_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('med_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->string('typ');                 // zugang/entnahme/korrektur/verfall
            $table->decimal('menge', 10, 3);        // signiert: + Zugang, − Entnahme
            $table->timestamp('gebucht_am');
            $table->unsignedBigInteger('gebucht_von')->nullable();
            $table->timestamps();
            $table->index(['med_stock_id', 'gebucht_am']);
        });
    }
    public function down(): void { Schema::dropIfExists('med_stock_transactions'); }
};
```

- [ ] **Step 3: Modelle**

`MedInventory.php`: `$fillable=['tenant_id','resident_id','med_product_id']`; Relationen `resident()`, `medProduct()`, `stocks(): HasMany`.
`MedStock.php`:
```php
<?php
namespace App\Domains\Medication\Models;

use App\Domains\Medication\Enums\StockStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class MedStock extends BaseModel
{
    protected $fillable = ['tenant_id', 'med_inventory_id', 'menge_initial', 'menge_aktuell', 'einheit', 'charge', 'eingang_am', 'geoeffnet_am', 'verfall_am', 'status'];
    protected $casts = ['menge_initial' => 'decimal:3', 'menge_aktuell' => 'decimal:3', 'eingang_am' => 'date', 'geoeffnet_am' => 'date', 'verfall_am' => 'date', 'status' => StockStatus::class];

    public function inventory(): BelongsTo { return $this->belongsTo(MedInventory::class, 'med_inventory_id'); }
    public function transactions(): HasMany { return $this->hasMany(MedStockTransaction::class); }
}
```
`MedStockTransaction.php`: `$fillable=['tenant_id','med_stock_id','administration_id','typ','menge','gebucht_am','gebucht_von']`; casts `typ`=>StockTransactionType, `menge`=>decimal:3, `gebucht_am`=>datetime; `stock(): BelongsTo`.

- [ ] **Step 4: DTO + AddStock-Action**

`StockData.php`:
```php
<?php
namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class StockData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $med_product_id,
        public float $menge,
        public string $einheit,
        public ?string $charge = null,
        public ?string $verfall_am = null,
    ) {}
}
```
`AddStock.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\{StockStatus, StockTransactionType};
use App\Domains\Medication\Models\{MedInventory, MedStock};
use Illuminate\Support\Facades\DB;

class AddStock
{
    public function handle(StockData $data): MedStock
    {
        return DB::transaction(function () use ($data) {
            $inventory = MedInventory::firstOrCreate([
                'resident_id' => $data->resident_id,
                'med_product_id' => $data->med_product_id,
            ]);

            $stock = $inventory->stocks()->create([
                'menge_initial' => $data->menge,
                'menge_aktuell' => $data->menge,
                'einheit' => $data->einheit,
                'charge' => $data->charge,
                'eingang_am' => now()->toDateString(),
                'verfall_am' => $data->verfall_am,
                'status' => StockStatus::Vorraetig,
            ]);

            $stock->transactions()->create([
                'typ' => StockTransactionType::Zugang,
                'menge' => $data->menge,
                'gebucht_am' => now(),
                'gebucht_von' => auth()->id(),
            ]);

            return $stock;
        });
    }
}
```

- [ ] **Step 5: Migrieren + Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/StockTest.php
git add -A && git commit -m "feat(medication): inventory, stock charges, transactions + addstock"
```

---

## Task 8: Quittierung am Bett (AdministerMedication) + Bestandsabbuchung

**Files:**
- Create: `app/Domains/Medication/Data/AdministerData.php`, `app/Domains/Medication/Actions/{AdministerMedication,RefuseMedication}.php`, Policy `MedicationAdministrationPolicy`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Medication/AdministerTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/AdministerTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AddStock, AdministerMedication, RefuseMedication};
use App\Domains\Medication\Data\{AdministerData, StockData};
use App\Domains\Medication\Enums\{AdministrationStatus, AdministrationTimeslot};
use App\Domains\Medication\Models\{MedProduct, MedicationAdministration};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('quittiert eine Gabe und bucht den Bestand ab', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();
    app(AddStock::class)->handle(new StockData($resident->id, $product->id, 50, 'Stück'));

    $a = MedicationAdministration::create([
        'resident_id' => $resident->id, 'soll_zeitpunkt' => now()->setTime(8, 0),
        'tageszeit' => AdministrationTimeslot::Morgens, 'dosis' => 1, 'status' => AdministrationStatus::Geplant,
    ]);

    app(AdministerMedication::class)->handle($a, new AdministerData(
        quittiert_von: $this->nurse->id, med_product_id: $product->id,
    ));

    $a->refresh();
    expect($a->status)->toBe(AdministrationStatus::Gegeben)
        ->and($a->quittiert_von)->toBe($this->nurse->id)
        ->and($a->ist_zeitpunkt)->not->toBeNull()
        ->and($a->stockTransactions)->toHaveCount(1);
});

it('vermerkt eine Ablehnung ohne Bestandsabbuchung', function () {
    $resident = Resident::factory()->create();
    $a = MedicationAdministration::create([
        'resident_id' => $resident->id, 'soll_zeitpunkt' => now(), 'tageszeit' => AdministrationTimeslot::Abends,
        'dosis' => 1, 'status' => AdministrationStatus::Geplant,
    ]);

    app(RefuseMedication::class)->handle($a, $this->nurse->id, 'Bewohner lehnt ab');
    expect($a->fresh()->status)->toBe(AdministrationStatus::Abgelehnt)
        ->and($a->fresh()->notiz)->toBe('Bewohner lehnt ab');
});
```

- [ ] **Step 2: DTO**

`AdministerData.php`:
```php
<?php
namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class AdministerData extends Data
{
    public function __construct(
        public int $quittiert_von,
        public ?int $med_product_id = null,   // für Bestandsabbuchung; null = reine BHP (keine Buchung)
        public ?float $dosis = null,          // Abweichung von der geplanten Dosis
        public ?string $notiz = null,
    ) {}
}
```

- [ ] **Step 3: AdministerMedication (FEFO-Abbuchung)**

`app/Domains/Medication/Actions/AdministerMedication.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\{AdministrationStatus, StockStatus, StockTransactionType};
use App\Domains\Medication\Models\{MedicationAdministration, MedStock};
use Illuminate\Support\Facades\DB;

class AdministerMedication
{
    public function handle(MedicationAdministration $administration, AdministerData $data): MedicationAdministration
    {
        return DB::transaction(function () use ($administration, $data) {
            $dosis = $data->dosis ?? (float) $administration->dosis;

            $administration->update([
                'status' => AdministrationStatus::Gegeben,
                'ist_zeitpunkt' => now(),
                'quittiert_von' => $data->quittiert_von,
                'notiz' => $data->notiz,
                'dosis' => $dosis,
            ]);

            // Bestandsabbuchung nur bei Medikament (FEFO: First-Expired-First-Out).
            if ($data->med_product_id) {
                $this->bucheBestandAb($administration, $data->med_product_id, $dosis, $data->quittiert_von);
            }

            return $administration;
        });
    }

    private function bucheBestandAb(MedicationAdministration $a, int $productId, float $dosis, int $userId): void
    {
        $stock = MedStock::query()
            ->whereHas('inventory', fn ($q) => $q
                ->where('resident_id', $a->resident_id)
                ->where('med_product_id', $productId))
            ->whereIn('status', [StockStatus::Vorraetig->value, StockStatus::Angebrochen->value])
            ->where('menge_aktuell', '>', 0)
            ->orderByRaw('verfall_am IS NULL, verfall_am ASC') // früheste Verfälle zuerst
            ->orderBy('eingang_am')
            ->first();

        if (! $stock) {
            return; // kein Bestand hinterlegt — Gabe trotzdem dokumentiert (Bestand optional)
        }

        $stock->transactions()->create([
            'administration_id' => $a->id,
            'typ' => StockTransactionType::Entnahme,
            'menge' => -1 * $dosis,
            'gebucht_am' => now(),
            'gebucht_von' => $userId,
        ]);

        $neu = (float) $stock->menge_aktuell - $dosis;
        $stock->update([
            'menge_aktuell' => max(0, $neu),
            'geoeffnet_am' => $stock->geoeffnet_am ?? now()->toDateString(),
            'status' => $neu <= 0 ? StockStatus::Leer : StockStatus::Angebrochen,
        ]);
    }
}
```

- [ ] **Step 4: RefuseMedication + Policy**

`RefuseMedication.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\MedicationAdministration;

class RefuseMedication
{
    /** $status: Abgelehnt (Bewohner verweigert) oder Ausgelassen (z. B. nüchtern/abwesend). */
    public function handle(MedicationAdministration $a, int $userId, string $notiz, AdministrationStatus $status = AdministrationStatus::Abgelehnt): MedicationAdministration
    {
        $a->update(['status' => $status, 'ist_zeitpunkt' => now(), 'quittiert_von' => $userId, 'notiz' => $notiz]);
        return $a;
    }
}
```
`MedicationAdministrationPolicy.php`: viewAny (alle Pflegerollen inkl. leserecht); `administer`/`update` (`admin`,`pflegefachkraft`,`pflegehilfskraft`). Registrieren.
> Append-only: keine `delete`-Methode — Korrekturen laufen über Statuswechsel + Notiz (Audit via activitylog).

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Medication/AdministerTest.php
git add -A && git commit -m "feat(medication): administer/refuse + FEFO stock decrement (append-only)"
```

---

## Task 9: Bedarfsmedikation + Absetzen (DiscontinuePrescription)

**Files:**
- Create: `app/Domains/Medication/Actions/{DiscontinuePrescription,AdministerOnDemand}.php`
- Test: `tests/Feature/Medication/OnDemandAndDiscontinueTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/OnDemandAndDiscontinueTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AddSchedule, AdministerOnDemand, CreatePrescription, DiscontinuePrescription, GenerateAdministrations};
use App\Domains\Medication\Data\{PrescriptionData, ScheduleData};
use App\Domains\Medication\Enums\{AdministrationStatus, ScheduleFrequency};
use App\Domains\Medication\Models\{MedProduct, MedicationAdministration};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('dokumentiert eine Bedarfsgabe innerhalb des Tageslimits', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id, bei_bedarf: true,
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::BeiBedarf->value, dosis: ['bei_bedarf' => 1], max_anzahl_taeglich: 3,
    ));

    $gabe = app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, dosis: 1, notiz: 'Schmerzen');
    expect($gabe->status)->toBe(AdministrationStatus::Gegeben);
});

it('lehnt eine Bedarfsgabe über dem Tageslimit ab', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, bei_bedarf: true,
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::BeiBedarf->value, dosis: ['bei_bedarf' => 1], max_anzahl_taeglich: 1,
    ));
    app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, 1, 'erste');

    expect(fn () => app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, 1, 'zweite'))
        ->toThrow(\DomainException::class);
});

it('setzt eine Verordnung ab und storniert künftige geplante Gaben', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id, gueltig_von: today()->toDateString(),
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1]));
    app(GenerateAdministrations::class)->handle($schedule, today()->toDateString(), today()->addDays(5)->toDateString());

    app(DiscontinuePrescription::class)->handle($rx, $this->nurse->id, ab: today()->addDay()->toDateString());

    expect(MedicationAdministration::where('status', AdministrationStatus::Ausgelassen->value)->count())->toBeGreaterThan(0)
        ->and($rx->fresh()->abgesetzt_am)->not->toBeNull();
});
```

- [ ] **Step 2: AdministerOnDemand**

`app/Domains/Medication/Actions/AdministerOnDemand.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\{AdministrationStatus, AdministrationTimeslot};
use App\Domains\Medication\Models\{MedicationAdministration, PrescriptionSchedule};
use DomainException;
use Illuminate\Support\Facades\DB;

class AdministerOnDemand
{
    public function __construct(private AdministerMedication $administer) {}

    public function handle(PrescriptionSchedule $schedule, int $userId, float $dosis, ?string $notiz = null): MedicationAdministration
    {
        return DB::transaction(function () use ($schedule, $userId, $dosis, $notiz) {
            $rx = $schedule->prescription;

            if ($schedule->max_anzahl_taeglich !== null) {
                $heute = MedicationAdministration::where('prescription_schedule_id', $schedule->id)
                    ->where('status', AdministrationStatus::Gegeben->value)
                    ->whereDate('ist_zeitpunkt', today())
                    ->count();
                if ($heute + 1 > (float) $schedule->max_anzahl_taeglich) {
                    throw new DomainException('Tageshöchstmenge der Bedarfsmedikation überschritten.');
                }
            }

            $gabe = MedicationAdministration::create([
                'resident_id' => $rx->resident_id,
                'prescription_schedule_id' => $schedule->id,
                'soll_zeitpunkt' => now(),
                'tageszeit' => AdministrationTimeslot::BeiBedarf,
                'dosis' => $dosis,
                'status' => AdministrationStatus::Geplant,
            ]);

            return $this->administer->handle($gabe, new AdministerData(
                quittiert_von: $userId, med_product_id: $rx->med_product_id, dosis: $dosis, notiz: $notiz,
            ));
        });
    }
}
```

- [ ] **Step 3: DiscontinuePrescription**

`app/Domains/Medication/Actions/DiscontinuePrescription.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\{MedicationAdministration, Prescription};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DiscontinuePrescription
{
    public function handle(Prescription $rx, int $userId, ?string $ab = null): Prescription
    {
        return DB::transaction(function () use ($rx, $userId, $ab) {
            $stichtag = Carbon::parse($ab ?? now()->toDateString());

            $rx->update(['abgesetzt_am' => $stichtag->toDateString(), 'abgesetzt_von' => $userId]);

            // Künftige, noch geplante Gaben ab Stichtag stornieren (als ausgelassen markieren).
            MedicationAdministration::whereIn('prescription_schedule_id', $rx->schedules()->pluck('id'))
                ->where('status', AdministrationStatus::Geplant->value)
                ->where('soll_zeitpunkt', '>=', $stichtag->startOfDay())
                ->update(['status' => AdministrationStatus::Ausgelassen, 'notiz' => 'Verordnung abgesetzt']);

            return $rx;
        });
    }
}
```

- [ ] **Step 4: Tests grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Medication/OnDemandAndDiscontinueTest.php
git add -A && git commit -m "feat(medication): on-demand dosing (daily limit) + discontinue"
```

---

## Task 10: Vitalwerte (VitalReading + RecordVital)

**Files:**
- Create: Migration `vital_readings`, Model `VitalReading`, DTO `VitalData`, Action `RecordVital`
- Test: `tests/Feature/Medication/VitalTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/VitalTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\RecordVital;
use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('erfasst einen Vitalwert mit Einheit', function () {
    $resident = Resident::factory()->create();
    $v = app(RecordVital::class)->handle(new VitalData(
        resident_id: $resident->id, typ: VitalType::Schmerz->value, wert: 6, gemessen_von: $this->nurse->id,
    ));
    expect($v->typ)->toBe(VitalType::Schmerz)->and($v->einheit)->toBe('NRS 0–10')->and((float) $v->wert)->toBe(6.0);
});
```

- [ ] **Step 2: Migration**

`2026_06_04_000430_create_vital_readings_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vital_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->string('typ');                  // VitalType
            $table->decimal('wert', 8, 2);
            $table->decimal('wert2', 8, 2)->nullable(); // z. B. diastolisch bei Blutdruck
            $table->string('einheit');
            $table->timestamp('gemessen_am');
            $table->unsignedBigInteger('gemessen_von')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'typ', 'gemessen_am']);
        });
    }
    public function down(): void { Schema::dropIfExists('vital_readings'); }
};
```

- [ ] **Step 3: Model + DTO + Action**

`VitalReading.php`: `$fillable=['tenant_id','resident_id','administration_id','typ','wert','wert2','einheit','gemessen_am','gemessen_von','notiz']`; casts `typ`=>VitalType, `wert`/`wert2`=>decimal:2, `gemessen_am`=>datetime; `resident(): BelongsTo`.
`VitalData.php`:
```php
<?php
namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class VitalData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $typ,
        public float $wert,
        public int $gemessen_von,
        public ?float $wert2 = null,
        public ?string $notiz = null,
        public ?int $administration_id = null,
    ) {}
}
```
`RecordVital.php`:
```php
<?php
namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;

class RecordVital
{
    public function handle(VitalData $data): VitalReading
    {
        return VitalReading::create([
            ...$data->toArray(),
            'einheit' => VitalType::from($data->typ)->einheit(),
            'gemessen_am' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Test grün + Commit**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Medication/VitalTest.php
git add -A && git commit -m "feat(medication): vital readings + record action"
```

---

## Task 11: Stellplan-UI (Livewire) — Eintrittspunkt am Bett

**Files:**
- Create: `app/Livewire/Medication/Stellplan.php`, `resources/views/livewire/medication/stellplan.blade.php`
- Modify: `routes/web.php`, `layouts.app` (Nav „Medikation"), `app/Livewire/ResidentShow.php` (Link/Tab)
- Test: `tests/Feature/Medication/StellplanPageTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/StellplanPageTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AddSchedule, CreatePrescription, GenerateAdministrations};
use App\Domains\Medication\Data\{PrescriptionData, ScheduleData};
use App\Domains\Medication\Enums\{AdministrationStatus, ScheduleFrequency};
use App\Domains\Medication\Models\{MedProduct, MedicationAdministration};
use App\Livewire\Medication\Stellplan;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
    $this->nurse->assignRole('pflegefachkraft');
});

it('zeigt offene Gaben und quittiert eine über die UI', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: $this->nurse->id, med_product_id: MedProduct::factory()->create()->id, gueltig_von: today()->toDateString(),
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1]));
    app(GenerateAdministrations::class)->handle($schedule, today()->toDateString(), today()->toDateString());
    $gabe = MedicationAdministration::first();

    Livewire::actingAs($this->nurse)->test(Stellplan::class, ['resident' => $resident])
        ->call('quittieren', $gabe->id)
        ->assertHasNoErrors();

    expect($gabe->fresh()->status)->toBe(AdministrationStatus::Gegeben);
});
```

- [ ] **Step 2: Komponente**

`app/Livewire/Medication/Stellplan.php`:
```php
<?php
namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\{AdministerMedication, RefuseMedication};
use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\MedicationAdministration;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Stellplan extends Component
{
    public Resident $resident;
    public string $tag;

    public function mount(Resident $resident): void
    {
        $this->resident = $resident;
        $this->tag = today()->toDateString();
    }

    public function quittieren(int $id, AdministerMedication $administer): void
    {
        $a = MedicationAdministration::where('resident_id', $this->resident->id)->findOrFail($id);
        $this->authorize('administer', $a);
        $productId = $a->schedule?->prescription?->med_product_id;
        $administer->handle($a, new AdministerData(quittiert_von: auth()->id(), med_product_id: $productId));
        session()->flash('status', 'Gabe quittiert.');
    }

    public function ablehnen(int $id, RefuseMedication $refuse): void
    {
        $a = MedicationAdministration::where('resident_id', $this->resident->id)->findOrFail($id);
        $this->authorize('administer', $a);
        $refuse->handle($a, auth()->id(), 'Abgelehnt am Bett');
        session()->flash('status', 'Als abgelehnt vermerkt.');
    }

    public function render()
    {
        $gaben = MedicationAdministration::where('resident_id', $this->resident->id)
            ->whereDate('soll_zeitpunkt', $this->tag)
            ->with('schedule.prescription.medProduct')
            ->orderBy('soll_zeitpunkt')->get();

        return view('livewire.medication.stellplan', [
            'gaben' => $gaben,
            'offen' => AdministrationStatus::Geplant,
        ]);
    }
}
```

- [ ] **Step 3: View** — Tabelle der Gaben des Tages (Uhrzeit, Medikament/`bhp_text`, Dosis, Status-Badge, Buttons „Geben"/„Ablehnen" bei `status===geplant`). Muster wie `livewire/speech.blade.php` (`.card`, `.chip`, `.btn`, `.badge`). BtM-Gaben mit rotem `.badge` „BtM" markieren (`$g->schedule?->prescription?->medProduct?->btm`).

- [ ] **Step 4: Route + Nav + ResidentShow-Link**

`routes/web.php`: `Route::get('/bewohner/{resident}/medikation', \App\Livewire\Medication\Stellplan::class)->name('medikation.stellplan');`
In `ResidentShow`-View einen Button „💊 Medikation/Stellplan" → `route('medikation.stellplan', $resident)`. Optional Nav-Eintrag „Medikation" (führt auf eine Bewohner-Auswahl oder den ersten Bewohner).

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Medication/StellplanPageTest.php
git add -A && git commit -m "feat(medication): bedside stellplan ui + bewohner-link"
```

---

## Task 12: Stellplan-Materialisierungs-Job + Demo-Seed + Gesamtsuite

**Files:**
- Create: `app/Domains/Medication/Jobs/MaterializeSchedulesJob.php` (+ Schedule in `routes/console.php`)
- Modify: `DemoSeeder` (eine Verordnung + Stellplan + Gaben + Bestand für Maria Schneider)
- Test: keine neue — Verifikation

- [ ] **Step 1: Materialisierungs-Job**

`app/Domains/Medication/Jobs/MaterializeSchedulesJob.php`: iteriert alle aktiven `PrescriptionSchedule` (deren Verordnung `aktiv`) und ruft `GenerateAdministrations` für die nächsten 7 Tage. Tenant-Kontext je Schedule via `app(CurrentTenant)->set($schedule->tenant)` (wie die Speech-Jobs in Plan 3). In `routes/console.php`:
```php
Schedule::job(new \App\Domains\Medication\Jobs\MaterializeSchedulesJob())->dailyAt('00:30');
```

- [ ] **Step 2: DemoSeeder erweitern**

In `DemoSeeder::run()` nach den Bewohnern: eine `TradeForm` (Tablette), `MedProduct` (Ramipril 5 mg), für Maria Schneider eine `Prescription` (gueltig_von heute) + `PrescriptionSchedule` (täglich morgens 1) + `AddStock` (100 Stück) + `GenerateAdministrations` (heute..+3 Tage). WHY: Medikation sofort sichtbar/testbar.

- [ ] **Step 3: Frisch migrieren/seeden + Gesamtsuite**

Run:
```bash
php artisan migrate:fresh --seed
./vendor/bin/pest
```
Expected: ALLE PASS (Plan 1–5).

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(medication): daily schedule materialization job + demo seed"
```

---

## Self-Review-Ergebnis (Plan 5)

- **Spec-Abdeckung:** Verordnung→Plan→Durchführung→Quittierung (OPDE Prescription/Schedule/BHP) → Tasks 3,4,5,6,8. Bedarfsmedikation + Tageslimit → Task 9. Absetzen/Storno (OPDE docOFF) → Task 9. Bestand/Charge/Transaktion + FEFO-Abbuchung (OPDE MedStock/MedStockTransaction/UPR) → Tasks 7,8. BtM-Kennzeichnung → `med_products.btm` + UI-Badge (Tasks 2,11). Vitalwerte (OPDE ResValue, Outcome) → Task 10 (+ `administration_id`-Link). UI-Eintrittspunkt → Task 11. Automatik → Task 12.
- **Append-only/Audit:** Gaben werden nie gelöscht — Korrektur über Status (gegeben/abgelehnt/ausgelassen) + Notiz; activitylog via BaseModel; keine `delete`-Policy.
- **Platzhalter:** keine — Kernlogik (Generierung, FEFO-Abbuchung, Bedarfslimit, Absetzen) vollständig als Code; nur die zwei Listen-Views (Stellplan/Verordnungen) verweisen auf das exakte bestehende Livewire-/CSS-Muster.
- **Typ-Konsistenz:** Enums (`AdministrationTimeslot/Status`, `ScheduleFrequency`, `VitalType`, `StockStatus/TransactionType`), `PrescriptionData/ScheduleData/AdministerData/StockData/VitalData`, Action-`handle`-Signaturen, Idempotenz-Key (`prescription_schedule_id`,`soll_zeitpunkt`,`tageszeit`) durchgängig identisch.

## Folge-Pläne
- **Plan 6:** Controlling / QMS — `docs/superpowers/plans/2026-06-04-opcare-controlling-qms.md`
- **Plan 7:** QDVS-Export — `…-opcare-qdvs-export.md`
