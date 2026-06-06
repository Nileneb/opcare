<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\BtmBuchen;
use App\Domains\Medication\Enums\BtmVorgang;
use App\Domains\Medication\Models\BtmBuchung;
use App\Domains\Medication\Models\BtmKonto;
use App\Livewire\Medication\BtmNachweis;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pfk->assignRole('pflegefachkraft');
    $this->resident = Resident::factory()->create(['status' => 'aktiv']);
    $this->konto = BtmKonto::create(['tenant_id' => $this->tenant->id, 'resident_id' => $this->resident->id,
        'substanz' => 'Morphin', 'staerke' => '10 mg/ml', 'einheit' => 'ml', 'arzt_name' => 'Dr. Meier', 'eroeffnet_am' => today()]);
});

it('schreibt den Bestand fort und nummeriert append-only', function () {
    $action = app(BtmBuchen::class);
    $action->handle($this->konto, BtmVorgang::Lieferung, 20, today()->toDateString(), ['lieferant' => 'Apotheke', 'arzt_name' => 'Dr. Meier']);
    $action->handle($this->konto, BtmVorgang::Gabe, 4, today()->toDateString(), ['durchgefuehrt_von' => $this->pfk->id]);

    $buchungen = BtmBuchung::where('btm_konto_id', $this->konto->id)->orderBy('lfd_nr')->get();
    expect($buchungen)->toHaveCount(2)
        ->and($buchungen[0]->lfd_nr)->toBe(1)->and((float) $buchungen[0]->bestand_nach)->toBe(20.0)
        ->and($buchungen[1]->lfd_nr)->toBe(2)->and((float) $buchungen[1]->bestand_nach)->toBe(16.0)
        ->and($this->konto->fresh()->bestand())->toBe(16.0);
});

it('lehnt einen Abgang über den Bestand hinaus ab', function () {
    expect(fn () => app(BtmBuchen::class)->handle($this->konto, BtmVorgang::Gabe, 5, today()->toDateString()))
        ->toThrow(InvalidArgumentException::class);
});

it('verlangt zwei Zeugen bei der Vernichtung', function () {
    app(BtmBuchen::class)->handle($this->konto, BtmVorgang::Lieferung, 10, today()->toDateString());
    expect(fn () => app(BtmBuchen::class)->handle($this->konto, BtmVorgang::Vernichtung, 2, today()->toDateString(), ['zeuge_1' => 'A']))
        ->toThrow(InvalidArgumentException::class);

    $b = app(BtmBuchen::class)->handle($this->konto, BtmVorgang::Vernichtung, 2, today()->toDateString(), ['zeuge_1' => 'A', 'zeuge_2' => 'B', 'vernichtungsmethode' => 'verascht']);
    expect((float) $b->bestand_nach)->toBe(8.0);
});

it('bucht und schließt den Monat über die UI; verlangt Differenz-Begründung', function () {
    $this->actingAs($this->pfk);

    $comp = Livewire::test(BtmNachweis::class)
        ->set('selected', $this->konto->id)
        ->set('b_vorgang', 'lieferung')->set('b_menge', 30)->set('b_datum', today()->toDateString())
        ->call('buchen')->assertHasNoErrors();

    // Ist weicht ab → Begründung Pflicht
    $comp->set('ab_monat', today()->startOfMonth()->toDateString())->set('ab_ist', 28)->set('ab_geprueft_von', 'Dr. Meier')->set('ab_notiz', '')
        ->call('monatsabschluss')->assertHasErrors('ab_notiz');

    $comp->set('ab_notiz', 'Zähldifferenz, Nachverfolgung läuft')->call('monatsabschluss')->assertHasNoErrors();
    expect($this->konto->abschluesse()->count())->toBe(1)
        ->and($this->konto->abschluesse()->first()->gesperrt_am)->not->toBeNull();
});

it('verwehrt den Zugriff ohne Pflegefachrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(BtmNachweis::class)->assertForbidden();
});
