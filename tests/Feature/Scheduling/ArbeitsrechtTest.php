<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Livewire\Scheduling\Arbeitsrecht;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('leserecht');
});

it('verweigert Leserecht den Regel-Editor', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('leserecht');
    $this->actingAs($u);

    Livewire::test(Arbeitsrecht::class)->assertForbidden();
});

it('seedet die Regeln und zeigt den amtlichen Gesetzeslink je Regel', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('admin');
    $this->actingAs($u);

    Livewire::test(Arbeitsrecht::class)
        ->assertSee('Tägliche Höchstarbeitszeit')
        ->assertSee('gesetze-im-internet.de/arbzg/__3.html')
        ->assertSee('§ 14 ArbZG');
});

it('speichert einen editierten Schwellwert', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('admin');
    $this->actingAs($u);
    $rule = ArbeitszeitgesetzDefaults::ensureFor($this->tenant->id)->firstWhere('key', 'tageshoechstarbeitszeit');

    Livewire::test(Arbeitsrecht::class)
        ->set("edits.{$rule->id}.params.max_stunden", 12)
        ->call('speichern', $rule->id)
        ->assertHasNoErrors();

    expect(ComplianceRule::find($rule->id)->param('max_stunden'))->toBe(12);
});

it('setzt eine Regel auf den ArbZG-Standard zurück', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('admin');
    $this->actingAs($u);
    $rule = ArbeitszeitgesetzDefaults::ensureFor($this->tenant->id)->firstWhere('key', 'tageshoechstarbeitszeit');
    $rule->update(['params' => ['max_stunden' => 16, 'hinweis_ab_stunden' => 8], 'aktiv' => false]);

    Livewire::test(Arbeitsrecht::class)->call('zuruecksetzen', $rule->id);

    $fresh = ComplianceRule::find($rule->id);
    expect($fresh->param('max_stunden'))->toBe(10)->and($fresh->aktiv)->toBeTrue();
});
