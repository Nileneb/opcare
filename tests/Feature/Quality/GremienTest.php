<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Domains\Quality\Models\Gremium;
use App\Livewire\Quality\Gremien;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
});

it('legt ein Gremium an und übernimmt die Typ-Standardwerte', function () {
    Livewire::test(Gremien::class)
        ->set('g_typ', 'arbeitsschutzausschuss') // Hook setzt periode auf null
        ->set('g_typ', 'heimbeirat')             // Hook setzt periode zurück auf 24
        ->set('g_name', 'Heimbeirat 2026')
        ->call('anlegen')->assertHasNoErrors();

    $g = Gremium::where('name', 'Heimbeirat 2026')->firstOrFail();
    expect($g->typ)->toBe(GremiumTyp::Heimbeirat);
    expect($g->periode_monate)->toBe(24);
});

it('fügt ein Mitglied hinzu und entfernt es wieder', function () {
    $g = Gremium::create(['tenant_id' => $this->tenant->id, 'typ' => GremiumTyp::Heimbeirat, 'name' => 'HB']);
    $c = Livewire::test(Gremien::class)
        ->set('selected', $g->id)
        ->set('m_name', 'Frau Schulz')->set('m_funktion', 'vorsitz')
        ->call('mitgliedHinzufuegen')->assertHasNoErrors();

    $m = $g->mitglieder()->firstOrFail();
    expect($m->name)->toBe('Frau Schulz');

    $c->call('mitgliedEntfernen', $m->id);
    expect($g->mitglieder()->count())->toBe(0);
});

it('protokolliert eine Sitzung', function () {
    $g = Gremium::create(['tenant_id' => $this->tenant->id, 'typ' => GremiumTyp::Qualitaetszirkel, 'name' => 'QZ']);
    Livewire::test(Gremien::class)
        ->set('selected', $g->id)
        ->set('s_datum', today()->toDateString())->set('s_thema', 'Sturzprophylaxe')
        ->set('s_beschluesse', 'Neue Matten')
        ->call('sitzungProtokollieren')->assertHasNoErrors();

    expect($g->sitzungen()->firstOrFail()->thema)->toBe('Sturzprophylaxe');
});

it('verbietet das Anlegen ohne Verwaltungsrecht', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    Livewire::actingAs($koch)->test(Gremien::class)->assertForbidden();
});
