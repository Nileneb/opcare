<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Models\Beauftragtenbestellung;
use App\Domains\Personnel\Models\Beauftragtenrolle;
use App\Domains\Personnel\Support\BeauftragtenrolleDefaults;
use App\Livewire\Personnel\Beauftragtenregister;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pdl = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pdl->assignRole('pflegefachkraft');
    $this->pdl->employeeProfile()->create(['tenant_id' => $this->tenant->id]);
    $this->rollen = BeauftragtenrolleDefaults::ensureFor($this->tenant->id)->keyBy('key');
});

it('seedet den Pflicht-Katalog inkl. Haustechnik (Leiterbeauftragte:r)', function () {
    expect(Beauftragtenrolle::where('tenant_id', $this->tenant->id)->count())->toBe(count(BeauftragtenrolleDefaults::katalog()))
        ->and($this->rollen->has('leiterbeauftragte'))->toBeTrue()
        ->and($this->rollen['leiterbeauftragte']->rechtsbasis)->toContain('208-016');
});

it('bestellt eine Person mit berechneter Auffrischungsfrist und Ampel', function () {
    $this->actingAs($this->pdl);
    $hygiene = $this->rollen['hygiene']; // auffrischung 36 Monate

    Livewire::test(Beauftragtenregister::class)
        ->set('b_rolle', $hygiene->id)->set('b_user', $this->pdl->id)->set('b_datum', today()->toDateString())
        ->call('bestellen')->assertHasNoErrors();

    $b = Beauftragtenbestellung::where('beauftragten_rolle_id', $hygiene->id)->first();
    expect($b)->not->toBeNull()
        ->and($b->gueltig_bis->toDateString())->toBe(today()->addMonths(36)->toDateString())
        ->and($b->status())->toBe('gueltig');
});

it('zeigt überfällige Bestellungen rot', function () {
    $b = Beauftragtenbestellung::create(['tenant_id' => $this->tenant->id, 'beauftragten_rolle_id' => $this->rollen['ersthelfer']->id,
        'user_id' => $this->pdl->id, 'bestellt_am' => today()->subYears(3), 'gueltig_bis' => today()->subDay()]);
    expect($b->status())->toBe('ueberfaellig')->and($b->ampel())->toBe('red');
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Beauftragtenregister::class)->assertForbidden();
});
