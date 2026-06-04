<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Database\Seeders\ShiftSeeder;
use App\Domains\Scheduling\Models\Shift;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet drei Standard-Schichten idempotent und deckt alle sechs Tageszeiten ab', function () {
    $this->seed(ShiftSeeder::class);
    $this->seed(ShiftSeeder::class);

    expect(Shift::count())->toBe(3);

    $slots = Shift::all()->flatMap(fn ($s) => array_keys($s->timeslots ?? []))->unique()->values()->all();
    expect($slots)->toContain('nacht_mo', 'morgens', 'mittags', 'nachmittags', 'abends', 'nacht_ab');
});
