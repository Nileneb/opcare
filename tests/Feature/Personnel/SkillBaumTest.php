<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Models\Kompetenz;
use App\Domains\Personnel\Models\MitarbeiterKompetenz;
use App\Domains\Personnel\Support\KompetenzDefaults;
use App\Livewire\Personnel\SkillBaum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pdl = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pdl->assignRole('pflegefachkraft');
    $this->ma = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ma->employeeProfile()->create(['tenant_id' => $this->tenant->id]);
    $this->katalog = KompetenzDefaults::ensureFor($this->tenant->id)->keyBy('key');
});

it('seedet den Katalog mit Voraussetzungen (DAG)', function () {
    expect(Kompetenz::where('tenant_id', $this->tenant->id)->count())->toBe(count(KompetenzDefaults::katalog()))
        ->and($this->katalog['lg2']->voraussetzungen->pluck('key'))->toContain('lg1')
        ->and($this->katalog['wundexperte_icw']->voraussetzungen->pluck('key'))->toContain('pflegefachkraft');
});

it('erzwingt Voraussetzungen beim Erteilen', function () {
    $this->actingAs($this->pdl);

    // Wundexperte ohne Pflegefachkraft → Fehler
    Livewire::test(SkillBaum::class)
        ->set('selectedUser', $this->ma->id)->set('g_kompetenz', $this->katalog['wundexperte_icw']->id)->set('g_datum', today()->toDateString())
        ->call('erteilen')->assertHasErrors('g_kompetenz');
    expect(MitarbeiterKompetenz::where('user_id', $this->ma->id)->count())->toBe(0);

    // erst Pflegefachkraft, dann Wundexperte → ok, gueltig_bis = +60 Monate
    $comp = Livewire::test(SkillBaum::class)->set('selectedUser', $this->ma->id);
    $comp->set('g_kompetenz', $this->katalog['pflegefachkraft']->id)->set('g_datum', today()->toDateString())->call('erteilen')->assertHasNoErrors();
    $comp->set('g_kompetenz', $this->katalog['wundexperte_icw']->id)->set('g_datum', today()->toDateString())->call('erteilen')->assertHasNoErrors();

    $we = MitarbeiterKompetenz::where('user_id', $this->ma->id)->where('kompetenz_id', $this->katalog['wundexperte_icw']->id)->first();
    expect($we)->not->toBeNull()
        ->and($we->gueltig_bis->toDateString())->toBe(today()->addMonths(60)->toDateString())
        ->and($we->status())->toBe('gueltig');
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(SkillBaum::class)->assertForbidden();
});
