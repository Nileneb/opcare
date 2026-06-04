<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Models\PrescriptionSchedule;
use App\Livewire\Medication\Verordnungen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);

    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->rx = (new CreatePrescription)->handle(new PrescriptionData(
        resident_id: $this->resident->id, created_by: $this->user->id, bhp_text: 'Kompression Bein li.',
    ));
    (new AddSchedule)->handle($this->rx, new ScheduleData(frequenz: 'taeglich', dosis: ['morgens' => 1]));
});

afterEach(fn () => Carbon::setTestNow());

it('listet die aktiven Verordnungen des Bewohners', function () {
    Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->assertSee('Kompression Bein li.');
});

it('setzt eine Verordnung ab', function () {
    Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->call('absetzen', $this->rx->id)
        ->assertHasNoErrors();

    expect($this->rx->fresh()->abgesetzt_am)->not->toBeNull();
});

it('kann keine Verordnung eines FREMDEN Bewohners absetzen (IDOR)', function () {
    $fremd = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $fremdRx = (new CreatePrescription)->handle(new PrescriptionData(
        resident_id: $fremd->id, created_by: $this->user->id, bhp_text: 'Fremd',
    ));

    expect(fn () => Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->call('absetzen', $fremdRx->id))
        ->toThrow(ModelNotFoundException::class);

    expect($fremdRx->fresh()->abgesetzt_am)->toBeNull();
});

it('kann keinen Bedarf-Schedule eines FREMDEN Bewohners dosieren (IDOR)', function () {
    $fremd = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $fremdRx = (new CreatePrescription)->handle(new PrescriptionData(
        resident_id: $fremd->id, created_by: $this->user->id, bhp_text: 'Fremd', bei_bedarf: true,
    ));
    $fremdSchedule = (new AddSchedule)->handle($fremdRx, new ScheduleData(frequenz: 'bei_bedarf', dosis: []));

    expect(fn () => Livewire::test(Verordnungen::class, ['resident' => $this->resident])
        ->call('bedarfGeben', $fremdSchedule->id, 1.0))
        ->toThrow(ModelNotFoundException::class);

    expect(PrescriptionSchedule::find($fremdSchedule->id)->administrations()->count())->toBe(0);
});
