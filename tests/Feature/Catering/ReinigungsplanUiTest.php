<?php

use App\Domains\Catering\Enums\ReinigungsIntervall;
use App\Domains\Catering\Models\Reinigungsaufgabe;
use App\Domains\Catering\Models\Reinigungsnachweis;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Catering\Reinigungsplan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Reinigungsplan UI Haus', 'slug' => 'reinigung-ui-haus']);
    app(CurrentTenant::class)->set($this->tenant);

    foreach (['admin', 'kueche', 'pflegefachkraft', 'haustechnik'] as $r) {
        Role::findOrCreate($r);
    }
});

function reinigungsUser(int $tenantId, string $rolle = 'kueche'): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($rolle);

    return $u;
}

// ---------------------------------------------------------------------------
// Basis-Zugriff
// ---------------------------------------------------------------------------

it('zeigt Reinigungsplan-Seite für kueche-Rolle (assertOk)', function () {
    $this->actingAs(reinigungsUser($this->tenant->id, 'kueche'));

    Livewire::test(Reinigungsplan::class)->assertOk();
});

it('zeigt Reinigungsplan-Seite für admin-Rolle', function () {
    $this->actingAs(reinigungsUser($this->tenant->id, 'admin'));

    Livewire::test(Reinigungsplan::class)->assertOk();
});

it('verwehrt Zugriff für Rolle haustechnik (403)', function () {
    $this->actingAs(reinigungsUser($this->tenant->id, 'haustechnik'));

    Livewire::test(Reinigungsplan::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Aufgabe anlegen
// ---------------------------------------------------------------------------

it('legt Reinigungsaufgabe an und persistiert sie tenant-scoped', function () {
    $this->actingAs(reinigungsUser($this->tenant->id));

    Livewire::test(Reinigungsplan::class)
        ->set('bezeichnung', 'Arbeitsflächen täglich')
        ->set('bereich', 'Küche')
        ->set('intervall', ReinigungsIntervall::Taeglich->value)
        ->set('verantwortlich', 'Küchenpersonal')
        ->call('aufgabeSpeichern')
        ->assertHasNoErrors();

    $aufgabe = Reinigungsaufgabe::where('tenant_id', $this->tenant->id)
        ->where('bezeichnung', 'Arbeitsflächen täglich')
        ->first();

    expect($aufgabe)->not->toBeNull()
        ->and($aufgabe->intervall)->toBe(ReinigungsIntervall::Taeglich)
        ->and($aufgabe->bereich)->toBe('Küche')
        ->and($aufgabe->aktiv)->toBeTrue();
});

it('schlägt fehl ohne Bezeichnung', function () {
    $this->actingAs(reinigungsUser($this->tenant->id));

    Livewire::test(Reinigungsplan::class)
        ->set('bezeichnung', '')
        ->set('intervall', ReinigungsIntervall::Taeglich->value)
        ->call('aufgabeSpeichern')
        ->assertHasErrors(['bezeichnung']);
});

it('schlägt fehl ohne Intervall', function () {
    $this->actingAs(reinigungsUser($this->tenant->id));

    Livewire::test(Reinigungsplan::class)
        ->set('bezeichnung', 'Test Aufgabe')
        ->set('intervall', '')
        ->call('aufgabeSpeichern')
        ->assertHasErrors(['intervall']);
});

// ---------------------------------------------------------------------------
// Erledigung melden
// ---------------------------------------------------------------------------

it('erledigt eine überfällige Aufgabe → Nachweis anlegen, letzte_erledigung_am = heute, Status nicht mehr rot', function () {
    $this->actingAs(reinigungsUser($this->tenant->id));

    $aufgabe = Reinigungsaufgabe::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Überfällige Aufgabe',
        // Wöchentlich + letzte Erledigung vor 14 Tagen → rot; nach Erledigung heute: nächste in 7 Tagen → gruen (>3-Tage-Schwelle)
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDays(14)->toDateString(),
    ]);

    expect($aufgabe->faelligkeitsStatus())->toBe('rot');

    Livewire::test(Reinigungsplan::class)
        ->set('erledigt_am', today()->toDateString())
        ->set('bemerkung', 'Gereinigt mit Desinfektionsmittel')
        ->call('erledigen', $aufgabe->id)
        ->assertHasNoErrors();

    $nachweis = Reinigungsnachweis::where('reinigungsaufgabe_id', $aufgabe->id)->first();

    expect($nachweis)->not->toBeNull()
        ->and($nachweis->erledigt_am->toDateString())->toBe(today()->toDateString())
        ->and($nachweis->bemerkung)->toBe('Gereinigt mit Desinfektionsmittel');

    $aufgabe->refresh();
    expect($aufgabe->letzte_erledigung_am->toDateString())->toBe(today()->toDateString())
        ->and($aufgabe->faelligkeitsStatus())->toBe('gruen');
});

// ---------------------------------------------------------------------------
// Validierung: Zukunftsdatum abweisen
// ---------------------------------------------------------------------------

it('verwirft Erledigung mit Zukunftsdatum — Validierungsfehler, kein Nachweis', function () {
    $this->actingAs(reinigungsUser($this->tenant->id));

    $aufgabe = Reinigungsaufgabe::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Aufgabe Zukunft',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);

    Livewire::test(Reinigungsplan::class)
        ->set('erledigt_am', today()->addDay()->toDateString())
        ->call('erledigen', $aufgabe->id)
        ->assertHasErrors(['erledigt_am']);

    expect(Reinigungsnachweis::where('reinigungsaufgabe_id', $aufgabe->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Tenant-Isolation: Fremd-Tenant-Aufgabe → findOrFail wirft
// ---------------------------------------------------------------------------

it('erledigen auf Aufgabe eines fremden Tenants wirft ModelNotFoundException', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Reinigung Haus', 'slug' => 'fremdes-reinigung-ui']);

    $fremdeAufgabe = Reinigungsaufgabe::create([
        'tenant_id' => $fremderTenant->id,
        'bezeichnung' => 'Fremde Aufgabe',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);

    $this->actingAs(reinigungsUser($this->tenant->id));

    $this->expectException(ModelNotFoundException::class);

    Livewire::test(Reinigungsplan::class)
        ->set('erledigt_am', today()->toDateString())
        ->call('erledigen', $fremdeAufgabe->id);
});
