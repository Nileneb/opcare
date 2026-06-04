# OPCare — Plan 4: Mehrmandanten-Betrieb + Admin-UI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aus dem vorbereiteten Single-Home-Fundament einen echten Mehrmandanten-Betrieb machen — mehrere Heime in **einer** Datenbank sauber row-level isoliert (`tenant_id` + globaler Scope, gehärtet und getestet), plus eine eigene **Admin-UI** (Livewire, kein Filament), in der Einrichtungen, Benutzer und Rollen verwaltet werden. Super-Admins können mandantenübergreifend arbeiten und den aktiven Mandanten umschalten.

**Architecture:** Hybrid-Tenancy (Entscheidung 2026-06-04): **jetzt** row-level (`tenant_id` in jeder Mandanten-Tabelle, `BelongsToTenant`/`TenantScope` aus Plan 1), DB-per-Tenant bleibt als späterer Pfad offen — die einzige Nahtstelle dafür ist der neue `TenantResolver`. Rollen werden über **spatie/laravel-permission „teams"** je Mandant isoliert (`team_foreign_key = tenant_id`). Ein `super-admin` (Gate::before-Bypass) sieht alles. Die Admin-UI sind normale Livewire-Komponenten unter `App\Livewire\Admin` im bestehenden `layouts.app`-Shell, hinter einer `role:admin|super-admin`-Gate.

**Tech Stack:** wie Plan 1–3 (Laravel 13, PHP 8.4, Livewire 4, spatie/laravel-permission v8 **mit Teams**, Pest 3 + Arch).

**Voraussetzung:** Plan 1–3 implementiert. `CurrentTenant`, `TenantScope`, `BelongsToTenant`, `SetCurrentTenant`-Middleware, `RolesSeeder`, `layouts.app`-Nav, `admin.css` existieren.

**Referenz:** OPDE-Domänenkarte (Homes → Station → Resident; OPGroups/SYSGROUPS2ACL → spatie-Rollen). Spec `docs/superpowers/specs/2026-06-04-pflegeplanung-laravel-design.md` §3/§6.

---

## File Structure (Plan 4)

```
app/
├── Domains/Identity/
│   ├── Models/Tenant.php                 # erweitern: aktiv, settings, BelongsToMany users
│   ├── Support/TenantResolver.php        # NEU — einzige Nahtstelle für Hybrid-Tenancy
│   ├── Actions/{CreateTenant, UpdateTenant, CreateUser, UpdateUser, AssignRole}.php
│   ├── Data/{TenantData, AdminUserData}.php
│   └── Policies/{TenantPolicy, UserPolicy}.php
├── Http/Middleware/SetCurrentTenant.php  # erweitern: Super-Admin Tenant-Switch via Session
└── Livewire/Admin/
    ├── Tenants.php       + resources/views/livewire/admin/tenants.blade.php
    ├── Users.php         + resources/views/livewire/admin/users.blade.php
    └── TenantSwitcher.php (+ view)        # Super-Admin: aktives Heim umschalten
config/permission.php                       # teams => true
database/migrations/
    ├── ..._add_active_settings_to_tenants_table.php
    └── (spatie) add team_id columns        # via vendor:publish neu generiert
tests/Feature/Tenancy/...
tests/Arch/TenancyTest.php
docs/superpowers/specs/2026-06-04-hybrid-tenancy-migrationspfad.md  # DB-per-Tenant später
```

---

## Task 1: spatie-Teams aktivieren (Rollen je Mandant)

**Files:**
- Modify: `config/permission.php` (`'teams' => true`, `'team_foreign_key' => 'tenant_id'`)
- Create: Migration, die `tenant_id` zu den spatie-Pivot-Tabellen ergänzt
- Modify: `app/Providers/AppServiceProvider.php` (Team-ID-Resolver an spatie binden)
- Test: `tests/Feature/Tenancy/TeamRolesTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/TeamRolesTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('isoliert Rollen je Mandant (teams)', function () {
    $a = Tenant::create(['name' => 'Haus A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'Haus B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    app(PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    $user = User::factory()->create(['tenant_id' => $a->id]);
    $user->assignRole('pflegefachkraft');
    expect($user->hasRole('pflegefachkraft'))->toBeTrue();

    // Im Kontext von Haus B hat derselbe User die Rolle NICHT.
    app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('pflegefachkraft'))->toBeFalse();
});
```

- [ ] **Step 2: Teams in der Permission-Config einschalten**

`config/permission.php`: `'teams' => true` und `'team_foreign_key' => 'tenant_id'` setzen (Schlüssel existieren bereits, nur Werte ändern).

- [ ] **Step 3: spatie-Pivot-Migration neu publishen / `tenant_id` ergänzen**

Da die Permission-Tabellen schon migriert sind, eine additive Migration `..._add_tenant_id_to_permission_tables.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        foreach (['model_has_roles', 'model_has_permissions'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
        });
    }
    public function down(): void {
        foreach (['model_has_roles', 'model_has_permissions'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('tenant_id'));
        }
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('tenant_id'));
    }
};
```
> Hinweis: Falls die Test-DB via `migrate:fresh` läuft, kann alternativ die publizierte spatie-Migration angepasst werden. Diese additive Variante ist idempotent für bestehende DBs.

- [ ] **Step 4: Team-ID an den aktiven Tenant koppeln**

In `app/Domains/Identity/Support/CurrentTenant.php` die `set()`-Methode erweitern, sodass spatie denselben Kontext kennt:
```php
public function set(Tenant $tenant): void
{
    $this->tenant = $tenant;
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Tenancy/TeamRolesTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(tenancy): per-tenant roles via spatie teams"
```

---

## Task 2: Tenant-Modell erweitern (aktiv, settings, Nutzer)

**Files:**
- Create: `database/migrations/..._add_active_settings_to_tenants_table.php`
- Modify: `app/Domains/Identity/Models/Tenant.php`
- Test: `tests/Feature/Tenancy/TenantModelTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/TenantModelTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

it('hält Einrichtungs-Stammdaten und Nutzer', function () {
    $t = Tenant::create([
        'name' => 'Haus Aprath', 'slug' => 'aprath',
        'traeger' => 'Bergische Diakonie', 'ik_nummer' => '260123456',
        'settings' => ['stichtag_quartal' => 1], 'aktiv' => true,
    ]);
    User::factory()->create(['tenant_id' => $t->id]);

    expect($t->aktiv)->toBeTrue()
        ->and($t->settings['stichtag_quartal'])->toBe(1)
        ->and($t->users)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

`database/migrations/2026_06_04_000300_add_active_settings_to_tenants_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('traeger')->nullable()->after('name');
            $table->string('ik_nummer')->nullable()->after('slug');   // Einrichtungs-IK (QDVS)
            $table->jsonb('settings')->nullable();
            $table->boolean('aktiv')->default(true);
        });
    }
    public function down(): void {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['traeger', 'ik_nummer', 'settings', 'aktiv']);
        });
    }
};
```

- [ ] **Step 3: Modell erweitern**

`app/Domains/Identity/Models/Tenant.php`:
```php
protected $fillable = ['name', 'traeger', 'slug', 'ik_nummer', 'settings', 'aktiv'];
protected $casts = ['settings' => 'array', 'aktiv' => 'boolean'];

public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(User::class);
}

public function scopeAktiv($q) { return $q->where('aktiv', true); }
```

- [ ] **Step 4: Migrieren + Test grün**

Run: `php artisan migrate && ./vendor/bin/pest tests/Feature/Tenancy/TenantModelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(tenancy): tenant master data (träger, ik, settings, aktiv)"
```

---

## Task 3: TenantResolver + Super-Admin-Switch (Hybrid-Naht)

**Files:**
- Create: `app/Domains/Identity/Support/TenantResolver.php`
- Modify: `app/Http/Middleware/SetCurrentTenant.php`
- Create: `app/Domains/Identity/Database/Seeders/SuperAdminRoleSeeder.php`
- Modify: `app/Providers/AppServiceProvider.php` (Gate::before für super-admin)
- Test: `tests/Feature/Tenancy/TenantResolverTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/TenantResolverTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\TenantResolver;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('löst für normale Nutzer den eigenen Tenant auf', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $u = User::factory()->create(['tenant_id' => $t->id]);

    expect(app(TenantResolver::class)->resolveFor($u, sessionTenantId: null)->id)->toBe($t->id);
});

it('lässt Super-Admins per Session zwischen Tenants wechseln', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $u = User::factory()->create(['tenant_id' => $a->id]);
    $this->seed(\App\Domains\Identity\Database\Seeders\SuperAdminRoleSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    $u->assignRole('super-admin');

    expect(app(TenantResolver::class)->resolveFor($u, sessionTenantId: $b->id)->id)->toBe($b->id);
});
```

- [ ] **Step 2: TenantResolver**

`app/Domains/Identity/Support/TenantResolver.php`:
```php
<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

// WHY(hybrid-tenancy): einzige Stelle, die "welcher Mandant gilt" entscheidet.
// Bei späterem DB-per-Tenant wird NUR diese Klasse ausgetauscht (z. B. Subdomain/DB-Switch).
class TenantResolver
{
    public function resolveFor(User $user, ?int $sessionTenantId): ?Tenant
    {
        if ($sessionTenantId && $user->hasRole('super-admin')) {
            return Tenant::find($sessionTenantId) ?? $user->tenant;
        }

        return $user->tenant;
    }
}
```

- [ ] **Step 3: SetCurrentTenant nutzt den Resolver**

`app/Http/Middleware/SetCurrentTenant.php` (`handle`):
```php
$user = $request->user();
if ($user) {
    $tenant = app(\App\Domains\Identity\Support\TenantResolver::class)
        ->resolveFor($user, $request->session()->get('active_tenant_id'));
    if ($tenant) {
        app(\App\Domains\Identity\Support\CurrentTenant::class)->set($tenant);
    }
}
return $next($request);
```

- [ ] **Step 4: super-admin-Rolle + Gate-Bypass**

`app/Domains/Identity/Database/Seeders/SuperAdminRoleSeeder.php`:
```php
<?php
namespace App\Domains\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void { Role::findOrCreate('super-admin'); }
}
```
In `AppServiceProvider::boot()`:
```php
Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
```

- [ ] **Step 5: Test grün + Commit**

Run: `./vendor/bin/pest tests/Feature/Tenancy/TenantResolverTest.php`
```bash
git add -A && git commit -m "feat(tenancy): tenant resolver + super-admin switch (hybrid seam)"
```

---

## Task 4: Cross-Tenant-Leak-Tests (Sicherheits-Gate)

**Files:**
- Create: `tests/Feature/Tenancy/CrossTenantIsolationTest.php`, `tests/Arch/TenancyTest.php`

- [ ] **Step 1: Isolation-Feature-Test (failing falls Scope lückenhaft)**

`tests/Feature/Tenancy/CrossTenantIsolationTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

it('zeigt niemals Bewohner eines fremden Mandanten', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    Resident::factory()->count(3)->create();

    app(CurrentTenant::class)->set($b);
    Resident::factory()->count(2)->create();

    expect(Resident::count())->toBe(2);
    app(CurrentTenant::class)->set($a);
    expect(Resident::count())->toBe(3);
});
```

- [ ] **Step 2: Arch-Test — jede BaseModel-Kindklasse trägt den Scope**

`tests/Arch/TenancyTest.php`:
```php
<?php

arch('Domänen-Modelle erben von BaseModel (Tenant-Scope) oder sind Referenzdaten')
    ->expect('App\Domains\Masterdata\Models')
    ->toExtend('App\Support\Models\BaseModel')
    ->ignoring('App\Domains\Masterdata\Models\IcdCode'); // tenant-übergreifende Referenz
```
> Beim Hinzufügen weiterer Referenz-Tabellen (kein Tenant) hier ergänzen — der Test erzwingt eine bewusste Entscheidung pro Modell.

- [ ] **Step 3: Tests grün + Commit**

Run: `./vendor/bin/pest tests/Feature/Tenancy/CrossTenantIsolationTest.php tests/Arch/TenancyTest.php`
```bash
git add -A && git commit -m "test(tenancy): cross-tenant isolation + arch guard"
```

---

## Task 5: Tenant-CRUD (Action + DTO + Policy)

**Files:**
- Create: `app/Domains/Identity/Data/TenantData.php`, `app/Domains/Identity/Actions/{CreateTenant,UpdateTenant}.php`, `app/Domains/Identity/Policies/TenantPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (Gate::policy)
- Test: `tests/Feature/Tenancy/TenantCrudTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/TenantCrudTest.php`:
```php
<?php

use App\Domains\Identity\Actions\CreateTenant;
use App\Domains\Identity\Data\TenantData;

it('erstellt eine Einrichtung über die Action', function () {
    $tenant = app(CreateTenant::class)->handle(new TenantData(
        name: 'Haus Sonnenhof', slug: 'sonnenhof', traeger: 'Diakonie', ik_nummer: '260999999',
    ));
    expect($tenant->name)->toBe('Haus Sonnenhof')->and($tenant->aktiv)->toBeTrue();
});
```

- [ ] **Step 2: DTO**

`app/Domains/Identity/Data/TenantData.php`:
```php
<?php
namespace App\Domains\Identity\Data;

use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $traeger = null,
        public ?string $ik_nummer = null,
        public bool $aktiv = true,
    ) {}
}
```

- [ ] **Step 3: Actions**

`CreateTenant.php`:
```php
<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;

class CreateTenant
{
    public function handle(TenantData $data): Tenant
    {
        return Tenant::create($data->toArray());
    }
}
```
`UpdateTenant.php`:
```php
<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;

class UpdateTenant
{
    public function handle(Tenant $tenant, TenantData $data): Tenant
    {
        $tenant->update($data->toArray());
        return $tenant;
    }
}
```

- [ ] **Step 4: Policy (nur super-admin verwaltet Einrichtungen)**

`app/Domains/Identity/Policies/TenantPolicy.php`:
```php
<?php
namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\{Tenant, User};

class TenantPolicy
{
    public function viewAny(User $u): bool { return $u->hasRole('super-admin'); }
    public function create(User $u): bool { return $u->hasRole('super-admin'); }
    public function update(User $u, Tenant $t): bool { return $u->hasRole('super-admin'); }
}
```
Registrieren in `AppServiceProvider::boot()`: `Gate::policy(Tenant::class, TenantPolicy::class);`
> Der `Gate::before`-Bypass macht super-admin ohnehin allmächtig; die Policy dokumentiert die Absicht und sperrt normale admins aus.

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Tenancy/TenantCrudTest.php
git add -A && git commit -m "feat(identity): tenant crud action+data+policy"
```

---

## Task 6: User-Verwaltung (Action + DTO + Policy)

**Files:**
- Create: `app/Domains/Identity/Data/AdminUserData.php`, `app/Domains/Identity/Actions/{CreateUser,UpdateUser,AssignRole}.php`, `app/Domains/Identity/Policies/UserPolicy.php`
- Test: `tests/Feature/Tenancy/UserManagementTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/UserManagementTest.php`:
```php
<?php

use App\Domains\Identity\Actions\{CreateUser, AssignRole};
use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('legt einen Mitarbeitenden im aktiven Mandanten an und vergibt eine Rolle', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $user = app(CreateUser::class)->handle(new AdminUserData(
        name: 'Pia Pflege', email: 'pia@opcare.local', password: 'geheim-123', role: 'pflegefachkraft',
    ));

    expect($user->tenant_id)->toBe($t->id)
        ->and($user->hasRole('pflegefachkraft'))->toBeTrue();
});
```

- [ ] **Step 2: DTO**

`app/Domains/Identity/Data/AdminUserData.php`:
```php
<?php
namespace App\Domains\Identity\Data;

use Spatie\LaravelData\Data;

class AdminUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public string $role = 'pflegehilfskraft',
    ) {}
}
```

- [ ] **Step 3: Actions**

`CreateUser.php` (Tenant kommt aus CurrentTenant — `BelongsToTenant` ist auf User NICHT aktiv, daher explizit):
```php
<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    public function handle(AdminUserData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password, // 'hashed'-Cast
                'tenant_id' => app(CurrentTenant::class)->id(),
            ]);
            $user->assignRole($data->role);
            return $user;
        });
    }
}
```
`UpdateUser.php`:
```php
<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;

class UpdateUser
{
    public function handle(User $user, AdminUserData $data): User
    {
        $attrs = ['name' => $data->name, 'email' => $data->email];
        if ($data->password) {
            $attrs['password'] = $data->password;
        }
        $user->update($attrs);
        return $user;
    }
}
```
`AssignRole.php`:
```php
<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

class AssignRole
{
    public function handle(User $user, string $role): User
    {
        $user->syncRoles([$role]); // genau eine Rolle pro Nutzer in v1
        return $user;
    }
}
```

- [ ] **Step 4: Policy**

`app/Domains/Identity/Policies/UserPolicy.php`:
```php
<?php
namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $u): bool { return $u->hasAnyRole(['admin', 'super-admin']); }
    public function create(User $u): bool { return $u->hasAnyRole(['admin', 'super-admin']); }
    public function update(User $u, User $target): bool
    {
        return $u->hasRole('super-admin') || ($u->hasRole('admin') && $u->tenant_id === $target->tenant_id);
    }
}
```
Registrieren: `Gate::policy(User::class, UserPolicy::class);`

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Tenancy/UserManagementTest.php
git add -A && git commit -m "feat(identity): user management actions + policy"
```

---

## Task 7: Admin-UI — Einrichtungen (Livewire)

**Files:**
- Create: `app/Livewire/Admin/Tenants.php`, `resources/views/livewire/admin/tenants.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php` (Verwaltungs-Nav nur für admin/super-admin)
- Test: `tests/Feature/Admin/AdminTenantsTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Admin/AdminTenantsTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\{RolesSeeder, SuperAdminRoleSeeder};
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\Tenants;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminRoleSeeder::class);
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('super-admin');
});

it('listet und erstellt Einrichtungen', function () {
    Livewire::actingAs($this->admin)->test(Tenants::class)
        ->set('name', 'Haus Neu')->set('slug', 'neu')
        ->call('save')->assertHasNoErrors();
    expect(Tenant::where('slug', 'neu')->exists())->toBeTrue();
});

it('verwehrt normalen Nutzern den Zugriff', function () {
    $nurse = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $nurse->assignRole('pflegefachkraft');
    $this->actingAs($nurse)->get('/admin/einrichtungen')->assertForbidden();
});
```

- [ ] **Step 2: Komponente**

`app/Livewire/Admin/Tenants.php`:
```php
<?php
namespace App\Livewire\Admin;

use App\Domains\Identity\Actions\CreateTenant;
use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Tenants extends Component
{
    public string $name = '';
    public string $slug = '';
    public string $traeger = '';
    public string $ik_nummer = '';

    public function mount(): void { $this->authorize('viewAny', Tenant::class); }

    public function save(CreateTenant $create): void
    {
        $this->authorize('create', Tenant::class);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'unique:tenants,slug'],
            'traeger' => ['nullable', 'string'],
            'ik_nummer' => ['nullable', 'string'],
        ]);
        $create->handle(new TenantData(...$data));
        $this->reset('name', 'slug', 'traeger', 'ik_nummer');
        session()->flash('status', 'Einrichtung angelegt.');
    }

    public function render()
    {
        return view('livewire.admin.tenants', ['tenants' => Tenant::orderBy('name')->get()]);
    }
}
```

- [ ] **Step 3: View** (Muster wie `livewire/residents.blade.php`: `.page-head`, `.card`, `table.data`, Formular mit `.field`/`.btn`). `resources/views/livewire/admin/tenants.blade.php`:
```blade
<div>
    <div class="page-head"><div><p class="kicker">Verwaltung</p><h1>Einrichtungen</h1>
        <p class="lead">Heime/Mandanten anlegen und pflegen (nur Super-Admin).</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <div class="card">
        <div class="card-head"><h3>Neue Einrichtung</h3></div>
        <form wire:submit="save">
            <div class="form-row">
                <div class="field"><label>Name</label><input wire:model="name" />@error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Kürzel (slug)</label><input wire:model="slug" />@error('slug')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row">
                <div class="field"><label>Träger</label><input wire:model="traeger" /></div>
                <div class="field"><label>IK-Nummer</label><input wire:model="ik_nummer" /></div>
            </div>
            <button class="btn btn-primary">Anlegen</button>
        </form>
    </div>
    <div class="card"><table class="data"><thead><tr><th>Name</th><th>Träger</th><th>IK</th><th>Status</th></tr></thead>
        <tbody>@foreach ($tenants as $t)<tr><td><b>{{ $t->name }}</b></td><td>{{ $t->traeger }}</td><td>{{ $t->ik_nummer }}</td>
            <td><span class="badge {{ $t->aktiv ? 'green' : 'gray' }}">{{ $t->aktiv ? 'aktiv' : 'inaktiv' }}</span></td></tr>@endforeach</tbody>
    </table></div>
</div>
```

- [ ] **Step 4: Route + Nav**

`routes/web.php` im `['auth','tenant']`-Block:
```php
Route::get('/admin/einrichtungen', \App\Livewire\Admin\Tenants::class)->name('admin.tenants');
```
In `layouts.app` Nav-Array nur für Admins ergänzen:
```php
@if (auth()->user()?->hasAnyRole(['admin', 'super-admin']))
    <a href="{{ route('admin.tenants') }}" @class(['is-active' => request()->routeIs('admin.tenants')])>Einrichtungen</a>
@endif
```

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Admin/AdminTenantsTest.php
git add -A && git commit -m "feat(admin): tenant management ui"
```

---

## Task 8: Admin-UI — Benutzer & Rollen (Livewire)

**Files:**
- Create: `app/Livewire/Admin/Users.php`, `resources/views/livewire/admin/users.blade.php`
- Modify: `routes/web.php`, `layouts.app` (Nav)
- Test: `tests/Feature/Admin/AdminUsersTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Admin/AdminUsersTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\Users;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->t);
    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
});

it('legt Mitarbeitende mit Rolle an', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('name', 'Hans Helfer')->set('email', 'hans@opcare.local')
        ->set('password', 'geheim-1234')->set('role', 'pflegehilfskraft')
        ->call('save')->assertHasNoErrors();

    $u = User::where('email', 'hans@opcare.local')->first();
    expect($u->tenant_id)->toBe($this->t->id)->and($u->hasRole('pflegehilfskraft'))->toBeTrue();
});
```

- [ ] **Step 2: Komponente**

`app/Livewire/Admin/Users.php`:
```php
<?php
namespace App\Livewire\Admin;

use App\Domains\Identity\Actions\{AssignRole, CreateUser};
use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Users extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'pflegehilfskraft';

    public function mount(): void { $this->authorize('viewAny', User::class); }

    public function save(CreateUser $create): void
    {
        $this->authorize('create', User::class);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'],
        ]);
        $create->handle(new AdminUserData(...$data));
        $this->reset('name', 'email', 'password');
        session()->flash('status', 'Mitarbeitende:r angelegt.');
    }

    public function setRole(int $userId, string $role, AssignRole $assign): void
    {
        $this->authorize('update', User::find($userId));
        $assign->handle(User::findOrFail($userId), $role);
        session()->flash('status', 'Rolle aktualisiert.');
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::where('tenant_id', auth()->user()->tenant_id)->with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
```

- [ ] **Step 3: View** — Tabelle (Name, E-Mail, Rolle als `<select wire:change="setRole({{ $u->id }}, $event.target.value)">`) + Anlege-Formular, Muster wie `admin/tenants.blade.php`. Rolle-Spalte:
```blade
<select wire:change="setRole({{ $u->id }}, $event.target.value)">
    @foreach ($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach
</select>
```

- [ ] **Step 4: Route + Nav**

`routes/web.php`: `Route::get('/admin/benutzer', \App\Livewire\Admin\Users::class)->name('admin.users');`
Nav (im Admin-`@if`): Link „Benutzer" → `admin.users`.

- [ ] **Step 5: Test grün + Commit**

```bash
./vendor/bin/pest tests/Feature/Admin/AdminUsersTest.php
git add -A && git commit -m "feat(admin): user & role management ui"
```

---

## Task 9: Tenant-Switcher (Super-Admin) + Demo-Seeder mit 2 Heimen

**Files:**
- Create: `app/Livewire/Admin/TenantSwitcher.php` (+ view, in `layouts.app` eingebunden)
- Modify: `app/Domains/Identity/Database/Seeders/DemoSeeder.php` (zweites Heim + super-admin)
- Modify: `database/seeders/DatabaseSeeder.php` (SuperAdminRoleSeeder aufnehmen)
- Test: `tests/Feature/Tenancy/TenantSwitchTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Tenancy/TenantSwitchTest.php`:
```php
<?php

use App\Domains\Identity\Database\Seeders\{RolesSeeder, SuperAdminRoleSeeder};
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\TenantSwitcher;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminRoleSeeder::class);
    $this->a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $this->b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($this->a);
    $this->admin = User::factory()->create(['tenant_id' => $this->a->id]);
    $this->admin->assignRole('super-admin');
});

it('schaltet den aktiven Mandanten in die Session', function () {
    Livewire::actingAs($this->admin)->test(TenantSwitcher::class)
        ->call('switchTo', $this->b->id);
    expect(session('active_tenant_id'))->toBe($this->b->id);
});
```

- [ ] **Step 2: Komponente**

`app/Livewire/Admin/TenantSwitcher.php`:
```php
<?php
namespace App\Livewire\Admin;

use App\Domains\Identity\Models\Tenant;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public function switchTo(int $tenantId): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        abort_unless(Tenant::whereKey($tenantId)->exists(), 404);
        session(['active_tenant_id' => $tenantId]);
        $this->redirect(route('overview'), navigate: false);
    }

    public function render()
    {
        return view('livewire.admin.tenant-switcher', [
            'tenants' => auth()->user()->hasRole('super-admin') ? Tenant::aktiv()->orderBy('name')->get() : collect(),
            'current' => app(\App\Domains\Identity\Support\CurrentTenant::class)->id(),
        ]);
    }
}
```

- [ ] **Step 3: View + Einbindung** — `resources/views/livewire/admin/tenant-switcher.blade.php`:
```blade
<div>
    @if ($tenants->isNotEmpty())
        <select class="btn btn-ghost btn-sm" wire:change="switchTo($event.target.value)">
            @foreach ($tenants as $t)<option value="{{ $t->id }}" @selected($t->id === $current)>{{ $t->name }}</option>@endforeach
        </select>
    @endif
</div>
```
In `layouts.app` im `.app-user`-Bereich vor dem Avatar einbinden: `@livewire('admin.tenant-switcher')`.

- [ ] **Step 4: DemoSeeder + DatabaseSeeder**

In `DemoSeeder::run()` nach dem ersten Heim ein zweites kleines Heim (`Haus Birkenhof`, slug `birkenhof`, 2 Bewohner) anlegen und einen super-admin `super@opcare.local` (Rolle `super-admin`) erstellen. In `DatabaseSeeder::run()` `SuperAdminRoleSeeder::class` vor `DemoSeeder` aufnehmen.
> WHY: damit der Mehrmandanten-Betrieb sofort sichtbar/testbar ist (kein „Feature ohne Outcome").

- [ ] **Step 5: Frisch migrieren/seeden + Tests grün**

Run:
```bash
php artisan migrate:fresh --seed
./vendor/bin/pest tests/Feature/Tenancy tests/Feature/Admin
```
Expected: alle PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(admin): super-admin tenant switcher + 2-home demo seed"
```

---

## Task 10: Hybrid-Migrationspfad dokumentieren + Gesamtsuite

**Files:**
- Create: `docs/superpowers/specs/2026-06-04-hybrid-tenancy-migrationspfad.md`

- [ ] **Step 1: Migrationspfad-Doku schreiben**

Inhalt (Kurzspec): Heute row-level (`tenant_id` + `TenantScope` + `TenantResolver`). Auslöser für DB-per-Tenant (z. B. vertragliche Datentrennung eines Trägers, DSGVO-Auftragsverarbeitung pro Heim, > N Heime/Performance). Pfad: (1) `TenantResolver` auf Connection-/DB-Switch erweitern (z. B. `stancl/tenancy` oder eigener `DatabaseManager::setDefaultConnection`), (2) zentrale Tabellen (`tenants`, `users`, spatie) bleiben in der Landlord-DB, (3) je-Heim-DB für Domänen-Tabellen, (4) `BelongsToTenant`-Scope entfällt dort (DB ist bereits isoliert). Risiken: heimübergreifende QDVS/Controlling-Auswertungen brauchen dann Aggregations-Layer (Landlord-Read-Replica oder ETL). Entscheidung bewusst vertagt — Naht ist `TenantResolver`.

- [ ] **Step 2: Gesamte Suite + Arch**

Run: `./vendor/bin/pest`
Expected: ALLE PASS (Plan 1–4).

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "docs(tenancy): hybrid db-per-tenant migration path; full suite green"
```

---

## Self-Review-Ergebnis (Plan 4)

- **Entscheidungs-Abdeckung:** Kein Filament → eigene Livewire-Admin-UI (Tasks 7–9). Hybrid-Tenancy → row-level gehärtet (Tasks 1–4) + `TenantResolver`-Naht + dokumentierter DB-per-Tenant-Pfad (Task 10). Mandanten/User/Rollen editierbar (Tasks 5–8). Super-Admin-Switch (Task 9).
- **Platzhalter:** keine — repetitive Admin-Views verweisen auf das exakte, bestehende Muster (`residents.blade.php` + `admin.css`-Klassen) und zeigen die nicht-offensichtlichen Teile (Rolle-Select, Switcher) als vollständigen Code.
- **Typ-Konsistenz:** `TenantData`/`AdminUserData`, `CreateTenant/UpdateTenant/CreateUser/UpdateUser/AssignRole::handle`, `TenantResolver::resolveFor($user, ?int $sessionTenantId)`, Session-Key `active_tenant_id`, Rollen `super-admin|admin|pflegefachkraft|pflegehilfskraft|leserecht` durchgängig identisch.
- **Sicherheit:** Cross-Tenant-Leak-Test (Task 4) + Arch-Guard erzwingen den Scope; Policies + `Gate::before(super-admin)` regeln Sichtbarkeit der Admin-UI.

## Folge-Pläne
- **Plan 5:** Medikation / BHP — `docs/superpowers/plans/2026-06-04-opcare-medikation-bhp.md`
- **Plan 6:** Controlling / QMS — `…-opcare-controlling-qms.md`
- **Plan 7:** QDVS-Export — `…-opcare-qdvs-export.md`
