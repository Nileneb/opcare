<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\CareReportData;
use App\Domains\CarePlanning\Models\CareReport;

class CreateCareReport
{
    public function handle(CareReportData $data): CareReport
    {
        return CareReport::create($data->toArray());
    }
}
