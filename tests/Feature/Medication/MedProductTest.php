<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;

beforeEach(function () {
    app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a']));
});

it('legt ein Medikament mit Darreichungsform an', function () {
    $tf = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stück', 'teilbar' => true]);
    $p = MedProduct::create(['name' => 'Ramipril', 'wirkstoff' => 'Ramipril', 'staerke' => '5 mg', 'trade_form_id' => $tf->id, 'btm' => false]);

    expect($p->tradeForm->name)->toBe('Tablette')->and($p->btm)->toBeFalse();
});
