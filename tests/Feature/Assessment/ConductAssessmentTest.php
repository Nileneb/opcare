<?php

use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Models\InstrumentItem;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->instrument = Instrument::factory()->create(['intervall_tage' => 30]);
    // zwei Items mit je zwei Optionen (Braden: niedrig = schlecht)
    foreach (['Mobilität', 'Feuchtigkeit'] as $i => $label) {
        $item = InstrumentItem::create(['instrument_id' => $this->instrument->id, 'label' => $label, 'reihenfolge' => $i]);
        AssessmentOption::create(['instrument_item_id' => $item->id, 'label' => 'stark eingeschränkt', 'punkte' => 1]);
        AssessmentOption::create(['instrument_item_id' => $item->id, 'label' => 'normal', 'punkte' => 4]);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('berechnet Score + Band, persistiert Antworten und setzt die Fälligkeit', function () {
    $items = $this->instrument->items()->with('options')->get();
    // beide Items „stark eingeschränkt" (1 Punkt) → Score 2 → sehr hohes Risiko (max 9)
    $answers = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->first()->id])->all();

    $assessment = (new ConductAssessment)->handle(new AssessmentInputData(
        resident_id: $this->resident->id,
        instrument_id: $this->instrument->id,
        created_by: $this->user->id,
        answers: $answers,
    ));

    expect($assessment->score)->toBe(2)
        ->and($assessment->risk_band)->toBe(RiskBand::SehrHoch)
        ->and($assessment->answers()->count())->toBe(2)
        ->and($assessment->faellig_am->toDateString())->toBe('2026-07-15'); // +30 Tage
});

it('revidiert ein Assessment append-only (neue Version, alte abgelöst)', function () {
    $items = $this->instrument->items()->with('options')->get();
    $low = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->first()->id])->all();
    $high = $items->mapWithKeys(fn ($item) => [$item->id => $item->options->last()->id])->all();

    $action = new ConductAssessment;
    $v1 = $action->handle(new AssessmentInputData($this->resident->id, $this->instrument->id, $this->user->id, $low));
    $v2 = (new \App\Domains\Assessment\Actions\ReviseAssessment)->handle($v1, new AssessmentInputData(
        $this->resident->id, $this->instrument->id, $this->user->id, $high,
    ));

    expect($v2->version)->toBe(2)
        ->and($v1->fresh()->isSuperseded())->toBeTrue()
        ->and(Assessment::current()->where('resident_id', $this->resident->id)->count())->toBe(1)
        ->and($v2->score)->toBe(8);
});
