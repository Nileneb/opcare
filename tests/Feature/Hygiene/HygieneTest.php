<?php

use App\Domains\Hygiene\Enums\BefundArt;
use App\Domains\Hygiene\Enums\Erreger;
use App\Domains\Hygiene\Models\Hygieneplan;
use App\Domains\Hygiene\Models\InfektionsBefund;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Hygiene\Hygiene;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Haus H', 'slug' => 'haus-h']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pdl = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pdl->assignRole('pflegefachkraft');
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('erkennt MRE und meldepflichtige Erreger', function () {
    expect(Erreger::Mrsa->istMre())->toBeTrue()
        ->and(Erreger::CDifficile->istMre())->toBeFalse()
        ->and(Erreger::Norovirus->meldeRelevant())->toBeTrue()
        ->and(Erreger::Mrsa->meldeRelevant())->toBeFalse()
        ->and(BefundArt::NosokomialeInfektion->bewertungspflichtig())->toBeTrue();
});

it('führt den Hygieneplan als Dokument-mit-Freigabe mit Revisions-Ampel', function () {
    $this->actingAs($this->pdl);
    Livewire::test(Hygiene::class)
        ->set('p_titel', 'Hygieneplan WB1')->set('p_version', '1.0')->set('p_intervall', 12)
        ->call('planAnlegen')->assertHasNoErrors();

    $plan = Hygieneplan::first();
    expect($plan->status())->toBe('entwurf')->and($plan->ampel())->toBe('red');

    Livewire::test(Hygiene::class)->call('planFreigeben', $plan->id);
    $plan->refresh();
    expect($plan->freigegeben_am)->not->toBeNull()
        ->and($plan->freigegeben_von)->toBe($this->pdl->id)
        ->and($plan->ampel())->toBe('green');

    $plan->update(['freigegeben_am' => today()->subMonths(13)]);
    expect($plan->fresh()->status())->toBe('ueberfaellig');
});

it('nimmt einen Befund in die Surveillance auf und verfolgt die Meldepflicht', function () {
    $this->actingAs($this->pdl);
    Livewire::test(Hygiene::class)
        ->set('b_resident', $this->resident->id)
        ->set('b_erreger', 'norovirus')
        ->set('b_art', 'nosokomiale_infektion')
        ->set('b_festgestellt', today()->toDateString())
        ->call('befundErfassen')->assertHasNoErrors();

    $b = InfektionsBefund::first();
    expect($b->meldepflichtig)->toBeTrue()
        ->and($b->meldungOffen())->toBeTrue()
        ->and($b->aktiv())->toBeTrue()
        ->and($b->ampel())->toBe('red');

    Livewire::test(Hygiene::class)->call('befundGemeldet', $b->id);
    expect($b->fresh()->meldungOffen())->toBeFalse()->and($b->fresh()->ampel())->toBe('amber');

    Livewire::test(Hygiene::class)->call('befundAufheben', $b->id);
    expect($b->fresh()->aktiv())->toBeFalse()->and($b->fresh()->ampel())->toBe('green');
});

it('schlägt die Meldepflicht aus dem gewählten Erreger vor', function () {
    $this->actingAs($this->pdl);
    Livewire::test(Hygiene::class)
        ->set('b_erreger', 'mrsa')->assertSet('b_meldepflichtig', false)
        ->set('b_erreger', 'tuberkulose')->assertSet('b_meldepflichtig', true);
});

it('scoped Befunde tenant-übergreifend ab (IDOR)', function () {
    $this->actingAs($this->pdl);
    $fremd = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd']);
    $fremderResident = Resident::factory()->create(['tenant_id' => $fremd->id]);

    Livewire::test(Hygiene::class)
        ->set('b_resident', $fremderResident->id)->set('b_erreger', 'mrsa')->set('b_art', 'besiedlung')
        ->set('b_festgestellt', today()->toDateString())
        ->call('befundErfassen')->assertHasErrors('b_resident');
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Hygiene::class)->assertForbidden();
});
