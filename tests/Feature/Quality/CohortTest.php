<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('enthält nur am Stichtag anwesende Bewohner', function () {
    Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'entlassung_am' => null, 'status' => 'aktiv']); // anwesend
    Resident::factory()->create(['aufnahme_am' => '2026-03-01', 'entlassung_am' => null, 'status' => 'aktiv']); // erst nach Stichtag
    Resident::factory()->create(['aufnahme_am' => '2025-06-01', 'entlassung_am' => '2026-01-10', 'status' => 'entlassen']); // vor Stichtag entlassen

    $cohort = Cohort::atStichtag('2026-02-15');
    expect($cohort->count())->toBe(1);
});
