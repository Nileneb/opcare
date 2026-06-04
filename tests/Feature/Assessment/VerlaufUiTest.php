<?php

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Livewire\Assessment\AssessmentVerlauf;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->instrument = Instrument::factory()->create();
});

it('zeigt das aktuelle Assessment je Instrument und die verfügbaren Instrumente', function () {
    Assessment::factory()->create([
        'resident_id' => $this->resident->id, 'instrument_id' => $this->instrument->id,
        'score' => 11, 'risk_band' => RiskBand::Hoch, 'created_by' => $this->user->id,
    ]);

    Livewire::test(AssessmentVerlauf::class, ['resident' => $this->resident])
        ->assertSee('Braden-Skala')
        ->assertSee(RiskBand::Hoch->label());
});
