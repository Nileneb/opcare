<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\CareMeasure;
use Illuminate\Support\Facades\DB;

class ReviseCareMeasure
{
    public function handle(CareMeasure $current, array $changes): CareMeasure
    {
        return DB::transaction(fn () => $current->reviseWith($changes));
    }
}
