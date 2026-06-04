<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\CareReport;
use Illuminate\Support\Facades\DB;

class ReviseCareReport
{
    public function handle(CareReport $current, array $changes): CareReport
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
