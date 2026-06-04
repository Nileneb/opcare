<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\SisAssessment;
use Illuminate\Support\Facades\DB;

class ReviseSisAssessment
{
    public function handle(SisAssessment $current, array $changes): SisAssessment
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
