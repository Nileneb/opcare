<?php

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Models\InstrumentItem;
use App\Livewire\Assessment\AssessmentDurchfuehren;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegehilfskraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegehilfskraft');
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->instrument = Instrument::factory()->create();
    $this->item = InstrumentItem::create(['instrument_id' => $this->instrument->id, 'label' => 'Mobilität', 'reihenfolge' => 0]);
    $this->optLow = AssessmentOption::create(['instrument_item_id' => $this->item->id, 'label' => 'immobil', 'punkte' => 1]);
});

it('führt ein Assessment über die UI durch und speichert Score+Band', function () {
    Livewire::test(AssessmentDurchfuehren::class, ['resident' => $this->resident, 'instrument' => $this->instrument])
        ->set("answers.{$this->item->id}", $this->optLow->id)
        ->call('speichern')
        ->assertHasNoErrors();

    $assessment = Assessment::where('resident_id', $this->resident->id)->first();
    expect($assessment)->not->toBeNull()
        ->and($assessment->score)->toBe(1);
});
