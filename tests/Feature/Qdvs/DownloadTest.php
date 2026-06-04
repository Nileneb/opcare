<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Qdvs\Models\QdvsExport;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260123456']);
    app(CurrentTenant::class)->set($this->tenant);
});

function makeExport(int $tenantId): QdvsExport
{
    Storage::disk('local')->put('qdvs/'.$tenantId.'-x.csv', "pseudonym\nR-1\n");

    return QdvsExport::create([
        'tenant_id' => $tenantId,
        'stichtag' => '2026-02-15',
        'spec' => 'csv-v1',
        'status' => 'exportiert',
        'bewohner_count' => 1,
        'pfad' => 'qdvs/'.$tenantId.'-x.csv',
        'fehler' => [],
    ]);
}

it('lässt berechtigte Rollen herunterladen', function () {
    $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $admin->assignRole('pflegefachkraft');
    $export = makeExport($this->tenant->id);

    $this->actingAs($admin)->get(route('qdvs.download', $export))->assertOk();
});

it('verwehrt Niedrigrollen den Download', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('leserecht');
    $export = makeExport($this->tenant->id);

    $this->actingAs($u)->get(route('qdvs.download', $export))->assertForbidden();
});

it('verhindert Download fremder Mandanten (404 via tenant-scope)', function () {
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b', 'ik_nummer' => '260999999']);
    app(CurrentTenant::class)->set($fremd);
    $fremdExport = makeExport($fremd->id);

    // Nutzer aus Tenant A (pflegefachkraft) versucht Export aus Tenant B zu laden
    app(CurrentTenant::class)->set($this->tenant);
    $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $admin->assignRole('pflegefachkraft');

    $this->actingAs($admin)->get(route('qdvs.download', $fremdExport))->assertNotFound();
});
