<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\HealthInsurance;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentInsurance;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('ordnet einem Bewohner eine primäre Kasse zu', function () {
    $kasse = HealthInsurance::create(['name' => 'AOK', 'ik_nummer' => '101570104']);
    $resident = Resident::factory()->create();

    $ri = ResidentInsurance::create([
        'resident_id' => $resident->id,
        'health_insurance_id' => $kasse->id,
        'versichertennr' => 'X123',
        'ist_primaer' => true,
    ]);

    expect($resident->insurances)->toHaveCount(1)
        ->and($ri->healthInsurance->name)->toBe('AOK')
        ->and($ri->ist_primaer)->toBeTrue();
});
