<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Energiestufe;
use App\Domains\Personnel\Models\Energielevel;
use App\Livewire\Personnel\Energiebarometer;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('betreuer');
    $this->ma = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ma->assignRole('pflegefachkraft');
});

it('bildet die Ampel je Energiestufe ab', function () {
    expect(Energiestufe::Erschoepft->ampel())->toBe('red')
        ->and(Energiestufe::Mittel->ampel())->toBe('amber')
        ->and(Energiestufe::Energiegeladen->ampel())->toBe('green');
});

it('speichert genau eine aktuelle Zeile je Mitarbeitendem (kein Verlauf)', function () {
    $this->actingAs($this->ma);
    Livewire::test(Energiebarometer::class)
        ->call('setzen', Energiestufe::Erschoepft->value)->assertHasNoErrors()
        ->call('setzen', Energiestufe::Energiegeladen->value);

    expect(Energielevel::where('user_id', $this->ma->id)->count())->toBe(1)
        ->and(Energielevel::where('user_id', $this->ma->id)->first()->stufe)->toBe(Energiestufe::Energiegeladen);
});

it('erlaubt das freiwillige Zurücknehmen der Rückmeldung', function () {
    $this->actingAs($this->ma);
    Livewire::test(Energiebarometer::class)
        ->call('setzen', Energiestufe::Mittel->value)
        ->call('zuruecknehmen');

    expect(Energielevel::where('user_id', $this->ma->id)->count())->toBe(0);
});

it('zeigt den Hausschnitt erst ab der Mindest-Rückmeldezahl (k-Anonymität)', function () {
    $this->actingAs($this->ma);

    Livewire::test(Energiebarometer::class)
        ->call('setzen', Energiestufe::Mittel->value)
        ->assertViewHas('auswertbar', false); // 1 < MIN_AUSWERTBAR

    foreach (range(1, 2) as $i) {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Energielevel::create(['tenant_id' => $this->tenant->id, 'user_id' => $u->id, 'stufe' => Energiestufe::Energiegeladen]);
    }

    Livewire::test(Energiebarometer::class)
        ->assertViewHas('auswertbar', true)
        ->assertViewHas('gesamt', 3);
});

it('verwehrt die Teilnahme für Portal-Nutzer (Betreuer)', function () {
    $betreuer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $betreuer->assignRole('betreuer');
    $this->actingAs($betreuer);
    Livewire::test(Energiebarometer::class)->assertForbidden();
});
