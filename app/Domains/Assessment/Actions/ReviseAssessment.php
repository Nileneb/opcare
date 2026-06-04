<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use Illuminate\Support\Facades\DB;

class ReviseAssessment
{
    public function __construct(private ConductAssessment $conduct = new ConductAssessment) {}

    // WHY: Wiederholungsmessung ist append-only — neue Durchführung als Folgeversion, alte wird abgelöst.
    public function handle(Assessment $previous, AssessmentInputData $data): Assessment
    {
        return DB::transaction(function () use ($previous, $data) {
            $neu = $this->conduct->handle($data);
            $neu->forceFill(['version' => $previous->version + 1])->save();
            $previous->forceFill(['superseded_by' => $neu->id, 'status' => 'abgelöst'])->save();

            return $neu;
        });
    }
}
