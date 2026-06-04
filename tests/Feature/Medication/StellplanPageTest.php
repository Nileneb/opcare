<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedProduct;
use App\Livewire\Medication\Stellplan;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
    $this->nurse->assignRole('pflegefachkraft');
});

it('zeigt offene Gaben und quittiert eine über die UI', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: $this->nurse->id, med_product_id: MedProduct::factory()->create()->id, gueltig_von: today()->toDateString(),
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1]));
    app(GenerateAdministrations::class)->handle($schedule, today()->toDateString(), today()->toDateString());
    $gabe = MedicationAdministration::first();

    Livewire::actingAs($this->nurse)->test(Stellplan::class, ['resident' => $resident])
        ->call('quittieren', $gabe->id)
        ->assertHasNoErrors();

    expect($gabe->fresh()->status)->toBe(AdministrationStatus::Gegeben);
});
