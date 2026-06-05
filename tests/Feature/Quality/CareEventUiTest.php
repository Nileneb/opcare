<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Livewire\ResidentShow;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->resident = Resident::factory()->create();
    $this->makeUser = function (string $role) use ($t) {
        $u = User::factory()->create(['tenant_id' => $t->id]);
        $u->assignRole($role);

        return $u;
    };
});

it('dokumentiert ein Vorkommnis über die UI', function () {
    Livewire::actingAs(($this->makeUser)('pflegefachkraft'))->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'sturz')
        ->set('ce_datum', '2026-06-01')
        ->set('ce_severity', 'mittel')
        ->set('ce_notiz', 'Sturz im Bad')
        ->call('recordCareEvent')
        ->assertHasNoErrors();

    $event = CareEvent::where('resident_id', $this->resident->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->indicator)->toBe(QualityIndicator::Sturz)
        ->and($event->severity?->value)->toBe('mittel')
        ->and($event->details['notiz'])->toBe('Sturz im Bad')
        ->and($event->reported_by)->not->toBeNull();
});

it('erlaubt ein Vorkommnis ohne Schweregrad', function () {
    Livewire::actingAs(($this->makeUser)('pflegehilfskraft'))->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'schmerz')
        ->set('ce_datum', '2026-06-02')
        ->call('recordCareEvent')
        ->assertHasNoErrors();

    expect(CareEvent::where('indicator', 'schmerz')->exists())->toBeTrue();
});

it('verwehrt Leserecht das Dokumentieren (Policy-Guard, nicht nur Nav)', function () {
    Livewire::actingAs(($this->makeUser)('leserecht'))->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'sturz')
        ->call('recordCareEvent')
        ->assertForbidden();

    expect(CareEvent::count())->toBe(0);
});

it('markiert ein Vorkommnis als behoben', function () {
    $event = CareEvent::create(['resident_id' => $this->resident->id, 'indicator' => 'sturz', 'datum' => '2026-06-01']);

    Livewire::actingAs(($this->makeUser)('admin'))->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('resolveCareEvent', $event->id);

    expect($event->fresh()->behoben_am)->not->toBeNull();
});

it('zeigt dokumentierte Vorkommnisse in der Liste', function () {
    CareEvent::create(['resident_id' => $this->resident->id, 'indicator' => 'wunde', 'datum' => '2026-06-01']);

    Livewire::actingAs(($this->makeUser)('admin'))->test(ResidentShow::class, ['resident' => $this->resident])
        ->assertSee('Chronische Wunde');
});
