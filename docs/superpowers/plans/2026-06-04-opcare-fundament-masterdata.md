# OPCare — Plan 1: Fundament + Masterdata — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein frisches Laravel-12-Repo („OPCare") mit Domänen-Struktur, mandantenfähigem Fundament (Identity/Tenant/RBAC/Audit) und vollständigem Stammdaten-Backend (Bewohner, Gebäude-Hierarchie, Diagnosen/ICD, Kassen, Betreuer, Ärzte, Dateien).

**Architecture:** Domänen-orientierter Monolith (`app/Domains/{Identity,Masterdata}`). Layering: Action-Klassen kapseln Schreiblogik, DTOs (`spatie/laravel-data`) transportieren Daten, Policies autorisieren, ein globaler `TenantScope` erzwingt Mandantentrennung. Reines Backend + Platzhalter-Views; die Designer-Frontend-Vorlage wird später eingearbeitet.

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL, Pest 3 (+ Pest Arch), spatie/laravel-data, spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-medialibrary.

**Referenz-Spec:** `docs/superpowers/specs/2026-06-04-pflegeplanung-laravel-design.md` (im OPDE-Vorlagen-Repo).

---

## File Structure (Plan 1)

```
opcare/                                    # neues Repo (Task 1)
├── app/
│   ├── Domains/
│   │   ├── Identity/
│   │   │   ├── Models/{Tenant.php, User.php}
│   │   │   ├── Concerns/BelongsToTenant.php
│   │   │   ├── Scopes/TenantScope.php
│   │   │   ├── Support/CurrentTenant.php
│   │   │   └── Database/{factories,seeders}/
│   │   └── Masterdata/
│   │       ├── Models/{Building,Floor,Station,Room,Resident,IcdCode,
│   │       │           ResidentDiagnosis,HealthInsurance,ResidentInsurance,
│   │       │           Custodian,Physician}.php
│   │       ├── Actions/{CreateResident,UpdateResident,CreateBuilding,...}.php
│   │       ├── Data/{ResidentData,BuildingData,...}.php
│   │       ├── Policies/{ResidentPolicy,BuildingPolicy,...}.php
│   │       └── Database/factories/
│   └── Support/Models/BaseModel.php
├── database/migrations/                   # eine Migration je Tabelle
├── tests/
│   ├── Arch/LayeringTest.php
│   ├── Feature/Identity/...
│   └── Feature/Masterdata/...
└── composer.json                          # PSR-4 App\Domains
```

---

## Task 1: Laravel-12-Projekt scaffolden

**Files:**
- Create: `opcare/` (gesamtes neues Projekt + Repo)

- [ ] **Step 1: Projekt erzeugen**

Run (im übergeordneten Verzeichnis, NICHT im OPDE-Repo):
```bash
composer create-project laravel/laravel opcare
cd opcare
php artisan --version   # erwartet: Laravel Framework 12.x
php -v                  # erwartet: PHP 8.5.x
```

- [ ] **Step 2: Git initialisieren**

```bash
git init
git add -A && git commit -m "chore: laravel 12 scaffold"
```

- [ ] **Step 3: PostgreSQL in `.env` konfigurieren**

`.env` (Werte anpassen):
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=opcare
DB_USERNAME=opcare
DB_PASSWORD=secret
```

- [ ] **Step 4: DB-Verbindung prüfen**

Run:
```bash
php artisan migrate
```
Expected: Default-Migrations (users, cache, jobs) laufen ohne Fehler durch.

- [ ] **Step 5: Pakete installieren**

```bash
composer require spatie/laravel-data spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-medialibrary
composer require --dev pestphp/pest pestphp/pest-plugin-laravel pestphp/pest-plugin-arch
php artisan pest:install
```

- [ ] **Step 6: Spatie-Migrations & Configs publishen**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```
Expected: permission-, activity_log-, media-Tabellen angelegt.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "chore: install data/permission/activitylog/medialibrary + pest"
```

---

## Task 2: Domänen-Struktur + PSR-4 + Layering-Arch-Test

**Files:**
- Modify: `composer.json` (autoload)
- Create: `app/Domains/.gitkeep`, `tests/Arch/LayeringTest.php`

- [ ] **Step 1: PSR-4-Namespace registrieren**

In `composer.json` unter `autoload.psr-4` ergänzen:
```json
"App\\Domains\\": "app/Domains/"
```
Dann:
```bash
composer dump-autoload
mkdir -p app/Domains/Identity app/Domains/Masterdata app/Support
```

- [ ] **Step 2: Arch-Test schreiben (failing)**

`tests/Arch/LayeringTest.php`:
```php
<?php

arch('Domänen hängen nicht von Http ab')
    ->expect('App\Domains')
    ->not->toUse('App\Http');

arch('Actions sind invokable oder haben handle()')
    ->expect('App\Domains\Masterdata\Actions')
    ->toHaveMethod('handle');

arch('keine debug-Funktionen')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
```

- [ ] **Step 3: Test laufen lassen**

Run: `./vendor/bin/pest tests/Arch/LayeringTest.php`
Expected: Der `toHaveMethod('handle')`-Test ist leer/grün (noch keine Actions), die anderen grün. Falls „no classes found" für Actions: akzeptabel bis Task 9, dann erneut grün.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "chore: app/Domains PSR-4 + layering arch tests"
```

---

## Task 3: Tenant-Modell + Migration

**Files:**
- Create: `app/Domains/Identity/Models/Tenant.php`, `database/migrations/xxxx_create_tenants_table.php`
- Test: `tests/Feature/Identity/TenantTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Identity/TenantTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;

it('legt einen Tenant mit slug an', function () {
    $tenant = Tenant::create(['name' => 'Haus Sonnenschein', 'slug' => 'haus-sonnenschein']);

    expect($tenant->name)->toBe('Haus Sonnenschein')
        ->and($tenant->slug)->toBe('haus-sonnenschein')
        ->and(Tenant::count())->toBe(1);
});
```

- [ ] **Step 2: Test schlägt fehl**

Run: `./vendor/bin/pest tests/Feature/Identity/TenantTest.php`
Expected: FAIL — Class "App\Domains\Identity\Models\Tenant" not found.

- [ ] **Step 3: Migration erstellen**

`database/migrations/2026_06_04_000001_create_tenants_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```

- [ ] **Step 4: Model erstellen**

`app/Domains/Identity/Models/Tenant.php`:
```php
<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Identity/TenantTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(identity): tenant model + migration"
```

---

## Task 4: Mandanten-Scope (BelongsToTenant + TenantScope + CurrentTenant)

**Files:**
- Create: `app/Domains/Identity/Support/CurrentTenant.php`, `app/Domains/Identity/Scopes/TenantScope.php`, `app/Domains/Identity/Concerns/BelongsToTenant.php`
- Test: `tests/Feature/Identity/TenantScopeTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Identity/TenantScopeTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;

it('filtert Queries automatisch nach aktivem Tenant', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    Building::create(['name' => 'Gebäude A']);

    app(CurrentTenant::class)->set($b);
    Building::create(['name' => 'Gebäude B']);

    expect(Building::count())->toBe(1)
        ->and(Building::first()->name)->toBe('Gebäude B')
        ->and(Building::first()->tenant_id)->toBe($b->id);
});
```
> Hinweis: `Building` wird in Task 8 erstellt; dieser Test wird erst dort grün. Bis dahin als „pending" markieren oder Task-Reihenfolge beachten.

- [ ] **Step 2: CurrentTenant (Request-/Job-Kontext-Halter)**

`app/Domains/Identity/Support/CurrentTenant.php`:
```php
<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Tenant;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }
}
```
Registrierung als Singleton in `app/Providers/AppServiceProvider.php` (Methode `register`):
```php
$this->app->singleton(\App\Domains\Identity\Support\CurrentTenant::class);
```

- [ ] **Step 3: Global Scope**

`app/Domains/Identity/Scopes/TenantScope.php`:
```php
<?php

namespace App\Domains\Identity\Scopes;

use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
```

- [ ] **Step 4: Trait**

`app/Domains/Identity/Concerns/BelongsToTenant.php`:
```php
<?php

namespace App\Domains\Identity\Concerns;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Scopes\TenantScope;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

- [ ] **Step 5: Commit (Test bleibt bis Task 8 rot/pending)**

```bash
git add -A && git commit -m "feat(identity): tenant scope, trait, current-tenant holder"
```

---

## Task 5: Identity — User um tenant_id + Rollen erweitern

**Files:**
- Modify: `app/Models/User.php` → verschieben nach `app/Domains/Identity/Models/User.php`
- Create: `database/migrations/xxxx_add_tenant_id_to_users_table.php`
- Modify: `config/auth.php` (Model-Pfad), `database/migrations/0001_01_01_000000_create_users_table.php` (nur falls nötig)
- Test: `tests/Feature/Identity/UserTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Identity/UserTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

it('verknüpft einen User mit einem Tenant', function () {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($user->tenant->is($tenant))->toBeTrue();
});
```

- [ ] **Step 2: User-Model verschieben**

Datei `app/Models/User.php` nach `app/Domains/Identity/Models/User.php` verschieben, Namespace auf `App\Domains\Identity\Models` ändern, Traits ergänzen:
```php
<?php

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password', 'tenant_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```
> User bewusst OHNE globalen TenantScope (Login muss tenant-übergreifend Mailadressen finden); tenant_id bleibt Pflichtfeld.

- [ ] **Step 3: Verweise aktualisieren**

In `config/auth.php`: `'model' => App\Domains\Identity\Models\User::class`.
In `database/factories/UserFactory.php`: `protected $model = \App\Domains\Identity\Models\User::class;` und Namespace-Import anpassen. Alte `app/Models/User.php` löschen.

- [ ] **Step 4: Migration tenant_id**

`database/migrations/2026_06_04_000002_add_tenant_id_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Identity/UserTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(identity): user in domain, tenant_id + HasRoles"
```

---

## Task 6: RBAC — Rollen & Rechte seeden

**Files:**
- Create: `app/Domains/Identity/Database/seeders/RolesSeeder.php`
- Test: `tests/Feature/Identity/RolesTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Identity/RolesTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use Spatie\Permission\Models\Role;

it('seedet Pflege-Rollen', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::pluck('name')->all())
        ->toContain('admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht');
});
```

- [ ] **Step 2: Seeder**

`app/Domains/Identity/Database/seeders/RolesSeeder.php`:
```php
<?php

namespace App\Domains\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $role) {
            Role::findOrCreate($role);
        }
    }
}
```

- [ ] **Step 3: Test grün**

Run: `./vendor/bin/pest tests/Feature/Identity/RolesTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(identity): seed care roles"
```

---

## Task 7: Audit — Basismodell mit Activitylog + Tenant

**Files:**
- Create: `app/Support/Models/BaseModel.php`
- Test: `tests/Feature/Identity/AuditTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Identity/AuditTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use Spatie\Activitylog\Models\Activity;

it('protokolliert Änderungen an Domänen-Modellen', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $b = Building::create(['name' => 'Gebäude A']);
    $b->update(['name' => 'Gebäude A2']);

    expect(Activity::where('subject_type', Building::class)->count())->toBeGreaterThanOrEqual(2);
});
```
> Wird mit Task 8 grün (Building existiert dort).

- [ ] **Step 2: BaseModel**

`app/Support/Models/BaseModel.php`:
```php
<?php

namespace App\Support\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class BaseModel extends Model
{
    use BelongsToTenant, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

- [ ] **Step 3: Commit (Test pending bis Task 8)**

```bash
git add -A && git commit -m "feat(support): base model with tenant scope + activity log"
```

---

## Task 8: Gebäude-Hierarchie — Migrationen, Modelle, Factories

**Files:**
- Create: Migrationen `buildings`, `floors`, `stations`, `rooms`; Modelle gleicher Namen; Factories
- Test: `tests/Feature/Masterdata/BuildingHierarchyTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/BuildingHierarchyTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Building, Floor, Station, Room};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('bildet die Hierarchie Gebäude→Etage→Station→Zimmer ab', function () {
    $building = Building::create(['name' => 'Haupthaus']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich 1']);
    $room = Room::create(['station_id' => $station->id, 'nummer' => '101', 'betten' => 2]);

    expect($room->station->floor->building->is($building))->toBeTrue()
        ->and($building->floors)->toHaveCount(1)
        ->and($room->betten)->toBe(2);
});
```

- [ ] **Step 2: Test schlägt fehl**

Run: `./vendor/bin/pest tests/Feature/Masterdata/BuildingHierarchyTest.php`
Expected: FAIL — Models nicht gefunden.

- [ ] **Step 3: Migrationen**

`database/migrations/2026_06_04_000010_create_buildings_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index('tenant_id');
        });
    }
    public function down(): void { Schema::dropIfExists('buildings'); }
};
```

`...000011_create_floors_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('floors'); }
};
```

`...000012_create_stations_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stations'); }
};
```

`...000013_create_rooms_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('nummer');
            $table->smallInteger('betten')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rooms'); }
};
```

- [ ] **Step 4: Modelle**

`app/Domains/Masterdata/Models/Building.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends BaseModel
{
    protected $fillable = ['tenant_id', 'name'];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }
}
```

`Floor.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Floor extends BaseModel
{
    protected $fillable = ['tenant_id', 'building_id', 'name'];

    public function building(): BelongsTo { return $this->belongsTo(Building::class); }
    public function stations(): HasMany { return $this->hasMany(Station::class); }
}
```

`Station.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Station extends BaseModel
{
    protected $fillable = ['tenant_id', 'floor_id', 'name'];

    public function floor(): BelongsTo { return $this->belongsTo(Floor::class); }
    public function rooms(): HasMany { return $this->hasMany(Room::class); }
}
```

`Room.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Room extends BaseModel
{
    protected $fillable = ['tenant_id', 'station_id', 'nummer', 'betten'];
    protected $casts = ['betten' => 'integer'];

    public function station(): BelongsTo { return $this->belongsTo(Station::class); }
    public function residents(): HasMany { return $this->hasMany(Resident::class); }
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/BuildingHierarchyTest.php tests/Feature/Identity/TenantScopeTest.php tests/Feature/Identity/AuditTest.php
```
Expected: alle PASS (auch die in Task 4 & 7 vorbereiteten Tests).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(masterdata): building→floor→station→room hierarchy"
```

---

## Task 9: Gebäude-CRUD — Action + DTO + Policy

**Files:**
- Create: `app/Domains/Masterdata/Data/BuildingData.php`, `app/Domains/Masterdata/Actions/CreateBuilding.php`, `app/Domains/Masterdata/Policies/BuildingPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (Policy-Registrierung optional via auto-discovery)
- Test: `tests/Feature/Masterdata/CreateBuildingTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/CreateBuildingTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Actions\CreateBuilding;
use App\Domains\Masterdata\Data\BuildingData;
use App\Domains\Masterdata\Models\Building;

it('erstellt ein Gebäude über die Action', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $building = app(CreateBuilding::class)->handle(new BuildingData(name: 'Neubau'));

    expect($building)->toBeInstanceOf(Building::class)
        ->and($building->name)->toBe('Neubau')
        ->and($building->tenant_id)->toBe($t->id);
});
```

- [ ] **Step 2: DTO**

`app/Domains/Masterdata/Data/BuildingData.php`:
```php
<?php

namespace App\Domains\Masterdata\Data;

use Spatie\LaravelData\Data;

class BuildingData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
```

- [ ] **Step 3: Action**

`app/Domains/Masterdata/Actions/CreateBuilding.php`:
```php
<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\BuildingData;
use App\Domains\Masterdata\Models\Building;

class CreateBuilding
{
    public function handle(BuildingData $data): Building
    {
        return Building::create(['name' => $data->name]);
    }
}
```

- [ ] **Step 4: Policy**

`app/Domains/Masterdata/Policies/BuildingPolicy.php`:
```php
<?php

namespace App\Domains\Masterdata\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Building;

class BuildingPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']); }
    public function create(User $user): bool { return $user->hasRole('admin'); }
    public function update(User $user, Building $building): bool { return $user->hasRole('admin'); }
    public function delete(User $user, Building $building): bool { return $user->hasRole('admin'); }
}
```
> Laravel 12 entdeckt Policies automatisch, wenn Model und Policy denselben Namen teilen und Policy unter dem konventionellen Namespace liegt; hier liegt sie im Domain-Namespace, daher explizit registrieren. In `app/Providers/AppServiceProvider.php` (`boot`):
```php
use Illuminate\Support\Facades\Gate;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Policies\BuildingPolicy;

Gate::policy(Building::class, BuildingPolicy::class);
```

- [ ] **Step 5: Test grün**

Run: `./vendor/bin/pest tests/Feature/Masterdata/CreateBuildingTest.php`
Expected: PASS. Danach auch Arch-Test erneut: `./vendor/bin/pest tests/Arch/LayeringTest.php` → PASS (Action mit `handle`).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(masterdata): create-building action, data, policy"
```

---

## Task 10: Resident — Migration, Model, Factory

**Files:**
- Create: `database/migrations/xxxx_create_residents_table.php`, `app/Domains/Masterdata/Models/Resident.php`, `app/Domains/Masterdata/Database/factories/ResidentFactory.php`
- Test: `tests/Feature/Masterdata/ResidentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/ResidentTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Resident, Building, Floor, Station, Room};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen Bewohner an und ordnet ihn einem Zimmer zu', function () {
    $b = Building::create(['name' => 'H']);
    $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
    $s = Station::create(['floor_id' => $f->id, 'name' => 'WB1']);
    $room = Room::create(['station_id' => $s->id, 'nummer' => '101']);

    $resident = Resident::create([
        'room_id' => $room->id,
        'name' => 'Erika Mustermann',
        'geburtsdatum' => '1940-05-01',
        'geschlecht' => 'w',
        'pflegegrad' => 3,
        'aufnahme_am' => '2026-01-15',
        'status' => 'aktiv',
    ]);

    expect($resident->room->is($room))->toBeTrue()
        ->and($resident->pflegegrad)->toBe(3)
        ->and($resident->geburtsdatum->format('Y-m-d'))->toBe('1940-05-01');
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000020_create_residents_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('geburtsdatum');
            $table->string('geschlecht', 1);            // m/w/d
            $table->smallInteger('pflegegrad')->nullable();
            $table->date('aufnahme_am');
            $table->date('entlassung_am')->nullable();
            $table->string('status')->default('aktiv'); // aktiv/abwesend/entlassen
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('residents'); }
};
```

- [ ] **Step 3: Model**

`app/Domains/Masterdata/Models/Resident.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Resident extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'room_id', 'name', 'geburtsdatum', 'geschlecht',
        'pflegegrad', 'aufnahme_am', 'entlassung_am', 'status',
    ];

    protected $casts = [
        'geburtsdatum' => 'date',
        'aufnahme_am' => 'date',
        'entlassung_am' => 'date',
        'pflegegrad' => 'integer',
    ];

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function diagnoses(): HasMany { return $this->hasMany(ResidentDiagnosis::class); }
    public function insurances(): HasMany { return $this->hasMany(ResidentInsurance::class); }
    public function custodians(): HasMany { return $this->hasMany(Custodian::class); }
}
```

- [ ] **Step 4: Factory**

`app/Domains/Masterdata/Database/factories/ResidentFactory.php`:
```php
<?php

namespace App\Domains\Masterdata\Database\Factories;

use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'geburtsdatum' => $this->faker->dateTimeBetween('-100 years', '-65 years')->format('Y-m-d'),
            'geschlecht' => $this->faker->randomElement(['m', 'w', 'd']),
            'pflegegrad' => $this->faker->numberBetween(1, 5),
            'aufnahme_am' => now()->subDays($this->faker->numberBetween(1, 1000))->format('Y-m-d'),
            'status' => 'aktiv',
        ];
    }
}
```
Im Model `Resident` die Factory verdrahten:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
// in der Klasse:
use HasFactory;

protected static function newFactory(): \App\Domains\Masterdata\Database\Factories\ResidentFactory
{
    return \App\Domains\Masterdata\Database\Factories\ResidentFactory::new();
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/ResidentTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(masterdata): resident model, migration, factory"
```

---

## Task 11: Resident-CRUD — Action + DTO + Policy

**Files:**
- Create: `app/Domains/Masterdata/Data/ResidentData.php`, `app/Domains/Masterdata/Actions/CreateResident.php`, `app/Domains/Masterdata/Actions/UpdateResident.php`, `app/Domains/Masterdata/Policies/ResidentPolicy.php`
- Test: `tests/Feature/Masterdata/CreateResidentTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/CreateResidentTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Actions\{CreateResident, UpdateResident};
use App\Domains\Masterdata\Data\ResidentData;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt und aktualisiert einen Bewohner über Actions', function () {
    $data = new ResidentData(
        name: 'Hans Beispiel',
        geburtsdatum: '1938-03-12',
        geschlecht: 'm',
        aufnahme_am: '2026-02-01',
        pflegegrad: 2,
        status: 'aktiv',
        room_id: null,
    );

    $resident = app(CreateResident::class)->handle($data);
    expect($resident->name)->toBe('Hans Beispiel')->and($resident->pflegegrad)->toBe(2);

    $updated = app(UpdateResident::class)->handle($resident, new ResidentData(
        name: 'Hans Beispiel',
        geburtsdatum: '1938-03-12',
        geschlecht: 'm',
        aufnahme_am: '2026-02-01',
        pflegegrad: 3,
        status: 'aktiv',
        room_id: null,
    ));
    expect($updated->fresh()->pflegegrad)->toBe(3);
});
```

- [ ] **Step 2: DTO**

`app/Domains/Masterdata/Data/ResidentData.php`:
```php
<?php

namespace App\Domains\Masterdata\Data;

use Spatie\LaravelData\Data;

class ResidentData extends Data
{
    public function __construct(
        public string $name,
        public string $geburtsdatum,
        public string $geschlecht,
        public string $aufnahme_am,
        public ?int $pflegegrad = null,
        public string $status = 'aktiv',
        public ?int $room_id = null,
        public ?string $entlassung_am = null,
    ) {}
}
```

- [ ] **Step 3: Actions**

`app/Domains/Masterdata/Actions/CreateResident.php`:
```php
<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\ResidentData;
use App\Domains\Masterdata\Models\Resident;

class CreateResident
{
    public function handle(ResidentData $data): Resident
    {
        return Resident::create($data->toArray());
    }
}
```

`app/Domains/Masterdata/Actions/UpdateResident.php`:
```php
<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\ResidentData;
use App\Domains\Masterdata\Models\Resident;

class UpdateResident
{
    public function handle(Resident $resident, ResidentData $data): Resident
    {
        $resident->update($data->toArray());

        return $resident;
    }
}
```

- [ ] **Step 4: Policy + Registrierung**

`app/Domains/Masterdata/Policies/ResidentPolicy.php`:
```php
<?php

namespace App\Domains\Masterdata\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;

class ResidentPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']); }
    public function view(User $user, Resident $resident): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft']); }
    public function update(User $user, Resident $resident): bool { return $user->hasAnyRole(['admin', 'pflegefachkraft']); }
    public function delete(User $user, Resident $resident): bool { return $user->hasRole('admin'); }
}
```
In `app/Providers/AppServiceProvider.php` (`boot`):
```php
Gate::policy(\App\Domains\Masterdata\Models\Resident::class, \App\Domains\Masterdata\Policies\ResidentPolicy::class);
```

- [ ] **Step 5: Test grün**

Run: `./vendor/bin/pest tests/Feature/Masterdata/CreateResidentTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(masterdata): resident create/update actions + policy"
```

---

## Task 12: ICD-Codes + Diagnosen

**Files:**
- Create: Migrationen `icd_codes`, `resident_diagnoses`; Modelle `IcdCode`, `ResidentDiagnosis`
- Test: `tests/Feature/Masterdata/DiagnosisTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/DiagnosisTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{IcdCode, Resident, ResidentDiagnosis};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('verknüpft Bewohner mit ICD-Diagnose', function () {
    $icd = IcdCode::create(['code' => 'F00.0', 'bezeichnung' => 'Demenz bei Alzheimer-Krankheit']);
    $resident = Resident::factory()->create();

    $diag = ResidentDiagnosis::create([
        'resident_id' => $resident->id,
        'icd_code_id' => $icd->id,
        'art' => 'primär',
    ]);

    expect($resident->diagnoses)->toHaveCount(1)
        ->and($diag->icdCode->code)->toBe('F00.0');
});
```

- [ ] **Step 2: Migrationen**

`...000030_create_icd_codes_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('icd_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('bezeichnung');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('icd_codes'); }
};
```
> ICD-Katalog ist tenant-übergreifend (Referenzdaten) — KEIN tenant_id.

`...000031_create_resident_diagnoses_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('resident_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icd_code_id')->constrained()->restrictOnDelete();
            $table->string('art')->default('sekundär'); // primär/sekundär
            $table->date('diagnostiziert_am')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('resident_diagnoses'); }
};
```

- [ ] **Step 3: Modelle**

`app/Domains/Masterdata/Models/IcdCode.php` (Referenzdaten, kein BaseModel/Tenant):
```php
<?php

namespace App\Domains\Masterdata\Models;

use Illuminate\Database\Eloquent\Model;

class IcdCode extends Model
{
    protected $fillable = ['code', 'bezeichnung'];
}
```

`app/Domains/Masterdata/Models/ResidentDiagnosis.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentDiagnosis extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'icd_code_id', 'art', 'diagnostiziert_am'];
    protected $casts = ['diagnostiziert_am' => 'date'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function icdCode(): BelongsTo { return $this->belongsTo(IcdCode::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/DiagnosisTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(masterdata): icd codes + resident diagnoses"
```

---

## Task 13: Krankenkassen + Versicherungsverhältnis

**Files:**
- Create: Migrationen `health_insurances`, `resident_insurance`; Modelle `HealthInsurance`, `ResidentInsurance`
- Test: `tests/Feature/Masterdata/InsuranceTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/InsuranceTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{HealthInsurance, Resident, ResidentInsurance};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('ordnet einem Bewohner eine primäre Kasse zu', function () {
    $kasse = HealthInsurance::create(['name' => 'AOK', 'ik_nummer' => '101570104']);
    $resident = Resident::factory()->create();

    $ri = ResidentInsurance::create([
        'resident_id' => $resident->id,
        'health_insurance_id' => $kasse->id,
        'versichertennr' => 'X123',
        'ist_primaer' => true,
    ]);

    expect($resident->insurances)->toHaveCount(1)
        ->and($ri->healthInsurance->name)->toBe('AOK')
        ->and($ri->ist_primaer)->toBeTrue();
});
```

- [ ] **Step 2: Migrationen**

`...000040_create_health_insurances_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('health_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ik_nummer')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'ik_nummer']);
        });
    }
    public function down(): void { Schema::dropIfExists('health_insurances'); }
};
```

`...000041_create_resident_insurance_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('resident_insurance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('health_insurance_id')->constrained()->restrictOnDelete();
            $table->string('versichertennr')->nullable();
            $table->boolean('ist_primaer')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('resident_insurance'); }
};
```

- [ ] **Step 3: Modelle**

`HealthInsurance.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;

class HealthInsurance extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'ik_nummer'];
}
```

`ResidentInsurance.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentInsurance extends BaseModel
{
    protected $table = 'resident_insurance';
    protected $fillable = ['tenant_id', 'resident_id', 'health_insurance_id', 'versichertennr', 'ist_primaer'];
    protected $casts = ['ist_primaer' => 'boolean'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
    public function healthInsurance(): BelongsTo { return $this->belongsTo(HealthInsurance::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/InsuranceTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(masterdata): health insurances + resident insurance"
```

---

## Task 14: Betreuer (Custodians)

**Files:**
- Create: Migration `custodians`, Modell `Custodian`
- Test: `tests/Feature/Masterdata/CustodianTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/CustodianTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Custodian, Resident};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen gesetzlichen Betreuer für einen Bewohner an', function () {
    $resident = Resident::factory()->create();
    $c = Custodian::create([
        'resident_id' => $resident->id,
        'name' => 'RA Schmidt',
        'umfang' => 'Gesundheitsfürsorge',
        'kontakt' => 'schmidt@kanzlei.de',
    ]);

    expect($resident->custodians)->toHaveCount(1)
        ->and($c->name)->toBe('RA Schmidt');
});
```

- [ ] **Step 2: Migration**

`...000050_create_custodians_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('custodians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('umfang')->nullable();
            $table->string('kontakt')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('custodians'); }
};
```

- [ ] **Step 3: Modell**

`app/Domains/Masterdata/Models/Custodian.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Custodian extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'name', 'umfang', 'kontakt'];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/CustodianTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(masterdata): custodians"
```

---

## Task 15: Ärzte (Physicians) + Bewohner-Pivot

**Files:**
- Create: Migrationen `physicians`, `resident_physician`; Modell `Physician`; Pivot-Relation in `Resident`
- Test: `tests/Feature/Masterdata/PhysicianTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/PhysicianTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Physician, Resident};

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('verknüpft Bewohner und Arzt über Pivot', function () {
    $resident = Resident::factory()->create();
    $arzt = Physician::create(['name' => 'Dr. Meier', 'fachrichtung' => 'Allgemeinmedizin']);

    $resident->physicians()->attach($arzt);

    expect($resident->physicians)->toHaveCount(1)
        ->and($resident->physicians->first()->name)->toBe('Dr. Meier');
});
```

- [ ] **Step 2: Migrationen**

`...000060_create_physicians_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('physicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('fachrichtung')->nullable();
            $table->string('kontakt')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('physicians'); }
};
```

`...000061_create_resident_physician_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('resident_physician', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('physician_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resident_id', 'physician_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('resident_physician'); }
};
```

- [ ] **Step 3: Modell + Relation**

`app/Domains/Masterdata/Models/Physician.php`:
```php
<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Physician extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'fachrichtung', 'kontakt'];

    public function residents(): BelongsToMany { return $this->belongsToMany(Resident::class); }
}
```
In `Resident.php` ergänzen:
```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function physicians(): BelongsToMany
{
    return $this->belongsToMany(Physician::class);
}
```

- [ ] **Step 4: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Masterdata/PhysicianTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(masterdata): physicians + resident pivot"
```

---

## Task 16: Bewohner-Dateien (Media Library)

**Files:**
- Modify: `app/Domains/Masterdata/Models/Resident.php` (InteractsWithMedia)
- Test: `tests/Feature/Masterdata/ResidentFilesTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Masterdata/ResidentFilesTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('media');
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('hängt eine Datei an einen Bewohner', function () {
    $resident = Resident::factory()->create();
    $resident->addMedia(UploadedFile::fake()->create('befund.pdf', 10))
        ->toMediaCollection('documents');

    expect($resident->getMedia('documents'))->toHaveCount(1);
});
```

- [ ] **Step 2: Resident um Media erweitern**

In `app/Domains/Masterdata/Models/Resident.php` Klassen-Signatur und Trait ergänzen:
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Resident extends BaseModel implements HasMedia
{
    use InteractsWithMedia;
    // ... bestehender Code ...

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }
}
```

- [ ] **Step 3: Test grün**

Run: `./vendor/bin/pest tests/Feature/Masterdata/ResidentFilesTest.php`
Expected: PASS.

- [ ] **Step 4: Gesamte Suite + Arch grün**

Run: `./vendor/bin/pest`
Expected: ALLE PASS (inkl. Arch-Tests).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(masterdata): resident document attachments via medialibrary"
```

---

## Task 17: Demo-Seeder + Abschluss

**Files:**
- Create: `app/Domains/Identity/Database/seeders/DemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Demo-Seeder**

`app/Domains/Identity/Database/seeders/DemoSeeder.php`:
```php
<?php

namespace App\Domains\Identity\Database\Seeders;

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\{Building, Floor, Station, Room, Resident};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create(['name' => 'Haus Sonnenschein', 'slug' => 'haus-sonnenschein']);
        app(CurrentTenant::class)->set($tenant);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@opcare.local',
            'password' => Hash::make('password'), 'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole('admin');

        $b = Building::create(['name' => 'Haupthaus']);
        $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
        $s = Station::create(['floor_id' => $f->id, 'name' => 'Wohnbereich 1']);
        $room = Room::create(['station_id' => $s->id, 'nummer' => '101', 'betten' => 2]);

        Resident::factory()->count(5)->create(['room_id' => $room->id]);
    }
}
```

- [ ] **Step 2: DatabaseSeeder verdrahten**

`database/seeders/DatabaseSeeder.php` (`run`):
```php
$this->call([
    \App\Domains\Identity\Database\Seeders\RolesSeeder::class,
    \App\Domains\Identity\Database\Seeders\DemoSeeder::class,
]);
```

- [ ] **Step 3: Frisch migrieren + seeden**

Run:
```bash
php artisan migrate:fresh --seed
```
Expected: Alle Migrationen + Seeder laufen ohne Fehler.

- [ ] **Step 4: Gesamte Test-Suite**

Run: `./vendor/bin/pest`
Expected: ALLE PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: demo seeder (tenant, admin, hierarchy, residents)"
```

---

## Self-Review-Ergebnis (Plan 1)

- **Spec-Abdeckung:** Identity/Tenant (§3,§6) → Tasks 3–7. Masterdata-Tabellen aus README-ER → Tasks 8,10,12,13,14,15,16. RBAC (§6) → Task 6. Audit/append-only-Fundament (§4,§6) → Task 7 (Activitylog; vollständige Versionierung der SIS/Berichte folgt in Plan 2, wo die append-only-Tabellen entstehen). Layering (§3) → Task 2. CarePlanning & Speech sind bewusst **Plan 2 & 3**.
- **Platzhalter:** keine — jeder Code-Schritt vollständig.
- **Typ-Konsistenz:** `CurrentTenant::set/get/id`, `BelongsToTenant`, `BaseModel`, `handle()`-Actions, DTO-Feldnamen durchgängig identisch.

## Offene Folge-Pläne
- **Plan 2:** CarePlanning (SIS®) — `docs/superpowers/plans/…-opcare-careplanning.md`
- **Plan 3:** Speech-Workflow — `docs/superpowers/plans/…-opcare-speech.md`
