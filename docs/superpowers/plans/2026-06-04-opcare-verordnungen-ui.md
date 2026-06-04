# OPCare — Plan 9: Verordnungs-Erfassungs-UI + Medikationsstamm-Management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Die fehlende **Bedien-Oberfläche** für das bereits fertige Medikations-Backend (Plan 5): Medikationsstamm pflegen (`MedProduct`-CRUD), eine ärztliche **Verordnung anlegen** (Produkt/BHP + Turnus/Dosis je Tageszeit + Bestand) mit sofortiger Materialisierung der geplanten Gaben, eine **Verordnungsliste** je Bewohner mit **Absetzen**, **Bedarfsmedikation** am Bett dosieren und **Vitalwerte** erfassen.

**Architecture:** **Kein neues Domänen-Backend** — alle Aktionen existieren bereits (`CreatePrescription`, `AddSchedule`, `GenerateAdministrations`, `AddStock`, `AdministerOnDemand`, `DiscontinuePrescription`, `RecordVital`) und werden aus neuen Livewire-Komponenten (`App\Livewire\Medication\*`) aufgerufen. Eine Verordnung wird in **einem** Schritt erfasst (Wizard-frei, ein Formular): `CreatePrescription` → optional `AddSchedule` → optional `AddStock` → `GenerateAdministrations` für ein Vorlauffenster. Tageszeiten kommen über `TimeslotClock` (das seit Plan 8 die Schicht-Konfiguration liest, sonst den config-Default). Textfelder (BHP-Text, Hinweis, Vital-Notiz) nutzen die Querschnitts-Sprachfunktion `<x-voice-field>`.

**Tech Stack:** wie Plan 1–8. Livewire 4, spatie-data-DTOs, Pest 4 + Livewire-Testing. Keine neuen Migrationen, keine neuen Models — nur UI, Routen, Tests.

**Voraussetzung:** Plan 5 (Medikation-Backend vollständig: Prescription/Schedule/Administration/MedProduct/TradeForm/Situation/MedStock/VitalReading + alle genannten Actions + `MedicationReferenceSeeder`). Plan 8 empfohlen (Schicht-Zeiten), aber nicht zwingend — `TimeslotClock` fällt auf den config-Default zurück. Plan 1/4 (Auth, Rollen, Tenancy).

**Referenz:** Plan-5-Dokument (`docs/superpowers/plans/2026-06-04-opcare-medikation-bhp.md`) für die exakten DTO-/Action-Signaturen. Bestehende UI als Stilvorlage: `app/Livewire/Medication/Stellplan.php` (+ View), `app/Livewire/Admin/Users.php` (+ View, Form-/Tabellen-Muster), `resources/views/components/voice-field.blade.php`.

---

## Hinweise für ausführende Subagents

- **Tests laufen auf SQLite in-memory** (`phpunit.xml`: `DB_CONNECTION=sqlite`, `SPEECH_FAKE=true`, `QUEUE_CONNECTION=sync`).
- **Pest gibt JSON aus** (laravel/pao). Ergebnis lesen:
  ```bash
  ./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['tests'],d['passed'],d.get('failed'))"
  ```
- **Vor jedem Commit:** `vendor/bin/pint` (CI `lint.yml` prüft `--test`).
- **CurrentTenant** in jedem Feature-Test setzen (`app(CurrentTenant::class)->set($tenant)`), sonst greift der globale `TenantScope`.
- **Bestehende Action-/DTO-Signaturen NICHT ändern** — sie sind getestet (Plan 5). Diese Felder gelten (verifiziert am Ist-Code):
  - `PrescriptionData(resident_id, created_by, med_product_id?, bhp_text?, physician_id?, situation_id?, bei_bedarf=false, gueltig_von?, gueltig_bis?, hinweis?)`
  - `ScheduleData(frequenz: string, dosis: array, intervall=1, wochentage?, max_anzahl_taeglich?, max_einzeldosis?)` — `frequenz` ist der **String-Wert** eines `ScheduleFrequency`-Case; `dosis` = `['morgens' => 1, 'abends' => 0.5, ...]` (Slot-Value → Menge).
  - `StockData(resident_id, med_product_id, menge, einheit, charge?, verfall_am?)`
  - `VitalData` — Felder am Ist-Code prüfen; `RecordVital` setzt `einheit` und `gemessen_am` selbst, erwartet u. a. `resident_id`, `typ` (String-Value von `VitalType`), `wert`/`wert_text`, `notiz?`, `gemessen_von?`.
  - `AdministerOnDemand::handle(PrescriptionSchedule $schedule, int $userId, float $dosis, ?string $notiz)`
  - `DiscontinuePrescription::handle(Prescription $rx, int $userId, ?string $ab)`
  - `GenerateAdministrations::handle(PrescriptionSchedule $schedule, string $von, string $bis): int`
  > **Pflicht-Schritt vor Task 2:** `app/Domains/Medication/Data/VitalData.php` und `app/Domains/Medication/Models/VitalReading.php` lesen und die Vital-UI exakt an die echten Feldnamen anpassen (im Plan unten als `wert`/`wert_text` angenommen).
- **Rollen** (`RolesSeeder`): `admin`, `pflegefachkraft`, `pflegehilfskraft`, `leserecht` + `super-admin`. **Verordnen/Absetzen/Stamm pflegen** ist fachlich der Pflegefachkraft/Leitung vorbehalten → Guard `admin`/`pflegefachkraft`/`super-admin`. **Quittieren/Bedarf geben/Vitalwerte** dürfen auch Pflegehilfskräfte (am Bett) — orientiere dich an der bestehenden `MedicationAdministrationPolicy` aus Plan 5.

---

## File Structure (Plan 9)

```
app/Livewire/Medication/
├── Stammdaten.php            # MedProduct-CRUD (+ TradeForm-Auswahl)
├── VerordnungAnlegen.php     # Formular: Verordnung + Stellplan + Bestand → Gaben generieren
├── Verordnungen.php          # Liste je Bewohner + Absetzen + Bedarf dosieren
└── Vitalwerte.php            # Vitalwert-Erfassung je Bewohner
resources/views/livewire/medication/
├── stammdaten.blade.php
├── verordnung-anlegen.blade.php
├── verordnungen.blade.php
└── vitalwerte.blade.php
routes/web.php                # + medikation.stammdaten, .verordnung-anlegen, .verordnungen, .vitalwerte
resources/views/layouts/app.blade.php   # Nav + Bewohner-Detail-Verlinkung
tests/Feature/Medication/Ui/...
```

Keine Migrationen, keine neuen Models/Actions/Enums.

---

## Task 1: Medikationsstamm-UI (`MedProduct`-CRUD)

**Files:**
- Create: `app/Livewire/Medication/Stammdaten.php`, `resources/views/livewire/medication/stammdaten.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Medication/Ui/StammdatenTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/Ui/StammdatenTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use App\Livewire\Medication\Stammdaten;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['pflegefachkraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $this->form = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stk', 'teilbar' => true]);
});

it('verweigert reines Leserecht das Anlegen eines Produkts', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('leserecht');
    $this->actingAs($u);

    Livewire::test(Stammdaten::class)->assertForbidden();
});

it('legt ein Medikationsprodukt an', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('pflegefachkraft');
    $this->actingAs($u);

    Livewire::test(Stammdaten::class)
        ->set('name', 'Ibuprofen 400')
        ->set('wirkstoff', 'Ibuprofen')
        ->set('staerke', '400 mg')
        ->set('tradeFormId', $this->form->id)
        ->set('btm', false)
        ->call('speichern')
        ->assertHasNoErrors();

    expect(MedProduct::where('name', 'Ibuprofen 400')->where('wirkstoff', 'Ibuprofen')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/StammdatenTest.php`
Expected: FAIL.

- [ ] **Step 3: `Stammdaten` Livewire**

`app/Livewire/Medication/Stammdaten.php`:
```php
<?php

namespace App\Livewire\Medication;

use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Stammdaten extends Component
{
    public string $name = '';

    public string $wirkstoff = '';

    public string $staerke = '';

    public ?int $tradeFormId = null;

    public ?string $atcCode = null;

    public ?string $pzn = null;

    public bool $btm = false;

    public function mount(): void
    {
        // WHY: Stamm-Pflege ist Fachkraft/Leitung — Guard in mount UND Action (Nav-Verstecken genügt nicht).
        abort_unless($this->darfPflegen(), 403);
    }

    private function darfPflegen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function speichern(): void
    {
        abort_unless($this->darfPflegen(), 403);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'wirkstoff' => ['nullable', 'string', 'max:255'],
            'staerke' => ['nullable', 'string', 'max:120'],
            'tradeFormId' => ['nullable', 'exists:trade_forms,id'],
            'atcCode' => ['nullable', 'string', 'max:16'],
            'pzn' => ['nullable', 'string', 'max:16'],
            'btm' => ['boolean'],
        ]);

        MedProduct::create([
            'name' => $data['name'],
            'wirkstoff' => $data['wirkstoff'] ?: null,
            'staerke' => $data['staerke'] ?: null,
            'trade_form_id' => $data['tradeFormId'] ?? null,
            'atc_code' => $data['atcCode'] ?? null,
            'pzn' => $data['pzn'] ?? null,
            'btm' => $data['btm'],
        ]);

        $this->reset('name', 'wirkstoff', 'staerke', 'atcCode', 'pzn', 'btm');
        session()->flash('status', 'Produkt angelegt.');
    }

    public function render()
    {
        return view('livewire.medication.stammdaten', [
            'produkte' => MedProduct::with('tradeForm')->orderBy('name')->get(),
            'tradeForms' => TradeForm::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/medication/stammdaten.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Medikationsstamm</h1>
        <p class="lead">Präparate für Verordnungen pflegen.</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neues Produkt</h3></div>
        <form wire:submit="speichern">
            <div class="form-row">
                <div class="field"><label>Handelsname</label><input wire:model="name" />@error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Wirkstoff</label><input wire:model="wirkstoff" /></div>
                <div class="field"><label>Stärke</label><input wire:model="staerke" placeholder="z. B. 400 mg" /></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Darreichungsform</label>
                    <select wire:model="tradeFormId">
                        <option value="">– wählen –</option>
                        @foreach ($tradeForms as $tf)<option value="{{ $tf->id }}">{{ $tf->name }} ({{ $tf->einheit }})</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>ATC</label><input wire:model="atcCode" /></div>
                <div class="field"><label>PZN</label><input wire:model="pzn" /></div>
                <div class="field check"><label><input type="checkbox" wire:model="btm" /> Betäubungsmittel (BtM)</label></div>
            </div>
            <button class="btn btn-primary">Anlegen</button>
        </form>
    </div>

    <div class="card">
        <table class="data"><thead><tr><th>Name</th><th>Wirkstoff</th><th>Stärke</th><th>Form</th><th>BtM</th></tr></thead>
            <tbody>
                @forelse ($produkte as $p)
                    <tr>
                        <td><b>{{ $p->name }}</b></td>
                        <td>{{ $p->wirkstoff }}</td>
                        <td>{{ $p->staerke }}</td>
                        <td>{{ $p->tradeForm?->name }}</td>
                        <td>@if ($p->btm)<span class="badge badge-warn">BtM</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Produkte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Route + Nav**

In `routes/web.php` (`['auth','tenant']`-Gruppe, Import `use App\Livewire\Medication\Stammdaten;`):
```php
Route::get('/medikation/stamm', Stammdaten::class)->name('medikation.stammdaten');
```
Nav-Link in `layouts/app.blade.php` (Fachkraft/Leitung — analog vorhandener rollen-gebundener Links):
```blade
@if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
    <a href="{{ route('medikation.stammdaten') }}" @class(['active' => request()->routeIs('medikation.stammdaten')])>Medikationsstamm</a>
@endif
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/StammdatenTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Medication/Stammdaten.php resources/views/livewire/medication/stammdaten.blade.php routes/web.php resources/views/layouts/app.blade.php tests/Feature/Medication/Ui/StammdatenTest.php
git commit -m "feat(medication-ui): Medikationsstamm-CRUD (Fachkraft-Guard)"
```

---

## Task 2: Verordnung-anlegen-UI (Verordnung + Stellplan + Bestand → Gaben)

**Files:**
- Create: `app/Livewire/Medication/VerordnungAnlegen.php`, `resources/views/livewire/medication/verordnung-anlegen.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Medication/Ui/VerordnungAnlegenTest.php`

**Vorab-Schritt (Pflicht):** `app/Domains/Medication/Models/PrescriptionSchedule.php` + `ScheduleData` lesen, um die exakten `dosis`/`frequenz`-Formate zu bestätigen (siehe Subagent-Hinweise).

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/Ui/VerordnungAnlegenTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\TradeForm;
use App\Domains\Medication\Models\MedProduct;
use App\Livewire\Medication\VerordnungAnlegen;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);

    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $form = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stk', 'teilbar' => true]);
    $this->product = MedProduct::create(['name' => 'Ramipril 5', 'trade_form_id' => $form->id]);
});

afterEach(fn () => Carbon::setTestNow());

it('legt Verordnung + täglichen Stellplan an und generiert Gaben für das Vorlauffenster', function () {
    Livewire::test(VerordnungAnlegen::class, ['resident' => $this->resident])
        ->set('medProductId', $this->product->id)
        ->set('frequenz', 'taeglich')
        ->set('dosis.morgens', 1)
        ->set('dosis.abends', 0)
        ->set('vorlaufTage', 3)
        ->call('speichern')
        ->assertHasNoErrors();

    $rx = Prescription::where('resident_id', $this->resident->id)->first();
    expect($rx)->not->toBeNull()
        ->and($rx->schedules()->count())->toBe(1);

    // 3 Tage × 1 Gabe morgens
    expect(MedicationAdministration::where('resident_id', $this->resident->id)->count())->toBe(3);
});

it('validiert: ohne Produkt UND ohne BHP-Text keine Verordnung', function () {
    Livewire::test(VerordnungAnlegen::class, ['resident' => $this->resident])
        ->set('medProductId', null)
        ->set('bhpText', '')
        ->call('speichern')
        ->assertHasErrors('medProductId');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VerordnungAnlegenTest.php`
Expected: FAIL.

- [ ] **Step 3: `VerordnungAnlegen` Livewire**

`app/Livewire/Medication/VerordnungAnlegen.php`:
```php
<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Situation;
use App\Domains\Masterdata\Models\Physician;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class VerordnungAnlegen extends Component
{
    #[Locked]
    public Resident $resident;

    public ?int $medProductId = null;

    public string $bhpText = '';

    public ?int $physicianId = null;

    public ?int $situationId = null;

    public bool $beiBedarf = false;

    public string $frequenz = 'taeglich';

    public array $wochentage = [];

    /** Slot-Value => Menge (z. B. ['morgens' => 1, 'abends' => 0.5]) */
    public array $dosis = [];

    public ?float $maxAnzahlTaeglich = null;

    public string $gueltigVon = '';

    public ?string $gueltigBis = null;

    public string $hinweis = '';

    // Optionaler Erstbestand
    public ?float $bestandMenge = null;

    public ?string $bestandCharge = null;

    public ?string $bestandVerfall = null;

    public int $vorlaufTage = 14;

    public function mount(Resident $resident): void
    {
        abort_unless($this->darfVerordnen(), 403);
        $this->resident = $resident;
        $this->gueltigVon = today()->toDateString();
        $this->dosis = array_fill_keys(array_map(fn ($s) => $s->value, AdministrationTimeslot::scheduled()), 0);
    }

    private function darfVerordnen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function speichern(
        CreatePrescription $create,
        AddSchedule $addSchedule,
        AddStock $addStock,
        GenerateAdministrations $generate,
    ): void {
        abort_unless($this->darfVerordnen(), 403);

        $rules = [
            'medProductId' => ['nullable', 'exists:med_products,id'],
            'bhpText' => ['nullable', 'string'],
            'physicianId' => ['nullable', 'exists:physicians,id'],
            'situationId' => ['nullable', 'exists:situations,id'],
            'beiBedarf' => ['boolean'],
            'frequenz' => ['required', 'in:'.implode(',', array_column(ScheduleFrequency::cases(), 'value'))],
            'wochentage' => ['array'],
            'dosis' => ['array'],
            'maxAnzahlTaeglich' => ['nullable', 'numeric', 'min:0'],
            'gueltigVon' => ['required', 'date'],
            'gueltigBis' => ['nullable', 'date', 'after_or_equal:gueltigVon'],
            'hinweis' => ['nullable', 'string'],
            'bestandMenge' => ['nullable', 'numeric', 'min:0'],
            'bestandCharge' => ['nullable', 'string', 'max:120'],
            'bestandVerfall' => ['nullable', 'date'],
            'vorlaufTage' => ['required', 'integer', 'min:0', 'max:60'],
        ];
        $this->validate($rules);

        // WHY(fachlich): eine Verordnung braucht entweder ein Präparat ODER einen BHP-Freitext.
        if (! $this->medProductId && trim($this->bhpText) === '') {
            $this->addError('medProductId', 'Bitte ein Präparat wählen oder eine BHP-Anweisung eingeben.');

            return;
        }

        DB::transaction(function () use ($create, $addSchedule, $addStock, $generate) {
            $rx = $create->handle(new PrescriptionData(
                resident_id: $this->resident->id,
                created_by: auth()->id(),
                med_product_id: $this->medProductId,
                bhp_text: trim($this->bhpText) ?: null,
                physician_id: $this->physicianId,
                situation_id: $this->situationId,
                bei_bedarf: $this->beiBedarf,
                gueltig_von: $this->gueltigVon,
                gueltig_bis: $this->gueltigBis,
                hinweis: trim($this->hinweis) ?: null,
            ));

            // nur Slots mit Menge > 0 übernehmen
            $dosis = array_filter($this->dosis, fn ($m) => (float) $m > 0);

            $schedule = $addSchedule->handle($rx, new ScheduleData(
                frequenz: $this->frequenz,
                dosis: $dosis,
                wochentage: $this->frequenz === ScheduleFrequency::Woechentlich->value ? array_map('intval', $this->wochentage) : null,
                max_anzahl_taeglich: $this->maxAnzahlTaeglich,
            ));

            if ($this->bestandMenge && $this->medProductId) {
                $einheit = MedProduct::find($this->medProductId)?->tradeForm?->einheit ?? 'Stk';
                $addStock->handle(new StockData(
                    resident_id: $this->resident->id,
                    med_product_id: $this->medProductId,
                    menge: (float) $this->bestandMenge,
                    einheit: $einheit,
                    charge: $this->bestandCharge,
                    verfall_am: $this->bestandVerfall,
                ));
            }

            if (! $this->beiBedarf && $this->frequenz !== ScheduleFrequency::BeiBedarf->value) {
                $generate->handle(
                    $schedule,
                    $this->gueltigVon,
                    now()->addDays($this->vorlaufTage)->toDateString(),
                );
            }
        });

        session()->flash('status', 'Verordnung angelegt.');
        $this->redirectRoute('medikation.verordnungen', ['resident' => $this->resident->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.medication.verordnung-anlegen', [
            'produkte' => MedProduct::orderBy('name')->get(),
            'aerzte' => Physician::orderBy('name')->get(),
            'situationen' => Situation::orderBy('name')->get(),
            'slots' => AdministrationTimeslot::scheduled(),
            'frequenzen' => ScheduleFrequency::cases(),
        ]);
    }
}
```

- [ ] **Step 4: View** (mit `<x-voice-field>` für BHP-Text + Hinweis)

`resources/views/livewire/medication/verordnung-anlegen.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Verordnung anlegen</h1>
        <p class="lead">für {{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form wire:submit="speichern">
        <div class="card">
            <div class="card-head"><h3>Präparat / Anweisung</h3></div>
            <div class="form-row">
                <div class="field"><label>Präparat</label>
                    <select wire:model="medProductId">
                        <option value="">– kein Präparat (BHP-Freitext) –</option>
                        @foreach ($produkte as $p)<option value="{{ $p->id }}">{{ $p->name }} {{ $p->staerke }}</option>@endforeach
                    </select>@error('medProductId')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Arzt</label>
                    <select wire:model="physicianId"><option value="">–</option>
                        @foreach ($aerzte as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <x-voice-field model="bhpText" label="BHP-Anweisung / Freitext" :rows="2"
                context="Behandlungspflege-Anweisung für die Pflegekraft, präzise und knapp." />
        </div>

        <div class="card">
            <div class="card-head"><h3>Stellplan</h3></div>
            <div class="form-row">
                <div class="field"><label>Turnus</label>
                    <select wire:model.live="frequenz">
                        @foreach ($frequenzen as $f)<option value="{{ $f->value }}">{{ ucfirst($f->value) }}</option>@endforeach
                    </select>
                </div>
                @if ($frequenz === 'woechentlich')
                    <div class="field"><label>Wochentage (ISO 1–7)</label>
                        <div class="weekdays">
                            @foreach (['1'=>'Mo','2'=>'Di','3'=>'Mi','4'=>'Do','5'=>'Fr','6'=>'Sa','7'=>'So'] as $iso => $lbl)
                                <label><input type="checkbox" wire:model="wochentage" value="{{ $iso }}" /> {{ $lbl }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="form-row">
                @foreach ($slots as $slot)
                    <div class="field field-narrow">
                        <label>{{ $slot->label() }}</label>
                        <input type="number" step="0.25" min="0" wire:model="dosis.{{ $slot->value }}" />
                    </div>
                @endforeach
            </div>
            <div class="form-row">
                <div class="field check"><label><input type="checkbox" wire:model.live="beiBedarf" /> Bedarfsmedikation</label></div>
                <div class="field"><label>Max. Gaben/Tag (Bedarf)</label><input type="number" step="0.5" min="0" wire:model="maxAnzahlTaeglich" /></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Gültigkeit & Erstbestand</h3></div>
            <div class="form-row">
                <div class="field"><label>Gültig ab</label><input type="date" wire:model="gueltigVon" />@error('gueltigVon')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Gültig bis</label><input type="date" wire:model="gueltigBis" />@error('gueltigBis')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Vorlauf Gaben (Tage)</label><input type="number" min="0" max="60" wire:model="vorlaufTage" /></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Bestand Menge</label><input type="number" step="1" min="0" wire:model="bestandMenge" /></div>
                <div class="field"><label>Charge</label><input wire:model="bestandCharge" /></div>
                <div class="field"><label>Verfall</label><input type="date" wire:model="bestandVerfall" /></div>
            </div>
            <x-voice-field model="hinweis" label="Hinweis" :rows="2" />
        </div>

        <button class="btn btn-primary">Verordnung speichern</button>
    </form>
</div>
```

- [ ] **Step 5: Route**

In `routes/web.php` (`['auth','tenant']`, Import `use App\Livewire\Medication\VerordnungAnlegen;`):
```php
Route::get('/bewohner/{resident}/verordnung/neu', VerordnungAnlegen::class)->name('medikation.verordnung-anlegen');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VerordnungAnlegenTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Medication/VerordnungAnlegen.php resources/views/livewire/medication/verordnung-anlegen.blade.php routes/web.php tests/Feature/Medication/Ui/VerordnungAnlegenTest.php
git commit -m "feat(medication-ui): Verordnung anlegen (Stellplan + Bestand + Gaben-Generierung, Voice-Felder)"
```

---

## Task 3: Verordnungsliste je Bewohner + Absetzen + Bedarf dosieren

**Files:**
- Create: `app/Livewire/Medication/Verordnungen.php`, `resources/views/livewire/medication/verordnungen.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Medication/Ui/VerordnungenTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Medication/Ui/VerordnungenTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\Prescription;
use App\Livewire\Medication\Verordnungen;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);

    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->rx = (new CreatePrescription)->handle(new PrescriptionData(
        resident_id: $this->resident->id, created_by: $this->user->id, bhp_text: 'Kompression Bein li.',
    ));
    (new AddSchedule)->handle($this->rx, new ScheduleData(frequenz: 'taeglich', dosis: ['morgens' => 1]));
});

afterEach(fn () => Carbon::setTestNow());

it('listet die aktiven Verordnungen des Bewohners', function () {
    Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->assertSee('Kompression Bein li.');
});

it('setzt eine Verordnung ab', function () {
    Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->call('absetzen', $this->rx->id)
        ->assertHasNoErrors();

    expect($this->rx->fresh()->abgesetzt_am)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VerordnungenTest.php`
Expected: FAIL.

- [ ] **Step 3: `Verordnungen` Livewire**

`app/Livewire/Medication/Verordnungen.php`:
```php
<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AdministerOnDemand;
use App\Domains\Medication\Actions\DiscontinuePrescription;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\PrescriptionSchedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Verordnungen extends Component
{
    #[Locked]
    public Resident $resident;

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    private function darfVerordnen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function absetzen(int $id, DiscontinuePrescription $discontinue): void
    {
        abort_unless($this->darfVerordnen(), 403);
        $rx = Prescription::where('resident_id', $this->resident->id)->findOrFail($id);
        $discontinue->handle($rx, auth()->id());
        session()->flash('status', 'Verordnung abgesetzt.');
    }

    public function bedarfGeben(int $scheduleId, float $dosis, AdministerOnDemand $onDemand): void
    {
        // Bedarf darf auch Pflegehilfskraft am Bett geben (Plan-5-Policy folgen).
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']) || auth()->user()?->isSuperAdmin(), 403);
        $schedule = PrescriptionSchedule::whereHas('prescription', fn ($q) => $q->where('resident_id', $this->resident->id))
            ->findOrFail($scheduleId);
        $onDemand->handle($schedule, auth()->id(), $dosis, 'Bedarfsgabe');
        session()->flash('status', 'Bedarfsgabe dokumentiert.');
    }

    public function render()
    {
        $verordnungen = Prescription::with(['medProduct', 'schedules', 'physician', 'situation'])
            ->where('resident_id', $this->resident->id)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.medication.verordnungen', [
            'aktive' => $verordnungen->filter(fn ($r) => $r->ist_aktiv),
            'beendete' => $verordnungen->filter(fn ($r) => ! $r->ist_aktiv),
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/medication/verordnungen.blade.php`:
```blade
<div>
    <div class="page-head">
        <div><p class="kicker">Medikation</p><h1>Verordnungen</h1><p class="lead">{{ $resident->name }}</p></div>
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
            <a class="btn btn-primary" href="{{ route('medikation.verordnung-anlegen', $resident) }}" wire:navigate>Neue Verordnung</a>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Aktiv</h3></div>
        <table class="data"><thead><tr><th>Präparat / BHP</th><th>Turnus</th><th>Arzt</th><th></th></tr></thead>
            <tbody>
                @forelse ($aktive as $rx)
                    <tr>
                        <td><b>{{ $rx->medProduct?->name ?? $rx->bhp_text }}</b>@if ($rx->medProduct?->btm)<span class="badge badge-warn">BtM</span>@endif</td>
                        <td>
                            @foreach ($rx->schedules as $s)
                                <div class="muted">{{ ucfirst($s->frequenz instanceof \BackedEnum ? $s->frequenz->value : $s->frequenz) }}
                                    @if ($rx->bei_bedarf)
                                        — <button class="btn btn-link" wire:click="bedarfGeben({{ $s->id }}, 1)">Bedarf geben</button>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td>{{ $rx->physician?->name }}</td>
                        <td>
                            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
                                <button class="btn btn-link" wire:click="absetzen({{ $rx->id }})"
                                    wire:confirm="Verordnung wirklich absetzen?">Absetzen</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Keine aktiven Verordnungen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($beendete->isNotEmpty())
        <div class="card">
            <div class="card-head"><h3>Beendet / abgesetzt</h3></div>
            <table class="data"><tbody>
                @foreach ($beendete as $rx)
                    <tr><td>{{ $rx->medProduct?->name ?? $rx->bhp_text }}</td>
                        <td class="muted">abgesetzt am {{ optional($rx->abgesetzt_am)->format('d.m.Y') }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
    @endif
</div>
```

- [ ] **Step 5: Route + Bewohner-Detail-Verlinkung**

In `routes/web.php` (Import `use App\Livewire\Medication\Verordnungen;`):
```php
Route::get('/bewohner/{resident}/verordnungen', Verordnungen::class)->name('medikation.verordnungen');
```
Im Bewohner-Detail (`resources/views/livewire/resident-show.blade.php`) bei den vorhandenen Aktionslinks (dort wo `medikation.stellplan` verlinkt ist) ergänzen:
```blade
<a class="btn" href="{{ route('medikation.verordnungen', $resident) }}" wire:navigate>Verordnungen</a>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VerordnungenTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Medication/Verordnungen.php resources/views/livewire/medication/verordnungen.blade.php routes/web.php resources/views/livewire/resident-show.blade.php tests/Feature/Medication/Ui/VerordnungenTest.php
git commit -m "feat(medication-ui): Verordnungsliste + Absetzen + Bedarf-Gabe"
```

---

## Task 4: Vitalwert-Erfassung je Bewohner

**Files:**
- Create: `app/Livewire/Medication/Vitalwerte.php`, `resources/views/livewire/medication/vitalwerte.blade.php`
- Modify: `routes/web.php`, `resources/views/livewire/resident-show.blade.php`
- Test: `tests/Feature/Medication/Ui/VitalwerteTest.php`

**Vorab-Schritt (Pflicht):** `app/Domains/Medication/Data/VitalData.php`, `app/Domains/Medication/Models/VitalReading.php`, `app/Domains/Medication/Actions/RecordVital.php` lesen. Den unten als `wert`/`wert_text` angenommenen Feldnamen an die echten Felder anpassen (z. B. `wert` numerisch, `wert_text` für Blutdruck „120/80"). Test + View entsprechend ausrichten.

- [ ] **Step 1: Failing test** (Feldnamen ggf. an `VitalData` anpassen)

`tests/Feature/Medication/Ui/VitalwerteTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\VitalReading;
use App\Livewire\Medication\Vitalwerte;
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
});

it('erfasst einen Vitalwert (Puls) am Bett', function () {
    Livewire::test(Vitalwerte::class, ['resident' => $this->resident])
        ->set('typ', 'puls')
        ->set('wert', 72)
        ->call('erfassen')
        ->assertHasNoErrors();

    expect(VitalReading::where('resident_id', $this->resident->id)->where('typ', 'puls')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VitalwerteTest.php`
Expected: FAIL.

- [ ] **Step 3: `Vitalwerte` Livewire** (Felder an `VitalData` anpassen)

`app/Livewire/Medication/Vitalwerte.php`:
```php
<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\RecordVital;
use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Vitalwerte extends Component
{
    #[Locked]
    public Resident $resident;

    public string $typ = 'puls';

    public ?float $wert = null;

    public ?string $wertText = null;  // z. B. Blutdruck "120/80"

    public string $notiz = '';

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    public function erfassen(RecordVital $record): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']) || auth()->user()?->isSuperAdmin(), 403);
        $data = $this->validate([
            'typ' => ['required', 'in:'.implode(',', array_column(VitalType::cases(), 'value'))],
            'wert' => ['nullable', 'numeric'],
            'wertText' => ['nullable', 'string', 'max:60'],
            'notiz' => ['nullable', 'string'],
        ]);

        // HINWEIS: Konstruktor-Argumente an die echte VitalData-Signatur anpassen!
        $record->handle(new VitalData(
            resident_id: $this->resident->id,
            typ: $data['typ'],
            wert: $data['wert'],
            wert_text: $data['wertText'] ?: null,
            notiz: trim($this->notiz) ?: null,
            gemessen_von: auth()->id(),
        ));

        $this->reset('wert', 'wertText', 'notiz');
        session()->flash('status', 'Vitalwert erfasst.');
    }

    public function render()
    {
        return view('livewire.medication.vitalwerte', [
            'typen' => VitalType::cases(),
            'messungen' => VitalReading::where('resident_id', $this->resident->id)
                ->orderByDesc('gemessen_am')->limit(20)->get(),
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/medication/vitalwerte.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Vitalwerte</h1><p class="lead">{{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <form wire:submit="erfassen">
            <div class="form-row">
                <div class="field"><label>Messung</label>
                    <select wire:model.live="typ">
                        @foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }} ({{ $t->einheit() }})</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Wert</label><input type="number" step="0.1" wire:model="wert" />@error('wert')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Wert (Text, z. B. 120/80)</label><input wire:model="wertText" /></div>
            </div>
            <x-voice-field model="notiz" label="Notiz" :rows="1" />
            <button class="btn btn-primary">Erfassen</button>
        </form>
    </div>

    <div class="card">
        <table class="data"><thead><tr><th>Wann</th><th>Messung</th><th>Wert</th></tr></thead>
            <tbody>
                @forelse ($messungen as $m)
                    <tr>
                        <td>{{ optional($m->gemessen_am)->format('d.m.Y H:i') }}</td>
                        <td>{{ $m->typ instanceof \BackedEnum ? $m->typ->label() : $m->typ }}</td>
                        <td><b>{{ $m->wert_text ?? $m->wert }}</b> {{ $m->einheit }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Noch keine Messungen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Route + Verlinkung**

In `routes/web.php` (Import `use App\Livewire\Medication\Vitalwerte;`):
```php
Route::get('/bewohner/{resident}/vitalwerte', Vitalwerte::class)->name('medikation.vitalwerte');
```
Im Bewohner-Detail ergänzen:
```blade
<a class="btn" href="{{ route('medikation.vitalwerte', $resident) }}" wire:navigate>Vitalwerte</a>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Medication/Ui/VitalwerteTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app routes resources
git add app/Livewire/Medication/Vitalwerte.php resources/views/livewire/medication/vitalwerte.blade.php routes/web.php resources/views/livewire/resident-show.blade.php tests/Feature/Medication/Ui/VitalwerteTest.php
git commit -m "feat(medication-ui): Vitalwert-Erfassung am Bett (Voice-Notiz)"
```

---

## Task 5: Gesamt-Suite + Pint + Screenshots + Push

- [ ] **Step 1: Gesamte Suite grün**

Run:
```bash
./vendor/bin/pest 2>&1 | python3 -c "import sys,json;d=json.load(sys.stdin);print('tests',d['tests'],'passed',d['passed'],'failed',d.get('failed'))"
```
Expected: alle Tests grün (`failed` = 0/None).

- [ ] **Step 2: Pint clean**

Run: `vendor/bin/pint --test`
Expected: keine Findings.

- [ ] **Step 3: Optional Screenshots** (wenn Dev-Server + Seed verfügbar)

```bash
php artisan serve --port=8099 &
node scripts/shots.mjs http://localhost:8099
```
`scripts/shots.mjs` um die neuen Pfade erweitern (`/bewohner/1/verordnungen`, `/bewohner/1/verordnung/neu`, `/medikation/stamm`, `/bewohner/1/vitalwerte`), falls visuelle Kontrolle gewünscht.

- [ ] **Step 4: Push**

```bash
git push origin <branch>
```

---

## Self-Review-Ergebnis (Autor)

**Spec coverage:** Medikationsstamm-CRUD (Task 1) ✓; Verordnung anlegen inkl. Stellplan + Erstbestand + Gaben-Generierung (Task 2, ruft `CreatePrescription`/`AddSchedule`/`AddStock`/`GenerateAdministrations`) ✓; Verordnungsliste + Absetzen + Bedarf dosieren (Task 3, `DiscontinuePrescription`/`AdministerOnDemand`) ✓; Vitalwerte (Task 4, `RecordVital`) ✓; Voice-Felder an Freitexten (BHP-Text, Hinweis, Vital-Notiz) ✓; Rollen-Guards in mount UND Action (Lehre aus Plan-4–7-Reviews) ✓.

**Placeholder-Scan:** Keine TODO/TBD im ausführbaren Code. Bewusste, markierte Abhängigkeiten vom Ist-Backend: (a) `VitalData`-Feldnamen (`wert`/`wert_text`/`gemessen_von`) — als **Pflicht-Vorab-Schritt** in Task 4 + Subagent-Hinweisen ausgewiesen, da der genaue Konstruktor erst zur Laufzeit gelesen werden muss; (b) Bewohner-Detail-Link-Position („dort wo `medikation.stellplan` verlinkt ist") — vom Ist-View abhängig.

**Typ-Konsistenz:** Alle Action-/DTO-Aufrufe gegen die im Ist-Code verifizierten Signaturen geschrieben (`PrescriptionData`, `ScheduleData` mit `frequenz`-String + `dosis`-array, `StockData`, `AdministerOnDemand`/`DiscontinuePrescription`/`GenerateAdministrations`-Parameterreihenfolge). `frequenz`/`typ` werden als Enum-`->value`-Strings durch die UI gereicht (konsistent mit Plan-5-DTOs, die Strings erwarten). `dosis`-Keys = `AdministrationTimeslot::scheduled()`-Values — identisch zu dem, was `GenerateAdministrations` ausliest. Einzige nicht selbst verifizierbare Stelle = `VitalData` (s. o., abgesichert durch Pflicht-Vorab-Schritt + Test).
