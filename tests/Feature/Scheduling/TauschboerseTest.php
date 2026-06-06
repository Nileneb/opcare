<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Scheduling\Enums\AbwesenheitTyp;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\ShiftSwapRequest;
use App\Domains\Scheduling\Support\DienstplanGenerator;
use App\Domains\Scheduling\Support\ShiftCoverageService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->a = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'A']);
    $this->b = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'B']);
    $this->frueh = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00', 'aktiv' => true]);
    $this->tag = CarbonImmutable::parse('2026-06-10')->toDateString();
    $this->assignment = ShiftAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->a->id, 'shift_id' => $this->frueh->id, 'dienst_am' => $this->tag]);
});

it('öffnet bei Krankmeldung die betroffenen Dienste als Vertretung', function () {
    app(ShiftCoverageService::class)->krankmelden($this->a, AbwesenheitTyp::Krank, $this->tag, $this->tag, null, $this->a->id);

    $req = ShiftSwapRequest::where('shift_assignment_id', $this->assignment->id)->first();
    expect($req)->not->toBeNull()->and($req->typ)->toBe('krankheit')->and($req->status)->toBe('offen');
});

it('überträgt einen übernommenen Dienst auf die übernehmende Person', function () {
    $req = app(ShiftCoverageService::class)->tauschAnbieten($this->assignment, null);
    app(ShiftCoverageService::class)->uebernehmen($req, $this->b);

    expect($this->assignment->fresh()->user_id)->toBe($this->b->id)
        ->and($req->fresh()->status)->toBe('uebernommen')
        ->and($req->fresh()->uebernommen_von)->toBe($this->b->id);
});

it('lehnt die Übernahme ab, wenn die Person am selben Tag schon eingeteilt ist', function () {
    ShiftAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->b->id, 'shift_id' => $this->frueh->id, 'dienst_am' => $this->tag]);
    $req = app(ShiftCoverageService::class)->tauschAnbieten($this->assignment, null);

    expect(fn () => app(ShiftCoverageService::class)->uebernehmen($req, $this->b))->toThrow(InvalidArgumentException::class);
});

it('plant abwesende Mitarbeitende nicht ein (Generator)', function () {
    $this->a->employeeProfile()->create(['tenant_id' => $this->tenant->id, 'qualifikation' => Qualifikation::Pflegefachkraft, 'wochenstunden' => 38.5]);
    $week = CarbonImmutable::parse('2026-06-08')->startOfWeek()->toDateString(); // Mo 08.06., enthält 10.06.
    app(ShiftCoverageService::class)->krankmelden($this->a, AbwesenheitTyp::Krank, '2026-06-08', '2026-06-14', null, $this->a->id);

    app(DienstplanGenerator::class)->generate($this->tenant->id, $week);

    // A ist die ganze Woche krank → bekommt keine auto-generierten Dienste
    expect(ShiftAssignment::where('user_id', $this->a->id)->where('auto_generiert', true)->count())->toBe(0);
});
