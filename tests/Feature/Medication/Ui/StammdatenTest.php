<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use App\Livewire\Medication\Stammdaten;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['pflegefachkraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $this->form = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stk', 'teilbar' => true]);
});

it('verweigert reines Leserecht das Anlegen eines Produkts', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('leserecht');
    $this->actingAs($u);

    Livewire::test(Stammdaten::class)->assertForbidden();
});

it('legt ein Medikationsprodukt an', function () {
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $u->assignRole('pflegefachkraft');
    $this->actingAs($u);

    Livewire::test(Stammdaten::class)
        ->set('name', 'Ibuprofen 400')
        ->set('wirkstoff', 'Ibuprofen')
        ->set('staerke', '400 mg')
        ->set('tradeFormId', $this->form->id)
        ->set('btm', false)
        ->call('speichern')
        ->assertHasNoErrors();

    expect(MedProduct::where('name', 'Ibuprofen 400')->where('wirkstoff', 'Ibuprofen')->exists())->toBeTrue();
});
