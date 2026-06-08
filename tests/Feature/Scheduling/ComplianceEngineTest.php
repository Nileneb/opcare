<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\ComplianceReporter;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pflegekraft A']);
    $this->rules = ArbeitszeitgesetzDefaults::ensureFor($this->tenant->id);
});

function dienst(int $tenantId, int $userId, string $name, string $beginn, string $ende, string $datum): void
{
    $shift = Shift::create(['tenant_id' => $tenantId, 'name' => $name, 'kind' => ShiftKind::Frueh, 'beginn' => $beginn, 'ende' => $ende, 'aktiv' => true]);
    ShiftAssignment::create(['tenant_id' => $tenantId, 'user_id' => $userId, 'shift_id' => $shift->id, 'dienst_am' => $datum]);
}

function befunde($self): array
{
    $assignments = ShiftAssignment::with(['user', 'shift'])->get();

    return app(WorkingHoursAnalyzer::class)->analyze($assignments, $self->rules);
}

it('seedet die ableitbaren ArbZG-Regeln idempotent (inkl. § 14)', function () {
    $first = ArbeitszeitgesetzDefaults::ensureFor($this->tenant->id);
    $again = ArbeitszeitgesetzDefaults::ensureFor($this->tenant->id);

    expect($first)->toHaveCount(6)
        ->and($again)->toHaveCount(6)
        ->and($first->pluck('key'))->toContain('tageshoechstarbeitszeit', 'ruhezeit', 'wochenarbeitszeit', 'sonntagsruhe', 'ruhepausen', 'notfall_ausnahme')
        ->and($first->firstWhere('key', 'tageshoechstarbeitszeit')->gesetz_url)->toContain('gesetze-im-internet.de/arbzg/__3');
});

it('§ 3: meldet einen Verstoß bei mehr als 10 h Tagesarbeitszeit', function () {
    dienst($this->tenant->id, $this->user->id, 'Langdienst', '06:00', '19:00', '2026-06-08'); // 13 h
    $f = collect(befunde($this))->firstWhere('ruleKey', 'tageshoechstarbeitszeit');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(ViolationSeverity::Verstoss)
        ->and($f->message)->toContain('13')
        ->and($f->gesetzUrl)->toContain('__3.html');
});

it('§ 3: meldet nur einen Hinweis zwischen 8 und 10 h', function () {
    dienst($this->tenant->id, $this->user->id, 'Neundienst', '06:00', '15:00', '2026-06-08'); // 9 h
    $f = collect(befunde($this))->firstWhere('ruleKey', 'tageshoechstarbeitszeit');

    expect($f->severity)->toBe(ViolationSeverity::Hinweis);
});

it('§ 5: meldet einen Verstoß bei unter 10 h Ruhezeit zwischen zwei Diensten', function () {
    dienst($this->tenant->id, $this->user->id, 'Spät', '14:00', '22:00', '2026-06-08'); // Mo Ende 22:00
    dienst($this->tenant->id, $this->user->id, 'Früh', '06:00', '14:00', '2026-06-09'); // Di Beginn 06:00 → 8 h Ruhe
    $f = collect(befunde($this))->firstWhere('ruleKey', 'ruhezeit');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(ViolationSeverity::Verstoss)
        ->and($f->message)->toContain('8');
});

it('behandelt Nachtdienste über Mitternacht als zusammenhängendes Intervall', function () {
    dienst($this->tenant->id, $this->user->id, 'Nacht', '22:00', '06:00', '2026-06-08'); // 8 h, nicht 16 h
    $f = collect(befunde($this))->firstWhere('ruleKey', 'tageshoechstarbeitszeit');

    expect($f)->toBeNull(); // 8 h → kein Hinweis (>8 strikt), kein Verstoß
});

it('§§ 9–11: weist Sonntagsdienst als Hinweis aus (Pflege-Ausnahme)', function () {
    dienst($this->tenant->id, $this->user->id, 'Sonntag', '06:00', '14:00', '2026-06-07'); // So
    $f = collect(befunde($this))->firstWhere('ruleKey', 'sonntagsruhe');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(ViolationSeverity::Hinweis)
        ->and($f->gesetzUrl)->toContain('__10.html');
});

it('§ 4: verweist im Plan auf die Pausenprüfung in der Zeiterfassung', function () {
    dienst($this->tenant->id, $this->user->id, 'Achtdienst', '06:00', '14:00', '2026-06-08'); // 8 h > 6 h
    $f = collect(befunde($this))->firstWhere('ruleKey', 'ruhepausen');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(ViolationSeverity::Hinweis)
        ->and($f->message)->toContain('Zeiterfassung');
});

it('wertet eine deaktivierte Regel nicht aus', function () {
    $this->rules->firstWhere('key', 'tageshoechstarbeitszeit')->update(['aktiv' => false]);
    dienst($this->tenant->id, $this->user->id, 'Langdienst', '06:00', '19:00', '2026-06-08');
    $this->rules = ComplianceRule::all();

    expect(collect(befunde($this))->firstWhere('ruleKey', 'tageshoechstarbeitszeit'))->toBeNull();
});

it('respektiert editierte Schwellwerte (max_stunden 12 → 13 h kein Verstoß mehr)', function () {
    $rule = $this->rules->firstWhere('key', 'tageshoechstarbeitszeit');
    $rule->update(['params' => ['max_stunden' => 13, 'hinweis_ab_stunden' => 8]]);
    dienst($this->tenant->id, $this->user->id, 'Langdienst', '06:00', '19:00', '2026-06-08'); // 13 h
    $this->rules = ComplianceRule::all();
    $f = collect(befunde($this))->firstWhere('ruleKey', 'tageshoechstarbeitszeit');

    expect($f->severity)->toBe(ViolationSeverity::Hinweis); // 13 ≤ 13, aber > 8
});

it('annotiert einen Verstoß mit einer dokumentierten § 14-Begründung (bleibt Verstoß)', function () {
    dienst($this->tenant->id, $this->user->id, 'Langdienst', '06:00', '19:00', '2026-06-08');
    ComplianceJustification::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'rule_key' => 'tageshoechstarbeitszeit',
        'datum' => '2026-06-08', 'grund' => 'Nachfolgekraft nicht erschienen', 'begruendet_von' => $this->user->id,
    ]);
    $assignments = ShiftAssignment::with(['user', 'shift'])->get();
    $findings = app(ComplianceReporter::class)->findings($assignments, $this->rules, ComplianceJustification::with('begruender')->get());
    $f = collect($findings)->firstWhere('ruleKey', 'tageshoechstarbeitszeit');

    expect($f->severity)->toBe(ViolationSeverity::Verstoss)
        ->and($f->istBegruendet())->toBeTrue()
        ->and($f->offenerVerstoss())->toBeFalse()
        ->and($f->begruendung)->toBe('Nachfolgekraft nicht erschienen')
        ->and($f->begruendetVon)->toBe('Pflegekraft A');
});
