<?php

namespace App\Domains\Speech\Actions;

use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Support\Facades\DB;

class ApproveTranscription
{
    public function __construct(private CreateSisAssessment $createSis) {}

    /**
     * @param  array{felder: array<int, array{themenfeld:string, freitext:string}>}  $korrigiert
     *                                                                                            Die vom Menschen geprüften/korrigierten Felder.
     */
    public function handle(TranscriptionJob $job, int $reviewerId, array $korrigiert): SisAssessment
    {
        return DB::transaction(function () use ($job, $reviewerId, $korrigiert) {
            $sis = $this->createSis->handle(new SisAssessmentData(
                resident_id: $job->resident_id,
                created_by: $reviewerId,
                erstellt_am: now()->format('Y-m-d'),
                eingangsfrage: null,
                themenfelder: array_map(fn ($f) => [
                    'themenfeld' => $f['themenfeld'],
                    'freitext' => $f['freitext'],
                    'strukturdaten' => null,
                ], $korrigiert['felder']),
            ));

            $job->update([
                'reviewer_id' => $reviewerId,
                'status' => TranscriptionStatus::Done,
                'freigegeben_at' => now(),
            ]);

            return $sis;
        });
    }
}
