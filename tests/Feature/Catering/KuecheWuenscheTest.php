<?php

use App\Domains\Catering\Enums\EssenswunschArt;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Essenswunsch;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Catering\Models\Menuewahl;
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
    Role::findOrCreate('kueche');
    $this->koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->koch->assignRole('kueche');
    $this->maria = Resident::create(['name' => 'Maria', 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
});

it('hinterlegt einen allgemeinen Essenswunsch über die Küche', function () {
    $this->actingAs($this->koch);

    Livewire::test(Kueche::class)
        ->set('ew_resident', $this->maria->id)->set('ew_art', EssenswunschArt::Abneigung->value)->set('ew_text', 'kein Fisch')
        ->call('essenswunschAnlegen')->assertHasNoErrors()
        ->assertSee('kein Fisch');

    expect(Essenswunsch::where('resident_id', $this->maria->id)->first()->art)->toBe(EssenswunschArt::Abneigung);
});

it('lässt einen Bewohner sein Menü wählen — eine Wahl je Mahlzeit', function () {
    $this->actingAs($this->koch);
    $a = Gericht::create(['datum' => today()->toDateString(), 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Fischfilet', 'allergene' => []]);
    $b = Gericht::create(['datum' => today()->toDateString(), 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Gemüseeintopf', 'allergene' => []]);

    $c = Livewire::test(Kueche::class)
        ->call('wahlOeffnen', $a->id)->set('waehler', [$this->maria->id])->call('wahlSpeichern');
    expect(Menuewahl::where('gericht_id', $a->id)->where('resident_id', $this->maria->id)->exists())->toBeTrue();

    // wechselt auf Gericht B derselben Mahlzeit → Wahl bei A wird entfernt
    $c->call('wahlOeffnen', $b->id)->set('waehler', [$this->maria->id])->call('wahlSpeichern');
    expect(Menuewahl::where('gericht_id', $b->id)->where('resident_id', $this->maria->id)->exists())->toBeTrue()
        ->and(Menuewahl::where('gericht_id', $a->id)->where('resident_id', $this->maria->id)->exists())->toBeFalse();
});
