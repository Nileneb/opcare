<?php

use App\Domains\Assessment\Actions\EscalateToQuality;
use App\Domains\Assessment\Actions\SyncRiskItem;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sis = SisAssessment::create([
        'resident_id' => $this->resident->id, 'created_by' => $this->user->id,
        'erstellt_am' => now()->toDateString(), 'status' => 'aktiv',
    ]);
    $this->instrument = Instrument::factory()->create(['risk_type' => RiskType::Dekubitus]);
});

it('setzt das passende SIS-RiskItem aus einem kritischen Assessment', function () {
    $assessment = Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 8, 'risk_band' => RiskBand::SehrHoch, 'created_by' => $this->user->id,
        'durchgefuehrt_am' => now()->toDateString(),
    ]);

    (new SyncRiskItem)->handle($assessment);

    $risk = RiskItem::where('sis_assessment_id', $this->sis->id)->where('risiko', RiskType::Dekubitus)->first();
    expect($risk)->not->toBeNull()
        ->and($risk->eingeschaetzt)->toBeTrue();
});

it('gibt null zurück wenn keine aktive SisAssessment existiert', function () {
    $this->sis->forceFill(['superseded_by' => $this->sis->id])->save();

    $assessment = Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 8, 'risk_band' => RiskBand::SehrHoch, 'created_by' => $this->user->id,
        'durchgefuehrt_am' => now()->toDateString(),
    ]);

    $result = (new SyncRiskItem)->handle($assessment);

    expect($result)->toBeNull();
});

it('eskaliert ein kritisches Assessment zu einem CareEvent (QualityIndicator::Dekubitus)', function () {
    $assessment = Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 8, 'risk_band' => RiskBand::SehrHoch, 'created_by' => $this->user->id,
        'durchgefuehrt_am' => now()->toDateString(),
    ]);

    $event = (new EscalateToQuality)->handle($assessment);

    expect($event)->not->toBeNull()
        ->and($event)->toBeInstanceOf(CareEvent::class)
        ->and($event->indicator)->toBe(QualityIndicator::Dekubitus)
        ->and($event->severity)->toBe(EventSeverity::Schwer);
});

it('eskaliert nicht bei nicht-kritischem Band', function () {
    $assessment = Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 20, 'risk_band' => RiskBand::Kein, 'created_by' => $this->user->id,
        'durchgefuehrt_am' => now()->toDateString(),
    ]);

    $result = (new EscalateToQuality)->handle($assessment);

    expect($result)->toBeNull();
});
