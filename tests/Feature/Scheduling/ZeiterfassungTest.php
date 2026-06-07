<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Models\Zeitbuchung;
use App\Livewire\Scheduling\Zeiterfassung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'pflegehilfskraft'] as $r) {
        Role::findOrCreate($r);
    }
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegehilfskraft');
});

it('stempelt Kommen und Gehen', function () {
    $this->actingAs($this->user);

    $c = Livewire::test(Zeiterfassung::class)->call('kommen');
    $offen = Zeitbuchung::where('user_id', $this->user->id)->whereNull('ende')->first();
    expect($offen)->not->toBeNull();

    $c->set('g_pause', 30)->call('gehen');
    $fresh = Zeitbuchung::find($offen->id);
    expect($fresh->ende)->not->toBeNull()
        ->and($fresh->pause_minuten)->toBe(30)
        ->and($fresh->laeuft())->toBeFalse();
});

it('erfasst eine Buchung manuell', function () {
    $this->actingAs($this->user);

    Livewire::test(Zeiterfassung::class)
        ->set('m_datum', today()->toDateString())
        ->set('m_beginn', '08:00')->set('m_ende', '16:00')->set('m_pause', 30)
        ->call('manuellAnlegen')->assertHasNoErrors();

    expect(Zeitbuchung::where('user_id', $this->user->id)->first()->istStunden())->toBe(7.5);
});

it('lässt nur eigene Buchungen löschen', function () {
    $this->actingAs($this->user);
    $fremd = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $fremdeBuchung = Zeitbuchung::create(['user_id' => $fremd->id, 'datum' => today()->toDateString(), 'beginn' => '08:00', 'ende' => '16:00']);

    Livewire::test(Zeiterfassung::class)->call('entfernen', $fremdeBuchung->id)->assertForbidden();
    expect(Zeitbuchung::find($fremdeBuchung->id))->not->toBeNull();
});

it('zeigt der Leitung die Team-Übersicht (Ist vs. Soll)', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $this->actingAs($leitung);
    Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => today()->toDateString(), 'beginn' => '08:00', 'ende' => '16:00', 'pause_minuten' => 30]);

    Livewire::test(Zeiterfassung::class)->assertSee('Team');
});

it('zählt Buchungen am letzten Wochentag mit (date-Cast-Grenze, nicht nur Mo–Sa)', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $this->actingAs($leitung);
    // 2026-06-07 ist ein Sonntag = letzter Tag der Woche, die am 2026-06-01 (Mo) beginnt.
    Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-07', 'beginn' => '08:00', 'ende' => '16:00', 'pause_minuten' => 30]);

    Livewire::test(Zeiterfassung::class)
        ->set('weekStart', '2026-06-01')
        ->assertSee('Team')
        ->assertSee('7.5 h'); // Ist-Stunden tauchen in der Team-Übersicht auf
});
