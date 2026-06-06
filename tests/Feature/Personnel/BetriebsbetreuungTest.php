<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\BetriebsbetreuungArt;
use App\Domains\Personnel\Models\Betriebsbetreuung;
use App\Livewire\Personnel\Arbeitsschutz;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
});

function neueBetreuung(array $attr = []): Betriebsbetreuung
{
    return Betriebsbetreuung::create(array_merge([
        'tenant_id' => app(CurrentTenant::class)->id(),
        'art' => BetriebsbetreuungArt::Betriebsarzt, 'name' => 'Dr. Test',
    ], $attr));
}

it('zeigt eine überfällige Begehung als rot', function () {
    $b = neueBetreuung(['letzte_begehung' => today()->subMonths(13)->toDateString(), 'begehung_intervall_monate' => 12]);
    expect($b->status())->toBe('ueberfaellig');
    expect($b->ampel())->toBe('red');
});

it('zeigt eine fehlende Begehung bei Pflichtintervall als amber (nicht grün)', function () {
    $b = neueBetreuung(['begehung_intervall_monate' => 12]);
    expect($b->status())->toBe('offen');
    expect($b->ampel())->toBe('amber');
});

it('ist grün bei aktueller Begehung', function () {
    $b = neueBetreuung(['letzte_begehung' => today()->subMonths(2)->toDateString(), 'begehung_intervall_monate' => 12]);
    expect($b->ampel())->toBe('green');
});

it('legt eine Betreuung an und dokumentiert eine Begehung', function () {
    Livewire::test(Arbeitsschutz::class)
        ->set('bb_art', 'sifa')->set('bb_name', 'Hr. Sicher')->set('bb_intervall', 12)
        ->call('betreuungAnlegen')->assertHasNoErrors();

    $b = Betriebsbetreuung::firstOrFail();
    expect($b->art)->toBe(BetriebsbetreuungArt::Sifa);

    Livewire::test(Arbeitsschutz::class)
        ->set('beg_datum', today()->toDateString())
        ->call('begehungDokumentieren', $b->id)->assertHasNoErrors();

    expect($b->fresh()->letzte_begehung?->toDateString())->toBe(today()->toDateString());
});
