<?php

use App\Domains\Identity\Enums\Bundesland;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\BundeslandResolver;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\HeimrechtRegelwerk;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;
use App\Livewire\Admin\Heimrecht;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Haus Aprath', 'slug' => 'aprath', 'plz' => '42489', 'ort' => 'Wülfrath']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('kueche');
    $this->leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->leitung->assignRole('admin');
});

it('leitet das Bundesland aus der PLZ-Leitregion ab', function () {
    expect(BundeslandResolver::fromPlz('42489'))->toBe(Bundesland::NW)
        ->and(BundeslandResolver::fromPlz('10115'))->toBe(Bundesland::BE)
        ->and(BundeslandResolver::fromPlz('80331'))->toBe(Bundesland::BY)
        ->and(BundeslandResolver::fromPlz('01067'))->toBe(Bundesland::SN)
        ->and(BundeslandResolver::fromPlz(null))->toBeNull()
        ->and(BundeslandResolver::fromPlz('x'))->toBeNull();
});

it('bevorzugt das explizit gewählte Bundesland vor der PLZ-Ableitung', function () {
    expect($this->tenant->landesrecht())->toBe(Bundesland::NW); // aus PLZ 42489

    $this->tenant->update(['bundesland' => Bundesland::BY]);
    expect($this->tenant->fresh()->landesrecht())->toBe(Bundesland::BY); // manuell überschrieben
});

it('hält das Landesheimgesetz je Bundesland als Norm-Quelle vor', function () {
    expect(Bundesland::NW->heimgesetz())->toBe('WTG NRW')
        ->and(Bundesland::NW->gesetzTitel())->toContain('Teilhabe')
        ->and(Bundesland::NW->gesetzUrl())->toStartWith('https://');
});

it('speist den Bundes-Default in die Personalbemessung ein, ohne Landeswerte zu raten', function () {
    $heimrecht = HeimrechtRegelwerk::fuer(Bundesland::NW);
    expect($heimrecht['fachkraftquote_min'])->toBe(0.5) // § 5 HeimPersV
        ->and($heimrecht['nachtdienst_je_fachkraft'])->toBe(50)
        ->and($heimrecht['landesspezifisch'])->toBeFalse(); // kein geratener Landeswert

    $config = PersonalbemessungDefaults::ensureConfig($this->tenant->id);
    expect($config->fachkraftquote_min)->toBe(0.5)
        ->and($config->nachtdienst_je_fachkraft)->toBe(50);
});

it('zeigt der Leitung das geltende Landesrecht und speichert eine manuelle Zuordnung', function () {
    $this->actingAs($this->leitung);
    Livewire::test(Heimrecht::class)
        ->assertViewHas('land', Bundesland::NW)
        ->assertViewHas('ausPlz', true)
        ->set('bundesland', Bundesland::BY->value)
        ->call('speichern')->assertHasNoErrors();

    expect($this->tenant->fresh()->bundesland)->toBe(Bundesland::BY);
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Heimrecht::class)->assertForbidden();
});
