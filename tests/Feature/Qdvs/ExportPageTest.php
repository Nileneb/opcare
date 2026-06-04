<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Models\QdvsExport;
use App\Livewire\Qdvs\Export;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260123456']);
    app(CurrentTenant::class)->set($t);
    $this->lead = User::factory()->create(['tenant_id' => $t->id]);
    $this->lead->assignRole('pflegefachkraft');
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Hypertonie']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);
});

it('erstellt einen Export über die UI', function () {
    Livewire::actingAs($this->lead)->test(Export::class)
        ->set('stichtag', '2026-02-15')->set('specKey', 'csv-v1')
        ->call('erstellen')->assertHasNoErrors();

    expect(QdvsExport::where('status', 'exportiert')->count())->toBe(1);
});

it('verweigert mount für leserecht-User', function () {
    $t = Tenant::first();
    $leser = User::factory()->create(['tenant_id' => $t->id]);
    $leser->assignRole('leserecht');
    app(CurrentTenant::class)->set($t);

    Livewire::actingAs($leser)->test(Export::class)
        ->assertForbidden();
});

it('verweigert erstellen() für pflegehilfskraft', function () {
    $t = Tenant::first();
    $hilfe = User::factory()->create(['tenant_id' => $t->id]);
    $hilfe->assignRole('pflegehilfskraft');
    app(CurrentTenant::class)->set($t);

    Livewire::actingAs($hilfe)->test(Export::class)
        ->assertForbidden();
});
