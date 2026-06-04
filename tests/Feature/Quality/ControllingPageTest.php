<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Quality\Controlling;
use App\Livewire\Quality\QualityReport;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->lead = User::factory()->create(['tenant_id' => $t->id]);
    $this->lead->assignRole('pflegefachkraft');
    Resident::factory()->count(2)->create(['status' => 'aktiv', 'aufnahme_am' => '2026-01-01']);
});

it('rendert das Controlling-Dashboard mit KPIs', function () {
    Livewire::actingAs($this->lead)->test(Controlling::class)->assertOk()->assertSee('Belegung');
});

it('rendert den Qualitäts-Report für einen Stichtag', function () {
    Livewire::actingAs($this->lead)->test(QualityReport::class)
        ->set('stichtag', '2026-02-15')->set('von', '2026-01-01')->set('bis', '2026-03-31')
        ->call('berechnen')->assertOk()->assertSee('Sturz');
});
