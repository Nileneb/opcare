<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\ScheduleQualityAnalyzer;
use App\Domains\Scheduling\Compliance\ScheduleQualityDefaults;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ScheduleQualityRule;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->frueh = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00', 'aktiv' => true]);
    $this->spaet = Shift::create(['name' => 'Spät', 'kind' => ShiftKind::Spaet, 'beginn' => '14:00', 'ende' => '22:00', 'aktiv' => true]);
    $this->nacht = Shift::create(['name' => 'Nacht', 'kind' => ShiftKind::Nacht, 'beginn' => '22:00', 'ende' => '06:00', 'aktiv' => true]);
    $this->rules = ScheduleQualityDefaults::ensureFor($this->tenant->id);
    $this->dates = collect(range(0, 6))->map(fn ($i) => CarbonImmutable::parse('2026-06-08')->addDays($i)->toDateString())->all();
});

function assign($user, $shift, $datum): void
{
    ShiftAssignment::create(['user_id' => $user->id, 'shift_id' => $shift->id, 'dienst_am' => $datum]);
}

it('erkennt einen Quick Return (Spät → Früh, < 16 h Ruhe)', function () {
    assign($this->user, $this->spaet, $this->dates[0]); // Mo Spät endet 22:00
    assign($this->user, $this->frueh, $this->dates[1]); // Di Früh beginnt 06:00 → 8 h Ruhe

    $a = ShiftAssignment::with(['user', 'shift'])->get();
    $findings = app(ScheduleQualityAnalyzer::class)->findings($a, $this->rules, $this->dates);

    $keys = collect($findings)->pluck('ruleKey');
    expect($keys)->toContain('quick-return')->toContain('vorwaerts-rotation');
});

it('erkennt mehr als drei Nachtdienste in Folge', function () {
    foreach (range(0, 3) as $i) {
        assign($this->user, $this->nacht, $this->dates[$i]); // 4 Nächte
    }
    $a = ShiftAssignment::with(['user', 'shift'])->get();
    $findings = app(ScheduleQualityAnalyzer::class)->findings($a, $this->rules, $this->dates);

    expect(collect($findings)->pluck('ruleKey'))->toContain('max-folge-nachtdienste');
});

it('respektiert das Abschalten einer Regel', function () {
    $this->rules->firstWhere('key', 'quick-return')->update(['aktiv' => false]);
    assign($this->user, $this->spaet, $this->dates[0]);
    assign($this->user, $this->frueh, $this->dates[1]);

    $a = ShiftAssignment::with(['user', 'shift'])->get();
    $rules = ScheduleQualityRule::where('tenant_id', $this->tenant->id)->get();
    $findings = app(ScheduleQualityAnalyzer::class)->findings($a, $rules, $this->dates);

    expect(collect($findings)->pluck('ruleKey'))->not->toContain('quick-return');
});
