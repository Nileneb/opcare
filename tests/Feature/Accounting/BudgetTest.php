<?php

use App\Domains\Accounting\Actions\Buchen;
use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Buchung;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Accounting\Support\BudgetStatus;
use App\Domains\Accounting\Support\KontoBudgetMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\Buchhaltung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('buchhaltung');
    $this->kasse = Konto::create(['nummer' => '1000', 'name' => 'Kasse', 'typ' => KontoTyp::Aktiv]);
    $this->aufwand = Konto::create(['nummer' => '6000', 'name' => 'Küche-Aufwand', 'typ' => KontoTyp::Aufwand]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
    $this->monat = today()->toDateString();
});

it('berechnet den Monatsverbrauch eines Kontos in natürlicher Richtung', function () {
    app(Buchen::class)->handle($this->aufwand->id, $this->kasse->id, 30.0, 'a', $this->monat);
    app(Buchen::class)->handle($this->aufwand->id, $this->kasse->id, 20.0, 'b', $this->monat);

    expect(app(KontoBudgetMonitor::class)->verbraucht($this->aufwand, $this->monat))->toBe(50.0);
});

it('bildet Ampel und Sperre generisch über BudgetGrenze ab', function () {
    $budget = new Budget(['limit_betrag' => 100, 'warn_prozent' => 80, 'sperre' => true]);

    expect((new BudgetStatus($budget, 50.0))->ampel())->toBe('gruen')
        ->and((new BudgetStatus($budget, 80.0))->ampel())->toBe('gelb')
        ->and((new BudgetStatus($budget, 100.0))->ampel())->toBe('rot')
        ->and((new BudgetStatus($budget, 90.0))->istGesperrt(20.0))->toBeTrue()   // 90+20 > 100
        ->and((new BudgetStatus($budget, 90.0))->istGesperrt(5.0))->toBeFalse();  // 90+5 <= 100
});

it('blockiert eine freie Buchung über ein gesperrtes Budget', function () {
    Budget::create(['tenant_id' => $this->tenant->id, 'konto_id' => $this->aufwand->id, 'limit_betrag' => 50, 'warn_prozent' => 80, 'sperre' => true]);

    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('b_soll', $this->aufwand->id)->set('b_haben', $this->kasse->id)
        ->set('b_betrag', 60.0)->set('b_text', 'Einkauf')->set('b_datum', $this->monat)
        ->call('freieBuchung')->assertHasErrors('b_betrag');

    expect(Buchung::count())->toBe(0);
});

it('bucht über ein Warn-Budget (weich) trotz Überschreitung', function () {
    Budget::create(['tenant_id' => $this->tenant->id, 'konto_id' => $this->aufwand->id, 'limit_betrag' => 50, 'warn_prozent' => 80, 'sperre' => false]);

    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('b_soll', $this->aufwand->id)->set('b_haben', $this->kasse->id)
        ->set('b_betrag', 60.0)->set('b_text', 'Einkauf')->set('b_datum', $this->monat)
        ->call('freieBuchung')->assertHasNoErrors();

    expect(Buchung::count())->toBe(1);
});

it('setzt und entfernt ein Konto-Budget über die Buchhaltung', function () {
    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('bg_konto', $this->aufwand->id)->set('bg_limit', 500.0)->set('bg_warn', 75)->set('bg_sperre', true)
        ->call('budgetSetzen')->assertHasNoErrors();

    $budget = Budget::where('konto_id', $this->aufwand->id)->first();
    expect($budget)->not->toBeNull()
        ->and((float) $budget->limit_betrag)->toBe(500.0)
        ->and($budget->sperre)->toBeTrue();

    Livewire::test(Buchhaltung::class)->call('budgetLoeschen', $this->aufwand->id);
    expect(Budget::where('konto_id', $this->aufwand->id)->exists())->toBeFalse();
});
