<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\FortbildungsThema;
use App\Domains\Personnel\Models\Fortbildung;
use App\Livewire\Personnel\Fortbildungsplan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Haus F', 'slug' => 'haus-f']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pdl = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pdl->assignRole('pflegefachkraft');
    $this->pdl->employeeProfile()->create(['tenant_id' => $this->tenant->id]);
});

it('kennt Pflicht- und fachliche Themen mit Intervall', function () {
    expect(FortbildungsThema::Hygiene->pflicht())->toBeTrue()
        ->and(FortbildungsThema::Hygiene->intervallMonate())->toBe(12)
        ->and(FortbildungsThema::Gewaltschutz->intervallMonate())->toBe(24)
        ->and(FortbildungsThema::Palliativ->pflicht())->toBeFalse()
        ->and(FortbildungsThema::Datenschutz->rechtsbasis())->toContain('DSGVO');
});

it('berechnet die Auffrischungs-Ampel einer Pflichtfortbildung', function () {
    $aktuell = Fortbildung::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->pdl->id, 'thema' => FortbildungsThema::Hygiene,
        'titel' => 'Händehygiene', 'absolviert_am' => today(), 'pflicht' => true, 'intervall_monate' => 12,
    ]);
    expect($aktuell->status())->toBe('aktuell')->and($aktuell->ampel())->toBe('green')
        ->and($aktuell->naechsteFaelligkeit()->toDateString())->toBe(today()->addMonths(12)->toDateString());

    $aktuell->update(['absolviert_am' => today()->subMonths(13)]);
    expect($aktuell->fresh()->status())->toBe('ueberfaellig')->and($aktuell->fresh()->ampel())->toBe('red');

    $geplant = Fortbildung::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->pdl->id, 'thema' => FortbildungsThema::Demenz,
        'titel' => 'Demenz-Update', 'geplant_am' => today()->addWeek(), 'pflicht' => false,
    ]);
    expect($geplant->status())->toBe('geplant')->and($geplant->ampel())->toBe('gray');
});

it('belegt Pflicht und Intervall aus dem Thema vor', function () {
    $this->actingAs($this->pdl);
    Livewire::test(Fortbildungsplan::class)
        ->set('f_thema', 'gewaltschutz')->assertSet('f_pflicht', true)->assertSet('f_intervall', 24)
        ->set('f_thema', 'palliativ')->assertSet('f_pflicht', false)->assertSet('f_intervall', null);
});

it('plant eine Fortbildung und markiert sie als absolviert', function () {
    $this->actingAs($this->pdl);
    Livewire::test(Fortbildungsplan::class)
        ->set('f_user', $this->pdl->id)->set('f_thema', 'hygiene')->set('f_titel', 'Hygieneschulung')
        ->set('f_geplant_am', today()->toDateString())->set('f_absolviert_am', null)
        ->call('planen')->assertHasNoErrors();

    $fb = Fortbildung::first();
    expect($fb->absolviert_am)->toBeNull()->and($fb->pflicht)->toBeTrue();

    Livewire::test(Fortbildungsplan::class)->call('absolviert', $fb->id);
    expect($fb->fresh()->absolviert_am->toDateString())->toBe(today()->toDateString());
});

it('zeigt fehlende Pflichtthemen als Lücke', function () {
    $this->actingAs($this->pdl);
    // PDL hat noch keine Pflichtfortbildung absolviert → alle Pflichtthemen sind Lücken
    $anzahlPflicht = count(array_filter(FortbildungsThema::cases(), fn ($t) => $t->pflicht()));
    Livewire::test(Fortbildungsplan::class)->assertViewHas('luecken', $anzahlPflicht);
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Fortbildungsplan::class)->assertForbidden();
});
