# FIFO-Vorratsbewertung + Inventur — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Vorräte FIFO-bewerten (§ 256 HGB) und eine Inventur-Kampagne (§§ 240/241 HGB / PBV) bauen — als Fundament für den weiteren WaWi-Ausbau.

**Architecture:** Jeder Wareneingang legt eine `Lagerschicht` (FIFO-Lot) an; Verbrauch zehrt älteste Schichten ab und schreibt unveränderliche `Schichtabgang`-Zeilen mit echten Schichtkosten. Eine `Inventur` snapshottet Soll-Mengen, erfasst Ist, bucht die Differenz (Inventurdifferenz-Konto) und friert den Bestandswert ein.

**Tech Stack:** Laravel 13, PHP 8.3+, Livewire 4, Pest 4, Larastan L5, spatie-permission. Domäne `app/Domains/Accounting`.

**Spec:** `docs/superpowers/specs/2026-06-07-fifo-bewertung-inventur-design.md` (Entscheidungen D1–D6).

---

## Dateistruktur

**Neu:**
- `database/migrations/2026_06_10_100000_create_lagerschichten_table.php`
- `database/migrations/2026_06_10_100100_create_schichtabgaenge_table.php`
- `database/migrations/2026_06_10_100200_create_inventuren_table.php`
- `database/migrations/2026_06_10_100300_create_inventur_positionen_table.php`
- `app/Domains/Accounting/Models/Lagerschicht.php` (BelongsToTenant-only, kein LogsActivity — D5)
- `app/Domains/Accounting/Models/Schichtabgang.php` (BelongsToTenant-only, append-only — D5)
- `app/Domains/Accounting/Models/Inventur.php` (BaseModel)
- `app/Domains/Accounting/Models/Inventurposition.php` (BaseModel)
- `app/Domains/Accounting/Enums/InventurStatus.php`
- `app/Domains/Accounting/Support/Lagerwert.php`
- `app/Domains/Accounting/Actions/InventurStarten.php`
- `app/Domains/Accounting/Actions/InventurAbschliessen.php`
- `app/Livewire/Accounting/Inventur.php` + `resources/views/livewire/accounting/inventur.blade.php`
- `tests/Feature/Accounting/FifoBewertungTest.php`
- `tests/Feature/Accounting/InventurTest.php`

**Geändert:**
- `app/Domains/Accounting/Actions/Wareneingang.php` (Schicht anlegen)
- `app/Domains/Accounting/Actions/Warenverbrauch.php` (FIFO-Abzug + Schichtabgang + Exception statt stillem Clamp)
- `app/Domains/Accounting/Support/AccountingDefaults.php` (`INVENTURDIFFERENZ`)
- `routes/web.php` (Route `inventur`)
- `resources/views/layouts/app.blade.php` (Nav-Link, Finanzen-Block)
- `app/Livewire/Accounting/Buchhaltung.php` + `resources/views/livewire/accounting/buchhaltung.blade.php` (Bestandswert je Artikel)
- `app/Domains/Identity/Database/Seeders/DemoSeeder.php` (FIFO-Demo + abgeschlossene Inventur)

**Konvention (aus Bestand):** Modelle extenden `BaseModel` (= `BelongsToTenant` + `LogsActivity`, auto-`tenant_id`) außer `Lagerschicht`/`Schichtabgang` (nur `use App\Domains\Identity\Concerns\BelongsToTenant;`, Vorbild `app/Domains/Personnel/Models/Energielevel.php`). Geld `decimal:2`, Schicht-Einstandspreis `decimal:4`. Nach allen Modellen: `php artisan ide-helper:models -W -R -M "App\Domains\Accounting\Models\<X>"` für die `@property`-Docblocks (PHPStan L5).

---

## Task 1: Migrationen (4 Tabellen)

**Files:** Create die 4 Migrationsdateien oben.

- [ ] **Step 1: `lagerschichten`**

```php
Schema::create('lagerschichten', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
    $table->foreignId('eingang_bewegung_id')->nullable()->constrained('lagerbewegungen')->nullOnDelete();
    $table->date('eingangsdatum');
    $table->decimal('menge_eingang', 12, 2);
    $table->decimal('menge_rest', 12, 2);
    $table->decimal('einstandspreis', 12, 4);
    $table->string('charge_nr')->nullable();
    $table->date('mhd')->nullable();
    $table->timestamps();
    $table->index(['artikel_id', 'eingangsdatum', 'id']); // FIFO-Reihenfolge
});
```

- [ ] **Step 2: `schichtabgaenge`**

```php
Schema::create('schichtabgaenge', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('bewegung_id')->constrained('lagerbewegungen')->cascadeOnDelete();
    $table->foreignId('schicht_id')->constrained('lagerschichten')->cascadeOnDelete();
    $table->decimal('menge', 12, 2);
    $table->decimal('einstandspreis', 12, 4);
    $table->timestamps();
    $table->index(['bewegung_id']);
});
```

- [ ] **Step 3: `inventuren`**

```php
Schema::create('inventuren', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('abteilung')->nullable(); // null = ganzes Haus
    $table->date('stichtag');
    $table->string('status')->default('offen');
    $table->decimal('bestandswert_summe', 14, 2)->nullable();
    $table->foreignId('differenz_buchung_id')->nullable()->constrained('buchungen')->nullOnDelete();
    $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('abgeschlossen_von')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('abgeschlossen_am')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: `inventur_positionen`**

```php
Schema::create('inventur_positionen', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('inventur_id')->constrained('inventuren')->cascadeOnDelete();
    $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
    $table->decimal('soll_menge', 12, 2);
    $table->decimal('ist_menge', 12, 2)->nullable();
    $table->decimal('einstandspreis_schnitt', 12, 4)->default(0);
    $table->foreignId('gezaehlt_von')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('gezaehlt_am')->nullable();
    $table->timestamps();
    $table->unique(['inventur_id', 'artikel_id']);
});
```

- [ ] **Step 5: Run** `php artisan migrate:fresh` — Expected: alle Tabellen ok, kein Fehler. (Seed kommt später.)

- [ ] **Step 6: Commit** `git add database/migrations && git commit -m "feat(wawi): FIFO-Schicht- + Inventur-Tabellen"`

---

## Task 2: Modelle, Enum & Konto-Default

**Files:** Create die 5 Modell-/Enum-Dateien; Modify `AccountingDefaults.php`.

- [ ] **Step 1: `InventurStatus` Enum**

```php
namespace App\Domains\Accounting\Enums;

enum InventurStatus: string
{
    case Offen = 'offen';
    case Abgeschlossen = 'abgeschlossen';

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'offen (in Zählung)',
            self::Abgeschlossen => 'abgeschlossen',
        };
    }
}
```

- [ ] **Step 2: `Lagerschicht`** (kein `LogsActivity`, Vorbild Energielevel)

```php
namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lagerschicht extends Model
{
    use BelongsToTenant;

    protected $table = 'lagerschichten';

    protected $fillable = ['tenant_id', 'artikel_id', 'eingang_bewegung_id', 'eingangsdatum',
        'menge_eingang', 'menge_rest', 'einstandspreis', 'charge_nr', 'mhd'];

    protected $casts = ['eingangsdatum' => 'date', 'mhd' => 'date',
        'menge_eingang' => 'decimal:2', 'menge_rest' => 'decimal:2', 'einstandspreis' => 'decimal:4'];

    public function artikel(): BelongsTo { return $this->belongsTo(Artikel::class); }

    /** @return HasMany<Schichtabgang, $this> */
    public function abgaenge(): HasMany { return $this->hasMany(Schichtabgang::class, 'schicht_id'); }

    public function offen(): bool { return (float) $this->menge_rest > 0; }
}
```

- [ ] **Step 3: `Schichtabgang`** (append-only, kein `LogsActivity`)

```php
namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schichtabgang extends Model
{
    use BelongsToTenant;

    protected $table = 'schichtabgaenge';

    protected $fillable = ['tenant_id', 'bewegung_id', 'schicht_id', 'menge', 'einstandspreis'];

    protected $casts = ['menge' => 'decimal:2', 'einstandspreis' => 'decimal:4'];

    public function schicht(): BelongsTo { return $this->belongsTo(Lagerschicht::class, 'schicht_id'); }

    public function bewegung(): BelongsTo { return $this->belongsTo(Lagerbewegung::class, 'bewegung_id'); }
}
```

- [ ] **Step 4: `Inventur`** (BaseModel)

```php
namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\InventurStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventur extends BaseModel
{
    protected $table = 'inventuren';

    protected $fillable = ['tenant_id', 'abteilung', 'stichtag', 'status', 'bestandswert_summe',
        'differenz_buchung_id', 'erstellt_von', 'abgeschlossen_von', 'abgeschlossen_am'];

    protected $casts = ['abteilung' => Abteilung::class, 'stichtag' => 'date', 'status' => InventurStatus::class,
        'bestandswert_summe' => 'decimal:2', 'abgeschlossen_am' => 'datetime'];

    /** @return HasMany<Inventurposition, $this> */
    public function positionen(): HasMany { return $this->hasMany(Inventurposition::class); }

    public function offen(): bool { return $this->status === InventurStatus::Offen; }
}
```

- [ ] **Step 5: `Inventurposition`** (BaseModel)

```php
namespace App\Domains\Accounting\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventurposition extends BaseModel
{
    protected $table = 'inventur_positionen';

    protected $fillable = ['tenant_id', 'inventur_id', 'artikel_id', 'soll_menge', 'ist_menge',
        'einstandspreis_schnitt', 'gezaehlt_von', 'gezaehlt_am'];

    protected $casts = ['soll_menge' => 'decimal:2', 'ist_menge' => 'decimal:2',
        'einstandspreis_schnitt' => 'decimal:4', 'gezaehlt_am' => 'datetime'];

    public function inventur(): BelongsTo { return $this->belongsTo(Inventur::class); }

    public function artikel(): BelongsTo { return $this->belongsTo(Artikel::class); }

    public function gezaehlt(): bool { return $this->ist_menge !== null; }

    public function differenzMenge(): float { return (float) ($this->ist_menge ?? 0) - (float) $this->soll_menge; }

    public function differenzWert(): float { return round($this->differenzMenge() * (float) $this->einstandspreis_schnitt, 2); }
}
```

- [ ] **Step 6: `AccountingDefaults` — `INVENTURDIFFERENZ`**

In `app/Domains/Accounting/Support/AccountingDefaults.php`: Konstante neben `WARENBESTAND` ergänzen und ins `$standard`-Array seeden.

```php
public const INVENTURDIFFERENZ = '4980';
```
```php
// im $standard-Array von ensureFor(), nach WARENBESTAND:
[self::INVENTURDIFFERENZ, 'Bestandsdifferenzen (Inventur)', KontoTyp::Aufwand],
```

- [ ] **Step 7:** `php artisan ide-helper:models -W -R -M "App\Domains\Accounting\Models\Lagerschicht" -M "App\Domains\Accounting\Models\Schichtabgang" -M "App\Domains\Accounting\Models\Inventur" -M "App\Domains\Accounting\Models\Inventurposition"` (Docblocks für PHPStan).

- [ ] **Step 8: Run** `vendor/bin/pint app/Domains/Accounting && php -d memory_limit=1G vendor/bin/phpstan analyse app/Domains/Accounting/Models app/Domains/Accounting/Enums app/Domains/Accounting/Support/AccountingDefaults.php` — Expected: 0 errors.

- [ ] **Step 9: Commit** `git commit -am "feat(wawi): Schicht-/Inventur-Modelle + Inventurdifferenz-Konto"`

---

## Task 3: Wareneingang legt FIFO-Schicht an

**Files:** Modify `app/Domains/Accounting/Actions/Wareneingang.php`; Test `tests/Feature/Accounting/FifoBewertungTest.php`.

- [ ] **Step 1: Failing test** (neue Datei `FifoBewertungTest.php`)

```php
<?php

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
    $this->mehl = Artikel::create(['name' => 'Mehl', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.00]);
});

it('legt je Wareneingang eine FIFO-Schicht mit Restmenge = Eingangsmenge an', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');

    $schichten = Lagerschicht::where('artikel_id', $this->mehl->id)->orderBy('eingangsdatum')->get();
    expect($schichten)->toHaveCount(2)
        ->and((float) $schichten[0]->menge_rest)->toBe(10.0)
        ->and((float) $schichten[0]->einstandspreis)->toBe(2.0)
        ->and((float) $schichten[1]->einstandspreis)->toBe(3.0)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(20.0);
});
```

- [ ] **Step 2: Run** `php -d memory_limit=1G vendor/bin/pest tests/Feature/Accounting/FifoBewertungTest.php` — Expected: FAIL (keine Schicht angelegt).

- [ ] **Step 3: Implement** — in `Wareneingang::handle()`, innerhalb der Transaction nach `$artikel->save()` und nach Erzeugung der `$bewegung` die Schicht anlegen. Reihenfolge anpassen: erst `$bewegung` erstellen, dann Schicht mit `eingang_bewegung_id`. Signatur um optionale `$chargeNr`/`$mhd` erweitern.

```php
public function handle(Artikel $artikel, float $menge, ?float $preis, string $datum, ?string $notiz = null, ?string $chargeNr = null, ?string $mhd = null): Lagerbewegung
{
    return DB::transaction(function () use ($artikel, $menge, $preis, $datum, $notiz, $chargeNr, $mhd) {
        AccountingDefaults::ensureFor($artikel->tenant_id);
        $stueckpreis = $preis ?? (float) ($artikel->einkaufspreis ?? 0);

        $artikel->bestand = (float) $artikel->bestand + $menge;
        if ($preis !== null) {
            $artikel->einkaufspreis = $preis; // nur Anzeige-/Bestell-Default (D1)
        }
        $artikel->save();

        $buchung = null;
        $betrag = round($menge * $stueckpreis, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN)->id,
                $betrag, 'Wareneingang: '.$artikel->name, $datum,
            );
        }

        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'eingang', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz, 'buchung_id' => $buchung?->id,
        ]);

        $artikel->schichten()->create([
            'tenant_id' => $artikel->tenant_id, 'eingang_bewegung_id' => $bewegung->id,
            'eingangsdatum' => $datum, 'menge_eingang' => $menge, 'menge_rest' => $menge,
            'einstandspreis' => $stueckpreis, 'charge_nr' => $chargeNr, 'mhd' => $mhd,
        ]);

        return $bewegung;
    });
}
```

Add `use App\Domains\Accounting\Models\Lagerschicht;`. In `Artikel` model eine Relation ergänzen:

```php
/** @return HasMany<Lagerschicht, $this> */
public function schichten(): HasMany { return $this->hasMany(Lagerschicht::class); }
```
(plus `use App\Domains\Accounting\Models\Lagerschicht;` falls nötig — `Lagerschicht` liegt im selben Namespace, also kein Import nötig; `HasMany` ist bereits importiert.)

- [ ] **Step 4: Run** dieselbe Pest-Datei — Expected: PASS. Dann die bestehende `WarenwirtschaftTest.php` laufen lassen — Expected: weiterhin PASS (Single-Layer-Bewertung identisch).

- [ ] **Step 5: Commit** `git commit -am "feat(wawi): Wareneingang legt FIFO-Schicht an"`

---

## Task 4: Warenverbrauch zehrt FIFO ab (echte Schichtkosten, kein stilles Clamp)

**Files:** Modify `app/Domains/Accounting/Actions/Warenverbrauch.php`; Test `FifoBewertungTest.php`.

- [ ] **Step 1: Failing tests** (an `FifoBewertungTest.php` anhängen)

```php
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Models\Schichtabgang;

it('verbraucht FIFO über Schichten und bucht die tatsächlichen Schichtkosten', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');

    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 15, '2026-06-10');

    // 10×2 + 5×3 = 35 €
    expect(AccountingDefaults::konto(Abteilung::Kueche->aufwandKonto())->saldo())->toBe(35.0)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(5.0);

    $rest = Lagerschicht::where('artikel_id', $this->mehl->id)->where('menge_rest', '>', 0)->get();
    expect($rest)->toHaveCount(1)->and((float) $rest[0]->einstandspreis)->toBe(3.0)
        ->and((float) $rest[0]->menge_rest)->toBe(5.0);
    expect(Schichtabgang::count())->toBe(2); // zwei Schichten angezehrt
});

it('wirft bei Verbrauch über den Bestand hinaus (kein stilles Clamp)', function () {
    app(Wareneingang::class)->handle($this->mehl, 3, 2.00, '2026-06-08');

    expect(fn () => app(Warenverbrauch::class)->handle($this->mehl->fresh(), 5, '2026-06-10'))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run** — Expected: FAIL (heute klemmt es still, Kosten = letzter Preis).

- [ ] **Step 3: Implement** `Warenverbrauch::handle()`:

```php
public function handle(Artikel $artikel, float $menge, string $datum, ?string $notiz = null): Lagerbewegung
{
    return DB::transaction(function () use ($artikel, $menge, $datum, $notiz) {
        AccountingDefaults::ensureFor($artikel->tenant_id);

        $schichten = $artikel->schichten()->where('menge_rest', '>', 0)
            ->orderBy('eingangsdatum')->orderBy('id')->lockForUpdate()->get();
        $verfuegbar = (float) $schichten->sum(fn ($s) => (float) $s->menge_rest);
        if ($verfuegbar + 1e-9 < $menge) {
            throw new InvalidArgumentException(
                'Verbrauch übersteigt den Bestand ('.number_format($verfuegbar, 2, ',', '.').' '.$artikel->einheit.').');
        }

        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'verbrauch', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz,
        ]);

        $offen = $menge;
        $kosten = 0.0;
        foreach ($schichten as $schicht) {
            if ($offen <= 1e-9) {
                break;
            }
            $nimm = min($offen, (float) $schicht->menge_rest);
            $schicht->menge_rest = (float) $schicht->menge_rest - $nimm;
            $schicht->save();
            $bewegung->abgaenge()->create([
                'tenant_id' => $artikel->tenant_id, 'schicht_id' => $schicht->id,
                'menge' => $nimm, 'einstandspreis' => $schicht->einstandspreis,
            ]);
            $kosten += $nimm * (float) $schicht->einstandspreis;
            $offen -= $nimm;
        }

        $artikel->bestand = (float) $artikel->bestand - $menge;
        $artikel->save();

        $betrag = round($kosten, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto($artikel->abteilung->aufwandKonto())->id,
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                $betrag, 'Verbrauch: '.$artikel->name.' ('.$artikel->abteilung->label().')', $datum,
            );
            $bewegung->update(['buchung_id' => $buchung->id]);
        }

        return $bewegung;
    });
}
```

Add `use App\Domains\Accounting\Models\Lagerbewegung;` (für Rückgabetyp – bereits da), `use InvalidArgumentException;`. `Lagerbewegung` braucht eine `abgaenge()`-Relation:

```php
/** @return HasMany<Schichtabgang, $this> */
public function abgaenge(): HasMany { return $this->hasMany(Schichtabgang::class, 'bewegung_id'); }
```
(plus `use Illuminate\Database\Eloquent\Relations\HasMany;` in `Lagerbewegung`).

- [ ] **Step 4: Run** `FifoBewertungTest.php` + `WarenwirtschaftTest.php` — Expected: alle PASS (Single-Layer-Verbrauch in WarenwirtschaftTest ergibt 4×2=8 wie bisher).

- [ ] **Step 5: Commit** `git commit -am "feat(wawi): Warenverbrauch FIFO-Abzug mit echten Schichtkosten, Exception statt stillem Clamp"`

---

## Task 5: Lagerwert-Service

**Files:** Create `app/Domains/Accounting/Support/Lagerwert.php`; Test in `FifoBewertungTest.php`.

- [ ] **Step 1: Failing test**

```php
use App\Domains\Accounting\Support\Lagerwert;

it('berechnet den FIFO-Bestandswert aus den Restschichten', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');
    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 15, '2026-06-10');

    expect(app(Lagerwert::class)->bestandswert($this->mehl->fresh()))->toBe(15.0); // 5 × 3,00
});
```

- [ ] **Step 2: Run** — Expected: FAIL (Klasse fehlt).

- [ ] **Step 3: Implement**

```php
namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Identity\Support\CurrentTenant;

class Lagerwert
{
    public function bestandswert(Artikel $artikel): float
    {
        return round((float) $artikel->schichten()->where('menge_rest', '>', 0)
            ->get()->sum(fn (Lagerschicht $s) => (float) $s->menge_rest * (float) $s->einstandspreis), 2);
    }

    public function bestandswertGesamt(int $tenantId, ?Abteilung $abteilung = null): float
    {
        $artikel = Artikel::where('tenant_id', $tenantId)
            ->when($abteilung, fn ($q) => $q->where('abteilung', $abteilung->value))->get();

        return round((float) $artikel->sum(fn (Artikel $a) => $this->bestandswert($a)), 2);
    }
}
```

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -am "feat(wawi): Lagerwert-Service (FIFO-Bestandswert)"`

---

## Task 6: InventurStarten (Snapshot)

**Files:** Create `app/Domains/Accounting/Actions/InventurStarten.php`; Test `tests/Feature/Accounting/InventurTest.php`.

- [ ] **Step 1: Failing test** (neue Datei `InventurTest.php`, beforeEach analog FifoBewertungTest mit `$this->mehl`)

```php
use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Enums\InventurStatus;

it('startet eine Inventur und snapshottet die Soll-Mengen je aktivem Artikel', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');

    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);

    expect($inventur->status)->toBe(InventurStatus::Offen)
        ->and($inventur->positionen)->toHaveCount(1)
        ->and((float) $inventur->positionen[0]->soll_menge)->toBe(10.0)
        ->and((float) $inventur->positionen[0]->einstandspreis_schnitt)->toBe(2.0);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

```php
namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Support\Lagerwert;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class InventurStarten
{
    public function __construct(private readonly Lagerwert $lagerwert) {}

    public function handle(string $stichtag, ?Abteilung $abteilung, ?int $userId): Inventur
    {
        $tenantId = app(CurrentTenant::class)->id();

        return DB::transaction(function () use ($stichtag, $abteilung, $userId, $tenantId) {
            $inventur = Inventur::create([
                'tenant_id' => $tenantId, 'abteilung' => $abteilung?->value, 'stichtag' => $stichtag,
                'status' => InventurStatus::Offen->value, 'erstellt_von' => $userId,
            ]);

            $artikel = Artikel::where('tenant_id', $tenantId)->where('aktiv', true)
                ->when($abteilung, fn ($q) => $q->where('abteilung', $abteilung->value))->get();

            foreach ($artikel as $a) {
                $sollMenge = (float) $a->bestand;
                $wert = $this->lagerwert->bestandswert($a);
                $schnitt = $sollMenge > 0 ? round($wert / $sollMenge, 4) : (float) ($a->einkaufspreis ?? 0);
                $inventur->positionen()->create([
                    'tenant_id' => $tenantId, 'artikel_id' => $a->id,
                    'soll_menge' => $sollMenge, 'einstandspreis_schnitt' => $schnitt,
                ]);
            }

            return $inventur->load('positionen');
        });
    }
}
```

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -am "feat(wawi): InventurStarten snapshottet Soll-Mengen"`

---

## Task 7: InventurAbschliessen (Schwund/Mehrbestand/Freeze/Guard/nicht-gezählt)

**Files:** Create `app/Domains/Accounting/Actions/InventurAbschliessen.php`; Tests in `InventurTest.php`.

- [ ] **Step 1: Failing tests**

```php
use App\Domains\Accounting\Actions\InventurAbschliessen;
use App\Domains\Accounting\Support\Lagerwert;

it('bucht Schwund FIFO ab (Inventurdifferenz an Warenbestand) und gleicht den Bestand ab', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 5, 3.00, '2026-06-09'); // soll 15
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    $pos = $inventur->positionen[0];
    $pos->update(['ist_menge' => 12]); // Schwund 3 → FIFO aus der 2€-Schicht

    $report = app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect((float) $this->mehl->fresh()->bestand)->toBe(12.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->saldo())->toBe(6.0) // 3 × 2,00
        ->and($inventur->fresh()->status)->toBe(InventurStatus::Abgeschlossen)
        ->and($report['gebucht'])->toBe(1)->and($report['nicht_gezaehlt'])->toBe(0);
});

it('legt bei Mehrbestand eine neue Schicht an (Warenbestand an Inventurdifferenz)', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08'); // soll 10
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    $inventur->positionen[0]->update(['ist_menge' => 13]); // +3

    app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect((float) $this->mehl->fresh()->bestand)->toBe(13.0)
        ->and(app(Lagerwert::class)->bestandswert($this->mehl->fresh()))->toBe(26.0) // 13 × 2,00
        ->and(AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->saldo())->toBe(-6.0); // Ertrag
});

it('zählt nicht erfasste Positionen transparent und bucht sie nicht als 0-Differenz', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    // ist_menge NICHT gesetzt

    $report = app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect($report['gebucht'])->toBe(0)->and($report['nicht_gezaehlt'])->toBe(1)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(10.0); // unverändert
});

it('verhindert den Doppel-Abschluss', function () {
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect(fn () => app(InventurAbschliessen::class)->handle($inventur->fresh(), null))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

```php
namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Lagerwert;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventurAbschliessen
{
    public function __construct(
        private readonly Buchen $buchen,
        private readonly Lagerwert $lagerwert,
    ) {}

    /** @return array{gebucht: int, nicht_gezaehlt: int} */
    public function handle(Inventur $inventur, ?int $userId): array
    {
        if (! $inventur->offen()) {
            throw new InvalidArgumentException('Inventur ist bereits abgeschlossen.');
        }
        AccountingDefaults::ensureFor($inventur->tenant_id);
        $stichtag = $inventur->stichtag->toDateString();

        return DB::transaction(function () use ($inventur, $userId, $stichtag) {
            $gebucht = 0;
            $nichtGezaehlt = 0;

            foreach ($inventur->positionen()->with('artikel')->get() as $pos) {
                if (! $pos->gezaehlt()) {
                    $nichtGezaehlt++;
                    continue; // D6: nie still als 0 buchen
                }
                $diff = round($pos->differenzMenge(), 2);
                if (abs($diff) < 0.005) {
                    continue;
                }
                $artikel = $pos->artikel;
                if ($diff < 0) {
                    $this->bucheSchwund($pos, $artikel, abs($diff), $stichtag);
                } else {
                    $this->bucheMehrbestand($pos, $artikel, $diff, $stichtag);
                }
                $artikel->update(['bestand' => (float) $pos->ist_menge]);
                $gebucht++;
            }

            $inventur->update([
                'status' => InventurStatus::Abgeschlossen->value,
                'bestandswert_summe' => $this->lagerwert->bestandswertGesamt(
                    $inventur->tenant_id, $inventur->abteilung),
                'abgeschlossen_von' => $userId, 'abgeschlossen_am' => now(),
            ]);

            return ['gebucht' => $gebucht, 'nicht_gezaehlt' => $nichtGezaehlt];
        });
    }

    private function bucheSchwund(Inventurposition $pos, $artikel, float $menge, string $stichtag): void
    {
        // FIFO abzehren (älteste zuerst), echte Schichtkosten als Differenzwert
        $schichten = $artikel->schichten()->where('menge_rest', '>', 0)
            ->orderBy('eingangsdatum')->orderBy('id')->lockForUpdate()->get();
        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'inventur', 'menge' => $menge, 'datum' => $stichtag, 'notiz' => 'Inventur-Schwund',
        ]);
        $offen = $menge;
        $kosten = 0.0;
        foreach ($schichten as $schicht) {
            if ($offen <= 1e-9) {
                break;
            }
            $nimm = min($offen, (float) $schicht->menge_rest);
            $schicht->update(['menge_rest' => (float) $schicht->menge_rest - $nimm]);
            $bewegung->abgaenge()->create([
                'tenant_id' => $artikel->tenant_id, 'schicht_id' => $schicht->id,
                'menge' => $nimm, 'einstandspreis' => $schicht->einstandspreis,
            ]);
            $kosten += $nimm * (float) $schicht->einstandspreis;
            $offen -= $nimm;
        }
        $betrag = round($kosten, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->id,
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                $betrag, 'Inventur-Schwund: '.$artikel->name, $stichtag, 'Inventur #'.$pos->inventur_id);
            $bewegung->update(['buchung_id' => $buchung->id]);
        }
    }

    private function bucheMehrbestand(Inventurposition $pos, $artikel, float $menge, string $stichtag): void
    {
        $preis = (float) $pos->einstandspreis_schnitt;
        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'inventur', 'menge' => $menge, 'datum' => $stichtag, 'notiz' => 'Inventur-Mehrbestand',
        ]);
        $artikel->schichten()->create([
            'tenant_id' => $artikel->tenant_id, 'eingang_bewegung_id' => $bewegung->id,
            'eingangsdatum' => $stichtag, 'menge_eingang' => $menge, 'menge_rest' => $menge,
            'einstandspreis' => $preis,
        ]);
        $betrag = round($menge * $preis, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->id,
                $betrag, 'Inventur-Mehrbestand: '.$artikel->name, $stichtag, 'Inventur #'.$pos->inventur_id);
            $bewegung->update(['buchung_id' => $buchung->id]);
        }
    }
}
```

> Hinweis: `Inventur::abteilung` ist als `Abteilung`-Enum gecastet; `bestandswertGesamt(int, ?Abteilung)` nimmt das Enum direkt — `$inventur->abteilung` liefert `?Abteilung`. Passt.

- [ ] **Step 4: Run** `InventurTest.php` — Expected: alle PASS.
- [ ] **Step 5:** `vendor/bin/pint app/Domains/Accounting && php -d memory_limit=1G vendor/bin/phpstan analyse app/Domains/Accounting` — 0 errors.
- [ ] **Step 6: Commit** `git commit -am "feat(wawi): InventurAbschliessen — Schwund/Mehrbestand-Buchung, Bestandswert-Freeze, nicht-gezählt transparent"`

---

## Task 8: Livewire-Eintrittspunkt (Inventur-UI)

**Files:** Create `app/Livewire/Accounting/Inventur.php` + `resources/views/livewire/accounting/inventur.blade.php`; Modify `routes/web.php`, `resources/views/layouts/app.blade.php`; Test in `InventurTest.php`.

- [ ] **Step 1: Failing test** (Livewire-Smoke + Rollen-Gate; oben in der Datei `use Livewire\Livewire;` und einen `admin`-User mit Tenant erzeugen)

```php
use App\Livewire\Accounting\Inventur as InventurLivewire;
use App\Domains\Identity\Models\User;
use Livewire\Livewire;

it('startet und schließt eine Inventur über die Livewire-Komponente ab', function () {
    $user = User::create(['name' => 'Admin', 'email' => 'a@a.de', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
    $user->assignRole('admin');
    $this->actingAs($user);
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');

    Livewire::test(InventurLivewire::class)
        ->set('neu_stichtag', '2026-06-30')->call('starten')
        ->assertHasNoErrors();

    $inventur = \App\Domains\Accounting\Models\Inventur::firstOrFail();
    Livewire::test(InventurLivewire::class)
        ->call('zaehlen', $inventur->positionen[0]->id, 8)
        ->call('abschliessen', $inventur->id)
        ->assertHasNoErrors();

    expect($inventur->fresh()->status->value)->toBe('abgeschlossen')
        ->and((float) $this->mehl->fresh()->bestand)->toBe(8.0);
});
```

- [ ] **Step 2: Run** — Expected: FAIL (Komponente/Route fehlt).

- [ ] **Step 3: Implement Livewire** `app/Livewire/Accounting/Inventur.php` — Muster: `#[Layout('layouts.app')]`, `mount()`/`render()` mit `abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin','buchhaltung']), 403)`. Properties `neu_stichtag`, `neu_abteilung`; Methoden `starten()` (validiert Datum, ruft `InventurStarten`), `zaehlen(int $positionId, float $menge)` (lädt tenant-gescopt `Inventurposition::findOrFail`, setzt `ist_menge`/`gezaehlt_von`/`gezaehlt_am`), `abschliessen(int $inventurId)` (ruft `InventurAbschliessen`, flash mit `gebucht`/`nicht_gezaehlt`). `render()` gibt offene + abgeschlossene Inventuren (mit Positionen) + `Lagerwert::bestandswertGesamt` zurück. **IDOR:** alle Lookups über tenant-gescopte Models (BaseModel-Scope greift) bzw. `->where('tenant_id', app(CurrentTenant::class)->id())`.

```php
namespace App\Livewire\Accounting;

use App\Domains\Accounting\Actions\InventurAbschliessen;
use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Inventur as InventurModel;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Accounting\Support\Lagerwert;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Inventur extends Component
{
    public ?string $neu_stichtag = null;

    public ?string $neu_abteilung = null;

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->neu_stichtag = now()->toDateString();
    }

    private function darf(): bool
    {
        return auth()->user()?->isSuperAdmin() || (bool) auth()->user()?->hasAnyRole(['admin', 'buchhaltung']);
    }

    public function starten(InventurStarten $action): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['neu_stichtag' => ['required', 'date'], 'neu_abteilung' => ['nullable']]);
        $abteilung = $this->neu_abteilung ? Abteilung::tryFrom($this->neu_abteilung) : null;
        $action->handle($this->neu_stichtag, $abteilung, auth()->id());
        session()->flash('status', 'Inventur gestartet.');
    }

    public function zaehlen(int $positionId, float $menge): void
    {
        abort_unless($this->darf(), 403);
        $pos = Inventurposition::findOrFail($positionId); // tenant-gescopt via BaseModel
        abort_unless($pos->inventur->offen(), 403);
        $pos->update(['ist_menge' => $menge, 'gezaehlt_von' => auth()->id(), 'gezaehlt_am' => now()]);
    }

    public function abschliessen(int $inventurId, InventurAbschliessen $action): void
    {
        abort_unless($this->darf(), 403);
        $inventur = InventurModel::findOrFail($inventurId);
        $report = $action->handle($inventur, auth()->id());
        session()->flash('status', "Inventur abgeschlossen: {$report['gebucht']} gebucht, {$report['nicht_gezaehlt']} nicht gezählt.");
    }

    public function render(Lagerwert $lagerwert)
    {
        return view('livewire.accounting.inventur', [
            'offene' => InventurModel::where('status', 'offen')->with('positionen.artikel')->latest()->get(),
            'abgeschlossene' => InventurModel::where('status', 'abgeschlossen')->with('positionen')->latest()->limit(10)->get(),
            'abteilungen' => Abteilung::cases(),
            'bestandswert' => $lagerwert->bestandswertGesamt(app(\App\Domains\Identity\Support\CurrentTenant::class)->id()),
        ]);
    }
}
```

- [ ] **Step 4: Blade** `resources/views/livewire/accounting/inventur.blade.php` — Muster der vorhandenen Accounting-Views (Karten, Tabellen). Mindestens: Start-Formular (`wire:model` `neu_stichtag`/`neu_abteilung`, `wire:click="starten"`), Liste offener Inventuren mit Positionstabelle (Soll, Ist-Eingabe + `wire:click="zaehlen({{ $pos->id }}, …)"` via kleinem Input je Zeile, `differenzMenge()`-Anzeige), Abschluss-Button (`wire:confirm`), Protokoll abgeschlossener Inventuren mit `bestandswert_summe`. `@if (session('status')) … @endif`. Header zeigt `bestandswert` (Σ FIFO).

> Für das Zählen je Zeile genügt ein einfaches Pattern: pro Position ein lokales Input gebunden an ein Array `wire:model="ist.{{ $pos->id }}"`, Button ruft `zaehlen($pos->id, $ist[$pos->id])`. Dafür Property `public array $ist = [];` ergänzen und die Signatur `zaehlen(int $positionId)` lesen aus `$this->ist[$positionId]`. (Passe Test Step 1 entsprechend an: statt `->call('zaehlen', id, 8)` → `->set("ist.{$posId}", 8)->call('zaehlen', $posId)`.)

- [ ] **Step 5: Route** in `routes/web.php` (bei den anderen Accounting-Routes, im selben auth-Gruppenblock wie `buchhaltung`):

```php
use App\Livewire\Accounting\Inventur;
// …
Route::get('/inventur', Inventur::class)->name('inventur');
```

- [ ] **Step 6: Nav** in `resources/views/layouts/app.blade.php` im Finanzen-Block (nach der Buchhaltung-Zeile, ~Zeile 92):

```blade
<a href="{{ route('inventur') }}" @class(['is-active' => request()->routeIs('inventur')])>Inventur</a>
```

- [ ] **Step 7: Run** `InventurTest.php` — Expected: PASS.
- [ ] **Step 8: Commit** `git commit -am "feat(wawi): Inventur-Livewire + Route + Nav (Eintrittspunkt)"`

---

## Task 9: Bestandswert in der Warenwirtschafts-Ansicht

**Files:** Modify `app/Livewire/Accounting/Buchhaltung.php` (render), `resources/views/livewire/accounting/buchhaltung.blade.php`.

- [ ] **Step 1:** In `Buchhaltung::render()` eine Wertspalte je Artikel mitgeben: injiziere `Lagerwert` (Methoden-Injection in `render(Lagerwert $lagerwert, …)`) und baue `$artikelwerte = $artikel->mapWithKeys(fn ($a) => [$a->id => $lagerwert->bestandswert($a)])`. An die View durchreichen.
- [ ] **Step 2:** In der Artikel-Tabelle der Blade eine Spalte „Bestandswert (FIFO)" ergänzen: `{{ number_format($artikelwerte[$a->id] ?? 0, 2, ',', '.') }} €`. In der Kopfzeile Σ der Werte zeigen.
- [ ] **Step 3: Run** die bestehenden Buchhaltungs-Tests (`tests/Feature/Accounting/*`) — Expected: PASS (keine Verhaltensänderung an Aktionen).
- [ ] **Step 4: Commit** `git commit -am "feat(wawi): FIFO-Bestandswert in der Warenwirtschafts-Ansicht"`

---

## Task 10: Demo-Seed (FIFO sichtbar + abgeschlossene Inventur)

**Files:** Modify `app/Domains/Identity/Database/Seeders/DemoSeeder.php` (~Zeile 487–494).

- [ ] **Step 1:** Dem `mehl` einen **zweiten** Wareneingang zu abweichendem Preis geben (macht FIFO im Demo sichtbar) und eine **abgeschlossene Inventur mit kleiner Differenz** anlegen. Nach Zeile 494 einfügen:

```php
// FIFO sichtbar: zweite Lieferung Mehl zu höherem Preis (zwei Preisschichten).
app(Wareneingang::class)->handle($mehl->fresh(), 25, 1.10, now()->subDay()->toDateString(), 'Großhandel Bergisch (Nachlieferung)');

// Inventur (§§ 240/241 HGB): Stichtag, eine erfasste Zähldifferenz beim Mehl.
$inventur = app(\App\Domains\Accounting\Actions\InventurStarten::class)->handle(
    now()->toDateString(), \App\Domains\Accounting\Enums\Abteilung::Kueche, $buchhalterin->id);
$mehlPos = $inventur->positionen->firstWhere('artikel_id', $mehl->id);
$mehlPos?->update(['ist_menge' => (float) $mehlPos->soll_menge - 2, 'gezaehlt_von' => $buchhalterin->id, 'gezaehlt_am' => now()]); // 2 kg Schwund
app(\App\Domains\Accounting\Actions\InventurAbschliessen::class)->handle($inventur->fresh(), $buchhalterin->id);
```

- [ ] **Step 2: Run** `php artisan migrate:fresh --seed` — Expected: ohne Fehler.
- [ ] **Step 3:** Kurzer Tinker-Check: `php artisan tinker --execute="echo App\Domains\Accounting\Models\Inventur::first()?->status->value;"` → `abgeschlossen`.
- [ ] **Step 4: Commit** `git commit -am "chore(seed): FIFO-Demo (zwei Preisschichten) + abgeschlossene Inventur"`

---

## Task 11: Schlussgates, Doku, Wiki, Push

- [ ] **Step 1: Volle Suite** `php -d memory_limit=1G vendor/bin/pest` — Expected: alle grün (bestehende + neue: FIFO ~4, Inventur ~5, Livewire 1).
- [ ] **Step 2:** `php -d memory_limit=1G vendor/bin/phpstan analyse` — 0 errors. `vendor/bin/pint` — clean.
- [ ] **Step 3: Screenshot** via `node scripts/shots.mjs` (2FA-Arming-Prozedur beachten), Route `inventur` + Warenwirtschafts-Ansicht mit Bestandswert. Ablage `storage/app/shots/`.
- [ ] **Step 4: Doku** `docs/inventur-bewertung.md` (FIFO-Verfahren, Inventur-Workflow, Konten, Norm-Anker §§240/241/256 HGB, PBV). README-Accounting-Zeile + Testzahl aktualisieren. `docs/INBETRIEBNAHME.md` falls Schalter betroffen (hier keiner — produktiv nutzbar).
- [ ] **Step 5: Wiki** Seite `Inventur Bewertung.md` (+ Home-Link) ins Wiki-Repo, Screenshot beilegen. Selbst pushen (autorisiert).
- [ ] **Step 6: Memory** `opcare-fifo-inventur.md` + MEMORY.md-Indexzeile (Verfahren FIFO §256, Inventur §§240/241/PBV, Schichtabgang als #2/#5-Brücke, D1–D6, Konto 4980).
- [ ] **Step 7: Commit + Push** `git commit -am "docs(wawi): Inventur/FIFO-Doku + README + Wiki" && git push origin master`.

---

## Self-Review (gegen Spec)

- **Spec-Abdeckung:** FIFO-Schicht (T1/T2/T3), Schichtabgang (T2/T4), Verbrauch echte Kosten + Exception statt Clamp/D2 (T4), Lagerwert (T5), Inventur Start/Snapshot (T6), Abschluss Schwund/Mehrbestand/Freeze/Guard/D6 (T7), Inventurdifferenz-Konto/D3 (T2), Eintrittspunkt-UI (T8), Bestandswert-Sicht (T9), charge_nr/mhd-Felder/D4 (T1/T2), Schicht ohne LogsActivity/D5 (T2), Demo (T10), Gates/Doku/Wiki (T11). Alle Spec-Punkte haben eine Task.
- **Platzhalter:** keine — jeder Code-Step zeigt echten Code; Blade-Step nennt konkrete Bindings/Methoden.
- **Typ-Konsistenz:** `schichten()` (Artikel), `abgaenge()` (Lagerschicht + Lagerbewegung), `offen()` (Lagerschicht/Inventur), `gezaehlt()`/`differenzMenge()`/`differenzWert()` (Inventurposition), `bestandswert()`/`bestandswertGesamt()` (Lagerwert), `handle()`-Signaturen der Actions — durchgängig gleich benannt. `Inventur::abteilung` als `?Abteilung` gecastet, passt zu `bestandswertGesamt(int, ?Abteilung)`.
