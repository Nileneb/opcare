<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
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
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
    $this->user->assignRole('pflegefachkraft');
});

it('erfasst einen Dekubitus mit strukturiertem Stadium und Beginn', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'dekubitus')
        ->set('ce_datum', '2026-06-01')
        ->set('ce_dek_stadium', 3)
        ->set('ce_dek_beginn', '2026-05-20')
        ->set('ce_dek_stelle', 'Steißbein')
        ->call('recordCareEvent')
        ->assertHasNoErrors();

    $event = CareEvent::where('indicator', 'dekubitus')->first();
    expect($event->details['stadium'])->toBe(3)
        ->and($event->details['beginn'])->toBe('2026-05-20')
        ->and($event->details['stelle'])->toBe('Steißbein');
});

it('verlangt Stadium und Beginn bei einem Dekubitus (DAS-Datenlücke vermeiden)', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'dekubitus')
        ->set('ce_datum', '2026-06-01')
        ->call('recordCareEvent')
        ->assertHasErrors(['ce_dek_stadium', 'ce_dek_beginn']);

    expect(CareEvent::count())->toBe(0);
});

it('verlangt keine Dekubitus-Felder bei anderen Indikatoren', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('ce_indicator', 'sturz')
        ->set('ce_datum', '2026-06-01')
        ->call('recordCareEvent')
        ->assertHasNoErrors();
});
