<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\QmBereich;
use App\Domains\Quality\Enums\QmStatus;
use App\Domains\Quality\Models\QmRequirement;
use App\Domains\Quality\Support\QmKatalogDefaults;
use App\Livewire\Quality\QmCheckliste;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('admin');
});

it('verwehrt Leserecht die QM-Checkliste', function () {
    $leser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leser->assignRole('leserecht');
    $this->actingAs($leser);

    Livewire::test(QmCheckliste::class)->assertForbidden();
});

it('zeigt norm-verankerte Anforderungen mit Gesetzeslink', function () {
    $this->actingAs($this->admin);

    Livewire::test(QmCheckliste::class)
        ->assertSee('Hygieneplan')
        ->assertSee('§ 20 Abs. 9 IfSG')
        ->assertSee('QB 1 — Mobilität & Selbstversorgung');
});

it('speichert den Status und setzt geprueft_am bei Erfüllung', function () {
    $this->actingAs($this->admin);
    $rule = QmKatalogDefaults::ensureFor($this->tenant->id)->firstWhere('schluessel', 'hyg_plan');

    Livewire::test(QmCheckliste::class)
        ->set("edits.{$rule->id}.status", QmStatus::Erfuellt->value)
        ->set("edits.{$rule->id}.zustaendig", 'Hygienebeauftragte')
        ->call('speichern', $rule->id)
        ->assertHasNoErrors();

    $fresh = QmRequirement::find($rule->id);
    expect($fresh->status)->toBe(QmStatus::Erfuellt)
        ->and($fresh->zustaendig)->toBe('Hygienebeauftragte')
        ->and($fresh->geprueft_am)->not->toBeNull();
});

it('ergänzt und entfernt eine eigene Anforderung (aber keine Standard-Anforderung)', function () {
    $this->actingAs($this->admin);
    $standard = QmKatalogDefaults::ensureFor($this->tenant->id)->first();

    $component = Livewire::test(QmCheckliste::class)
        ->set('neu_bereich', QmBereich::Datenschutz->value)
        ->set('neu_norm', 'intern')
        ->set('neu_anforderung', 'Eigene Festlegung X')
        ->call('anlegen')
        ->assertHasNoErrors();

    $eigene = QmRequirement::where('anforderung', 'Eigene Festlegung X')->firstOrFail();
    expect($eigene->schluessel)->toBeNull();

    $component->call('entfernen', $eigene->id);
    expect(QmRequirement::find($eigene->id))->toBeNull();

    // Standard-Anforderung ist NICHT löschbar
    $component->call('entfernen', $standard->id)->assertForbidden();
    expect(QmRequirement::find($standard->id))->not->toBeNull();
});
