<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\AdministerOnDemand;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\DiscontinuePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedProduct;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('dokumentiert eine Bedarfsgabe innerhalb des Tageslimits', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id, bei_bedarf: true,
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::BeiBedarf->value, dosis: ['bei_bedarf' => 1], max_anzahl_taeglich: 3,
    ));

    $gabe = app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, dosis: 1, notiz: 'Schmerzen');
    expect($gabe->status)->toBe(AdministrationStatus::Gegeben);
});

it('lehnt eine Bedarfsgabe über dem Tageslimit ab', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, bei_bedarf: true,
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::BeiBedarf->value, dosis: ['bei_bedarf' => 1], max_anzahl_taeglich: 1,
    ));
    app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, 1, 'erste');

    expect(fn () => app(AdministerOnDemand::class)->handle($schedule, $this->nurse->id, 1, 'zweite'))
        ->toThrow(DomainException::class);
});

it('setzt eine Verordnung ab und storniert künftige geplante Gaben', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id, gueltig_von: today()->toDateString(),
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1]));
    app(GenerateAdministrations::class)->handle($schedule, today()->toDateString(), today()->addDays(5)->toDateString());

    app(DiscontinuePrescription::class)->handle($rx, $this->nurse->id, ab: today()->addDay()->toDateString());

    expect(MedicationAdministration::where('status', AdministrationStatus::Ausgelassen->value)->count())->toBeGreaterThan(0)
        ->and($rx->fresh()->abgesetzt_am)->not->toBeNull();
});
