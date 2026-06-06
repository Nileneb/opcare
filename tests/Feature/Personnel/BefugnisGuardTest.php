<?php

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\ResidentShow;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['pflegefachkraft', 'pflegehilfskraft'] as $r) {
        Role::findOrCreate($r);
    }
    $this->resident = Resident::factory()->create();
});

it('blockt SIS-Abzeichnen durch eine Hilfskraft (Vorbehalt § 4 PflBG)', function () {
    $hilfskraft = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $hilfskraft->assignRole('pflegehilfskraft');
    $this->actingAs($hilfskraft);

    Livewire::test(ResidentShow::class, ['resident' => $this->resident])
        ->set('sis_eingangsfrage', 'Test')
        ->call('createSis')->assertForbidden();

    expect(SisAssessment::where('resident_id', $this->resident->id)->count())->toBe(0);
});

it('erlaubt SIS-Abzeichnen durch eine Pflegefachkraft', function () {
    $fachkraft = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $fachkraft->assignRole('pflegefachkraft');
    $this->actingAs($fachkraft);

    Livewire::test(ResidentShow::class, ['resident' => $this->resident])
        ->set('sis_eingangsfrage', 'Test')
        ->call('createSis')->assertHasNoErrors();

    expect(SisAssessment::where('resident_id', $this->resident->id)->count())->toBe(1);
});
