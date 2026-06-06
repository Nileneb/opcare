<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;

class SyncRiskItem
{
    // WHY: ein Assessment-Ergebnis soll im SIS-Risikoteil sichtbar werden. Es schreibt in die
    // aktuelle (nicht abgelöste) SisAssessment des Bewohners; existiert keine, passiert nichts.
    public function handle(Assessment $assessment): ?RiskItem
    {
        $assessment->loadMissing('instrument');
        $riskType = $assessment->instrument->risk_type;

        $sis = SisAssessment::current()
            ->where('resident_id', $assessment->resident_id)
            ->latest('erstellt_am')
            ->first();

        if (! $sis) {
            return null;
        }

        $band = $assessment->risk_band;
        $eingeschaetzt = $band !== null && $band->istKritisch();

        $risk = RiskItem::firstOrNew([
            'sis_assessment_id' => $sis->id,
            'risiko' => $riskType,
        ]);
        $risk->eingeschaetzt = $eingeschaetzt;
        $risk->begruendung = sprintf(
            '%s: Score %d (%s) am %s',
            $assessment->instrument->name,
            $assessment->score,
            $band?->label() ?? '—',
            $assessment->durchgefuehrt_am->format('d.m.Y'),
        );
        $risk->save();

        return $risk;
    }
}
