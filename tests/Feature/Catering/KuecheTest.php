<?php

use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Catering\Kueche;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['kueche', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $this->erika = Resident::create(['name' => 'Erika Nuss', 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
    $this->erika->allergies()->create(['substanz' => 'Haselnüsse', 'typ' => 'allergie', 'kategorie' => 'nahrung', 'kritikalitaet' => 'hoch', 'erfasst_am' => '2025-01-01']);
});

function kuechenkraft(int $tenantId): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole('kueche');

    return $u;
}

it('verwehrt Leserecht die Küche (Gesundheitsdaten)', function () {
    $leser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leser->assignRole('leserecht');
    $this->actingAs($leser);

    Livewire::test(Kueche::class)->assertForbidden();
});

it('zeigt der Küche die Lebensmittelallergien der Bewohner', function () {
    $this->actingAs(kuechenkraft($this->tenant->id));

    Livewire::test(Kueche::class)
        ->assertSee('Erika Nuss')
        ->assertSee('Haselnüsse');
});

it('legt ein Gericht an und warnt vor betroffenen Bewohnern', function () {
    $this->actingAs(kuechenkraft($this->tenant->id));

    Livewire::test(Kueche::class)
        ->set('g_mahlzeit', 'mittag')
        ->set('g_bezeichnung', 'Nussecken')
        ->set('g_allergene', [LmivAllergen::Schalenfruechte->value])
        ->call('gerichtAnlegen')
        ->assertHasNoErrors()
        ->assertSee('Nussecken')
        ->assertSee('Erika Nuss')
        ->assertSee('Schalenfrüchte (Nüsse)');

    expect(Gericht::where('bezeichnung', 'Nussecken')->whereDate('datum', today())->count())->toBe(1);
});
