<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Models\MedicationAdministration;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('legt eine geplante Gabe an', function () {
    $resident = Resident::factory()->create();
    $a = MedicationAdministration::create([
        'resident_id' => $resident->id,
        'soll_zeitpunkt' => now()->setTime(8, 0),
        'tageszeit' => AdministrationTimeslot::Morgens,
        'dosis' => 1,
        'status' => AdministrationStatus::Geplant,
    ]);

    expect($a->status)->toBe(AdministrationStatus::Geplant)
        ->and($a->tageszeit)->toBe(AdministrationTimeslot::Morgens);
});
