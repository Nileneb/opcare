<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\TradeForm;
use App\Livewire\Medication\VerordnungAnlegen;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);

    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $form = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stk', 'teilbar' => true]);
    $this->product = MedProduct::create(['name' => 'Ramipril 5', 'trade_form_id' => $form->id]);
});

afterEach(fn () => Carbon::setTestNow());

it('legt Verordnung + täglichen Stellplan an und generiert Gaben für das Vorlauffenster', function () {
    Livewire::test(VerordnungAnlegen::class, ['resident' => $this->resident])
        ->set('medProductId', $this->product->id)
        ->set('frequenz', 'taeglich')
        ->set('dosis.morgens', 1)
        ->set('dosis.abends', 0)
        ->set('vorlaufTage', 3)
        ->call('speichern')
        ->assertHasNoErrors();

    $rx = Prescription::where('resident_id', $this->resident->id)->first();
    expect($rx)->not->toBeNull()
        ->and($rx->schedules()->count())->toBe(1);

    // 3 Tage × 1 Gabe morgens
    expect(MedicationAdministration::where('resident_id', $this->resident->id)->count())->toBe(3);
});

it('validiert: ohne Produkt UND ohne BHP-Text keine Verordnung', function () {
    Livewire::test(VerordnungAnlegen::class, ['resident' => $this->resident])
        ->set('medProductId', null)
        ->set('bhpText', '')
        ->call('speichern')
        ->assertHasErrors('medProductId');
});

it('lehnt ein Produkt eines FREMDEN Mandanten ab (IDOR via exists-Regel)', function () {
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($tenantB);
    $fremdForm = TradeForm::create(['name' => 'Tablette B', 'einheit' => 'Stk', 'teilbar' => true]);
    $fremdProduct = MedProduct::create(['name' => 'Fremd 5', 'trade_form_id' => $fremdForm->id]);
    app(CurrentTenant::class)->set($this->tenant);

    Livewire::test(VerordnungAnlegen::class, ['resident' => $this->resident])
        ->set('medProductId', $fremdProduct->id)
        ->set('frequenz', 'taeglich')
        ->set('dosis.morgens', 1)
        ->call('speichern')
        ->assertHasErrors('medProductId');

    expect(Prescription::where('resident_id', $this->resident->id)->count())->toBe(0);
});
