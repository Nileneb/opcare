<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\SocialCare\Models\Betreuungsangebot;
use App\Domains\SocialCare\Models\BetreuungsTeilnahme;
use App\Livewire\SocialCare\Betreuung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['betreuungskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $this->maria = Resident::create(['name' => 'Maria', 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
    $this->kurt = Resident::create(['name' => 'Kurt', 'geburtsdatum' => '1938-01-01', 'geschlecht' => 'm', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
});

function betreuer(int $tenantId): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole('betreuungskraft');

    return $u;
}

it('verwehrt Leserecht die Soziale Betreuung', function () {
    $leser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leser->assignRole('leserecht');
    $this->actingAs($leser);

    Livewire::test(Betreuung::class)->assertForbidden();
});

it('legt ein Angebot an, dokumentiert die Teilnahme und zeigt den § 43b-Nachweis', function () {
    $this->actingAs(betreuer($this->tenant->id));

    $c = Livewire::test(Betreuung::class)
        ->set('a_art', 'musik')->set('a_titel', 'Singkreis')->set('a_dauer', 45)
        ->call('angebotAnlegen')->assertHasNoErrors()
        ->assertSee('Singkreis');

    $angebot = Betreuungsangebot::firstOrFail();
    $c->call('teilnahmeOeffnen', $angebot->id)
        ->set('teilnehmer', [$this->maria->id])
        ->call('teilnahmeSpeichern')
        ->assertHasNoErrors()
        ->assertSee('Maria')
        ->assertSee('noch keine Betreuung im Monat'); // gilt für Kurt

    expect(BetreuungsTeilnahme::where('resident_id', $this->maria->id)->where('teilgenommen', true)->count())->toBe(1);
});
