<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\SpitzenzeitAnalyzer;
use App\Domains\Scheduling\Compliance\SpitzenzeitDefaults;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\Spitzenzeit;
use App\Livewire\Scheduling\Spitzenzeiten;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->montag = CarbonImmutable::parse('2026-06-08')->startOfWeek()->toDateString();
    $this->leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->leitung->assignRole('pflegefachkraft');
});

it('erkennt überlappende Schichten je Bedarfs-Fenster (auch über Mitternacht)', function () {
    // Frühdienst 06–14 deckt das Mittagessen-Fenster
    expect(Spitzenzeit::ueberlappt('06:00', '14:00', '11:30', '13:30'))->toBeTrue()
        // Spätdienst 14–22 deckt das Frühstück NICHT
        ->and(Spitzenzeit::ueberlappt('14:00', '22:00', '08:00', '09:30'))->toBeFalse()
        // Nachtdienst 22–06 (Umbruch) deckt ein Fenster 05:00–07:00
        ->and(Spitzenzeit::ueberlappt('22:00', '06:00', '05:00', '07:00'))->toBeTrue()
        // …aber nicht ein reines Vormittags-Fenster
        ->and(Spitzenzeit::ueberlappt('22:00', '06:00', '08:00', '09:30'))->toBeFalse();
});

it('seedet den Standard-Spitzenzeit-Katalog idempotent', function () {
    $a = SpitzenzeitDefaults::ensureFor($this->tenant->id);
    $b = SpitzenzeitDefaults::ensureFor($this->tenant->id);
    expect($a)->toHaveCount(count(SpitzenzeitDefaults::KATALOG))
        ->and($b)->toHaveCount(count(SpitzenzeitDefaults::KATALOG))
        ->and($a->firstWhere('name', 'Mittagessen')->soll_personen)->toBe(3);
});

it('bildet die Ampel aus dem Personen-Defizit', function () {
    expect(SpitzenzeitAnalyzer::ampel(3, 3))->toBe('gruen')
        ->and(SpitzenzeitAnalyzer::ampel(2, 3))->toBe('gelb')
        ->and(SpitzenzeitAnalyzer::ampel(1, 3))->toBe('rot');
});

it('berechnet die Deckung je Fenster und schlägt Spitzendienste bei Unterdeckung vor', function () {
    $frueh = Shift::create(['name' => 'Frühdienst', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00']);
    foreach (range(1, 2) as $i) {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
        ShiftAssignment::create(['user_id' => $u->id, 'shift_id' => $frueh->id, 'dienst_am' => $this->montag]);
    }

    $analyse = app(SpitzenzeitAnalyzer::class)->analysiere($this->tenant->id, $this->montag);
    $id = fn (string $name) => $analyse->fenster->firstWhere('name', $name)->id;

    expect($analyse->zellen[$id('Frühstück')][$this->montag]['ampel'])->toBe('gruen')   // 2/2
        ->and($analyse->zellen[$id('Mittagessen')][$this->montag])->toMatchArray(['ist' => 2, 'soll' => 3, 'ampel' => 'gelb'])
        ->and($analyse->zellen[$id('Abendversorgung')][$this->montag]['ampel'])->toBe('rot') // 0/2
        ->and($analyse->vorschlaege)->not->toBeEmpty()
        ->and($analyse->unterdeckungen())->toBeGreaterThan(0);
});

it('deaktiviert werktags-Fenster am Wochenende', function () {
    SpitzenzeitDefaults::ensureFor($this->tenant->id);
    Spitzenzeit::where('name', 'Mittagessen')->update(['nur_werktags' => true]);

    $analyse = app(SpitzenzeitAnalyzer::class)->analysiere($this->tenant->id, $this->montag);
    $id = $analyse->fenster->firstWhere('name', 'Mittagessen')->id;
    $samstag = $analyse->tage[5]['datum'];

    expect($analyse->tage[5]['wochenende'])->toBeTrue()
        ->and($analyse->zellen[$id][$samstag]['aktiv'])->toBeFalse()
        ->and($analyse->zellen[$id][$this->montag]['aktiv'])->toBeTrue();
});

it('legt ein Bedarfs-Fenster und einen Spitzendienst an', function () {
    $this->actingAs($this->leitung);
    Livewire::test(Spitzenzeiten::class)
        ->set('neu_name', 'Kaffee/Vesper')->set('neu_beginn', '15:00')->set('neu_ende', '16:00')->set('neu_soll', 2)
        ->call('anlegen')->assertHasNoErrors()
        ->set('sd_name', 'Spitzendienst Vesper')->set('sd_beginn', '14:45')->set('sd_ende', '16:30')
        ->call('spitzendienstAnlegen')->assertHasNoErrors();

    expect(Spitzenzeit::where('name', 'Kaffee/Vesper')->exists())->toBeTrue()
        ->and(Shift::where('name', 'Spitzendienst Vesper')->first()->kind)->toBe(ShiftKind::Spitzendienst);
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Spitzenzeiten::class)->assertForbidden();
});
