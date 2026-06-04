<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('hängt einen täglichen Stellplan mit Dosis je Tageszeit an', function () {
    $resident = Resident::factory()->create();
    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id, created_by: 1, med_product_id: MedProduct::factory()->create()->id,
    ));

    $schedule = app(AddSchedule::class)->handle($rx, new ScheduleData(
        frequenz: ScheduleFrequency::Taeglich->value,
        dosis: ['morgens' => 1, 'abends' => 0.5],
    ));

    expect($schedule->frequenz)->toBe(ScheduleFrequency::Taeglich)
        ->and($schedule->dosis['morgens'])->toBe(1)
        ->and($rx->schedules)->toHaveCount(1);
});
