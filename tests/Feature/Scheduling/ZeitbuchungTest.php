<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\Enums\Pausenstatus;
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

// ---------------------------------------------------------------------------
// § 4 ArbZG Ruhepausen — prüfbar, weil die Pause erfasst ist
// ---------------------------------------------------------------------------

it('§ 4: bis 6 h Brutto keine Pausenpflicht (nicht relevant)', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00', 'ende' => '14:00', 'pause_minuten' => 0]); // genau 6 h

    expect($b->erforderlichePauseMinuten())->toBe(0)
        ->and($b->pausenStatus())->toBe(Pausenstatus::NichtRelevant);
});

it('§ 4: über 6 h verlangt 30 min — konform bei 30, unzureichend bei 20', function () {
    $ok = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00', 'ende' => '15:00', 'pause_minuten' => 30]); // 7 h
    $zuKurz = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-09', 'beginn' => '08:00', 'ende' => '15:00', 'pause_minuten' => 20]);

    expect($ok->erforderlichePauseMinuten())->toBe(30)
        ->and($ok->pausenStatus())->toBe(Pausenstatus::Konform)
        ->and($zuKurz->pausenStatus())->toBe(Pausenstatus::Unzureichend);
});

it('§ 4: über 9 h verlangt 45 min', function () {
    $zuKurz = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '07:00', 'ende' => '17:00', 'pause_minuten' => 30]); // 10 h, 30 < 45
    $ok = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-09', 'beginn' => '07:00', 'ende' => '17:00', 'pause_minuten' => 45]);

    expect($zuKurz->erforderlichePauseMinuten())->toBe(45)
        ->and($zuKurz->pausenStatus())->toBe(Pausenstatus::Unzureichend)
        ->and($ok->pausenStatus())->toBe(Pausenstatus::Konform);
});

it('§ 4: Nachtschicht über Mitternacht wird brutto korrekt bemessen', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '20:00', 'ende' => '06:00', 'pause_minuten' => 30]); // 10 h brutto → 45 nötig

    expect($b->bruttoMinuten())->toBe(600)
        ->and($b->erforderlichePauseMinuten())->toBe(45)
        ->and($b->pausenStatus())->toBe(Pausenstatus::Unzureichend);
});

it('§ 4: laufende Buchung hat Status läuft', function () {
    $b = Zeitbuchung::create(['user_id' => $this->user->id, 'datum' => '2026-06-08', 'beginn' => '08:00']);

    expect($b->pausenStatus())->toBe(Pausenstatus::Laeuft);
});
