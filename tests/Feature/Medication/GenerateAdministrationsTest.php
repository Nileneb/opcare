<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erzeugt geplante Gaben je Tageszeit über einen Zeitraum — idempotent', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id,
        gueltig_von: '2026-06-01',
    ));
    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::Taeglich->value, dosis: ['morgens' => 1, 'abends' => 1],
    ));

    $created = app(GenerateAdministrations::class)->handle($schedule, '2026-06-01', '2026-06-03');
    // 3 Tage × 2 Tageszeiten = 6 geplante Gaben
    expect($created)->toBe(6)
        ->and(MedicationAdministration::count())->toBe(6);

    // Erneuter Lauf erzeugt KEINE Duplikate.
    $again = app(GenerateAdministrations::class)->handle($schedule, '2026-06-01', '2026-06-03');
    expect($again)->toBe(0)->and(MedicationAdministration::count())->toBe(6);
});
