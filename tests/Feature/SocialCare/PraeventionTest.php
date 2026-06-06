<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\SocialCare\Enums\Handlungsfeld;
use App\Domains\SocialCare\Models\Praeventionsprogramm;
use App\Domains\SocialCare\Models\Praeventionsteilnahme;
use App\Livewire\SocialCare\Praevention;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('betreuungskraft');
    Role::findOrCreate('kueche');
    $this->bk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->bk->assignRole('betreuungskraft');
    $this->maria = Resident::factory()->create(['status' => 'aktiv']);
    $this->otto = Resident::factory()->create(['status' => 'aktiv']);
});

it('legt ein Programm an und dokumentiert Teilnahmen als Verwendungsnachweis', function () {
    $this->actingAs($this->bk);

    $comp = Livewire::test(Praevention::class)
        ->set('p_handlungsfeld', Handlungsfeld::Bewegung->value)->set('p_titel', 'Sturzpräventions-Gymnastik')
        ->call('programmAnlegen')->assertHasNoErrors();

    $programm = Praeventionsprogramm::where('titel', 'Sturzpräventions-Gymnastik')->firstOrFail();
    expect($programm->handlungsfeld)->toBe(Handlungsfeld::Bewegung);

    $comp->call('teilnahmeStart', $programm->id)
        ->set('t_datum', today()->toDateString())->set('t_dauer', 45)
        ->set('t_teilnehmer', [$this->maria->id, $this->otto->id])
        ->call('teilnahmeSpeichern')->assertHasNoErrors();

    expect(Praeventionsteilnahme::where('praeventionsprogramm_id', $programm->id)->count())->toBe(2)
        ->and((int) $programm->teilnahmen()->sum('dauer_minuten'))->toBe(90);
});

it('verwehrt den Zugriff ohne passende Rolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);

    Livewire::test(Praevention::class)->assertForbidden();
});
