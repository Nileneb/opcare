<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Models\EmployeeProfile;
use App\Livewire\Personnel\Personalakte;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'leserecht', 'pflegefachkraft'] as $r) {
        Role::findOrCreate($r);
    }
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('admin');
    $this->mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sandra Vogt']);
});

it('verwehrt Leserecht den Zugriff auf die Personalakte', function () {
    $leser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leser->assignRole('leserecht');
    $this->actingAs($leser);

    Livewire::test(Personalakte::class, ['user' => $this->mitarbeiter])->assertForbidden();
});

it('speichert die Personalakte und verschlüsselt sensible Felder', function () {
    $this->actingAs($this->admin);

    Livewire::test(Personalakte::class, ['user' => $this->mitarbeiter])
        ->set('f.vorname', 'Sandra')
        ->set('f.qualifikation', 'pflegefachkraft')
        ->set('f.wochenstunden', '38.5')
        ->set('f.steuer_id', '12345678901')
        ->set('f.iban', 'DE89370400440532013000')
        ->call('speichern')
        ->assertHasNoErrors();

    $profile = EmployeeProfile::where('user_id', $this->mitarbeiter->id)->first();
    expect($profile->vorname)->toBe('Sandra')
        ->and($profile->wochenstunden)->toBe(38.5)
        ->and($profile->steuer_id)->toBe('12345678901');

    $raw = DB::table('employee_profiles')->where('id', $profile->id)->first();
    expect($raw->steuer_id)->not->toBe('12345678901')
        ->and($raw->iban)->not->toContain('DE89370400440532013000');
});

it('koppelt die Rollen-Zuweisung an die Personalakte', function () {
    $this->actingAs($this->admin);

    Livewire::test(Personalakte::class, ['user' => $this->mitarbeiter])
        ->set('role', 'pflegefachkraft')
        ->call('setRole');

    expect($this->mitarbeiter->fresh()->hasRole('pflegefachkraft'))->toBeTrue();
});

it('verhindert den Zugriff auf die Personalakte eines fremden Mandanten (IDOR → 403)', function () {
    // WHY: User erbt nicht von BaseModel (kein Tenant-Global-Scope) → die UserPolicy verweigert den
    // mandantenübergreifenden Zugriff (403), wie bei der Rollen-Zuweisung in Admin\Users.
    $this->actingAs($this->admin);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $fremderUser = User::factory()->create(['tenant_id' => $fremd->id]);

    $this->get(route('personnel.akte', $fremderUser))->assertForbidden();
});
