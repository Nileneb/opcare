<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('berechnet Inzidenz und Prävalenz eines Indikators', function () {
    $r1 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);
    $r2 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);
    $r3 = Resident::factory()->create(['aufnahme_am' => '2026-01-01']);

    // r1: Dekubitus im Zeitraum, noch nicht behoben (zählt für Prävalenz am Stichtag)
    CareEvent::create(['resident_id' => $r1->id, 'indicator' => QualityIndicator::Dekubitus, 'datum' => '2026-02-10']);
    // r2: Dekubitus im Zeitraum, vor Stichtag behoben (zählt für Inzidenz, NICHT Prävalenz)
    CareEvent::create(['resident_id' => $r2->id, 'indicator' => QualityIndicator::Dekubitus, 'datum' => '2026-02-01', 'behoben_am' => '2026-02-05']);

    $svc = app(IndicatorService::class);
    $cohort = Cohort::atStichtag('2026-02-15');

    $inz = $svc->incidence(QualityIndicator::Dekubitus, '2026-02-01', '2026-02-28', $cohort);
    $prev = $svc->prevalence(QualityIndicator::Dekubitus, $cohort);

    expect($inz->betroffene)->toBe(2)->and($inz->kohorte)->toBe(3)
        ->and($prev->betroffene)->toBe(1);
});
