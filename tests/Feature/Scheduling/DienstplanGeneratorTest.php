<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Support\DienstplanGenerator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->week = CarbonImmutable::parse('2026-06-08')->startOfWeek()->toDateString(); // Montag

    $this->mitarbeitende = collect([Qualifikation::Pflegefachkraft, Qualifikation::Pflegehilfskraft, Qualifikation::Pflegehilfskraft])
        ->map(function (Qualifikation $q, int $i) {
            $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'MA'.$i]);
            $u->employeeProfile()->create(['tenant_id' => $this->tenant->id, 'qualifikation' => $q, 'wochenstunden' => 38.5]);

            return $u;
        });
    $this->frueh = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00', 'soll_besetzung' => 1, 'aktiv' => true]);
});

it('füllt jeden Tag genau einmal, markiert als Vorschlag und bucht niemanden doppelt', function () {
    $result = app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    $assignments = ShiftAssignment::where('tenant_id', $this->tenant->id)->get();
    expect($assignments)->toHaveCount(7)
        ->and($assignments->every(fn ($a) => $a->auto_generiert))->toBeTrue()
        ->and($result->erstellt)->toBe(7);

    // pro Tag genau 1, pro (user,tag) eindeutig, keiner über 48 h
    expect($assignments->groupBy(fn ($a) => $a->dienst_am->toDateString())->every(fn ($g) => $g->count() === 1))->toBeTrue()
        ->and($assignments->groupBy('user_id')->every(fn ($g) => $g->count() * 8 <= 48))->toBeTrue();
});

it('respektiert Nicht-verfügbar-Wünsche', function () {
    $ma = $this->mitarbeitende->first();
    Dienstwunsch::create(['tenant_id' => $this->tenant->id, 'user_id' => $ma->id, 'datum' => $this->week, 'typ' => WunschTyp::NichtVerfuegbar]);

    app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    expect(ShiftAssignment::where('user_id', $ma->id)->whereDate('dienst_am', $this->week)->exists())->toBeFalse();
});

it('lässt bestehende manuelle Zuweisungen unangetastet und füllt nur offene Slots', function () {
    $ma = $this->mitarbeitende->first();
    ShiftAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $ma->id, 'shift_id' => $this->frueh->id, 'dienst_am' => $this->week, 'auto_generiert' => false]);

    app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    $montag = ShiftAssignment::whereDate('dienst_am', $this->week)->get();
    expect($montag)->toHaveCount(1)
        ->and($montag->first()->auto_generiert)->toBeFalse(); // manuell unverändert
});

it('ist beim erneuten Lauf idempotent (auch am Sonntag, Datetime-Grenze)', function () {
    $gen = app(DienstplanGenerator::class);
    $gen->generate($this->tenant->id, $this->week);
    $erste = ShiftAssignment::where('tenant_id', $this->tenant->id)->count();

    $gen->generate($this->tenant->id, $this->week); // darf nicht in Unique-Konflikt laufen

    expect(ShiftAssignment::where('tenant_id', $this->tenant->id)->count())->toBe($erste)
        ->and(ShiftAssignment::whereDate('dienst_am', CarbonImmutable::parse($this->week)->addDays(6))->count())->toBe(1);
});

it('belegt den Fachkraft-Pflichtplatz je Schicht mit einer Fachkraft', function () {
    PersonalbemessungDefaults::ensureConfig($this->tenant->id)->update(['fachkraftquote_min' => 0.5]);
    $this->frueh->update(['soll_besetzung' => 2]); // fk_req = ceil(2*0,5) = 1

    app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    $fkIds = $this->mitarbeitende->filter(fn ($u) => $u->employeeProfile->qualifikation === Qualifikation::Pflegefachkraft)->pluck('id');
    $proTag = ShiftAssignment::where('tenant_id', $this->tenant->id)->get()->groupBy(fn ($a) => $a->dienst_am->toDateString());
    // jeder voll besetzte Tag (2) enthält mind. eine Fachkraft
    foreach ($proTag as $tag) {
        if ($tag->count() >= 2) {
            expect($tag->pluck('user_id')->intersect($fkIds))->not->toBeEmpty();
        }
    }
});

it('lässt den Fachkraft-Pflichtplatz offen, wenn keine Fachkraft verfügbar ist', function () {
    // alle zu Hilfskräften machen → keine Fachkraft im Haus
    $this->mitarbeitende->each(fn ($u) => $u->employeeProfile->update(['qualifikation' => Qualifikation::Pflegehilfskraft]));
    PersonalbemessungDefaults::ensureConfig($this->tenant->id)->update(['fachkraftquote_min' => 0.5]);
    $this->frueh->update(['soll_besetzung' => 2]);

    $result = app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    expect(collect($result->offeneSlots)->filter(fn ($s) => str_contains($s, 'Fachkraft nötig')))->not->toBeEmpty()
        ->and(ShiftAssignment::where('tenant_id', $this->tenant->id)->get()
            ->groupBy(fn ($a) => $a->dienst_am->toDateString())->every(fn ($t) => $t->count() <= 1))->toBeTrue();
});

it('meldet offene Slots bei Unterdeckung transparent', function () {
    $this->frueh->update(['soll_besetzung' => 5]); // 5 gefordert, nur 3 Mitarbeitende

    $result = app(DienstplanGenerator::class)->generate($this->tenant->id, $this->week);

    expect($result->offeneSlots)->not->toBeEmpty()
        ->and($result->deckung())->toBeLessThan(100);
});
