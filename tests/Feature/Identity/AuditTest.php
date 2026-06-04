<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use Spatie\Activitylog\Models\Activity;

it('protokolliert Änderungen an Domänen-Modellen', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $b = Building::create(['name' => 'Gebäude A']);
    $b->update(['name' => 'Gebäude A2']);

    expect(Activity::where('subject_type', Building::class)->count())->toBeGreaterThanOrEqual(2);
});
