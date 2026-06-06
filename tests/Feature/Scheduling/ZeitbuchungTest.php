<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Models\Zeitbuchung;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('rechnet Ist-Stunden inkl. Pause korrekt', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00', 'ende' => '16:00', 'pause_minuten' => 30]);

    expect($b->istStunden())->toBe(7.5)
        ->and($b->laeuft())->toBeFalse();
});

it('zählt Nachtschichten über Mitternacht korrekt', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '22:00', 'ende' => '06:00', 'pause_minuten' => 0]);

    expect($b->istStunden())->toBe(8.0);
});

it('kennt eine laufende (eingestempelte) Buchung ohne Ist-Stunden', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00']);

    expect($b->laeuft())->toBeTrue()
        ->and($b->istStunden())->toBeNull();
});

it('ist mandantengetrennt', function () {
    Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00', 'ende' => '16:00']);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(Zeitbuchung::count())->toBe(0);
});
