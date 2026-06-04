<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\CarePlanning\Models\SisAssessment;
use Illuminate\Support\Facades\DB;

class CreateSisAssessment
{
    public function handle(SisAssessmentData $data): SisAssessment
    {
        return DB::transaction(function () use ($data) {
            $sis = SisAssessment::create([
                'resident_id' => $data->resident_id,
                'created_by' => $data->created_by,
                'erstellt_am' => $data->erstellt_am,
                'status' => 'aktiv',
                'eingangsfrage' => $data->eingangsfrage,
            ]);

            foreach ($data->themenfelder as $feld) {
                $sis->topicFields()->create([
                    'themenfeld' => $feld['themenfeld'],
                    'freitext' => $feld['freitext'] ?? null,
                    'strukturdaten' => $feld['strukturdaten'] ?? null,
                ]);
            }

            return $sis->load('topicFields');
        });
    }
}
