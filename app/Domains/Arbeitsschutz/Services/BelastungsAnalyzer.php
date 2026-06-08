<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Data\BelastungsBefund;
use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Models\BelastungsKonfig;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Station;
use App\Domains\Scheduling\Compliance\Data\QualityFinding;
use App\Domains\Scheduling\Compliance\Data\SpitzenzeitAnalyse;
use App\Domains\Scheduling\Compliance\Data\StaffingAnalysis;
use Illuminate\Support\Collection;

/**
 * Berechnet den Belastungs-Live-Index je belegter Station eines Mandanten.
 * Stationsbezogen, KEIN Personen-Score (§ 5 Abs. 3 Nr. 6 ArbSchG Mode A).
 */
class BelastungsAnalyzer
{
    /**
     * @param  array<int, QualityFinding>  $qualityFindings
     * @return Collection<int, BelastungsBefund>
     */
    public function analysiere(
        int $tenantId,
        StaffingAnalysis $staffing,
        array $qualityFindings,
        ?SpitzenzeitAnalyse $spitzen = null,
    ): Collection {
        $konfig = BelastungsKonfig::ensureFor($tenantId);

        $deckungScore = $this->deckungScore($staffing);
        // WHY: SpitzenzeitAnalyse::unterdeckungen() zählt alle aktiven Zellen (Fenster×Tag) mit ampel !== gruen
        $spitzenScore = $spitzen === null ? 0 : min(100, $spitzen->unterdeckungen() * 20);
        $ergonomieScore = min(100, count($qualityFindings) * 15);

        $stationen = Station::where('tenant_id', $tenantId)->with(['rooms.residents'])->get();

        $befunde = collect();

        foreach ($stationen as $station) {
            $bewohner = $station->rooms->flatMap(fn ($room) => $room->residents->where('status', 'aktiv'));

            if ($bewohner->isEmpty()) {
                continue;
            }

            $pflegelastScore = $this->pflegelastScore($bewohner, $tenantId);

            $gp = $konfig->gewicht_pflegelast;
            $gd = $konfig->gewicht_deckung;
            $gs = $konfig->gewicht_spitzenzeit;
            $ge = $konfig->gewicht_ergonomie;
            $gewichtSumme = max(1, $gp + $gd + $gs + $ge);

            $gesamt = (int) round(
                ($pflegelastScore * $gp + $deckungScore * $gd + $spitzenScore * $gs + $ergonomieScore * $ge)
                / $gewichtSumme
            );

            $stufe = $this->stufe($gesamt, $konfig);

            $befunde->push(new BelastungsBefund(
                stationId: $station->id,
                wohnbereich: $station->name,
                stufe: $stufe,
                score: $gesamt,
                signale: [
                    'Pflegelast' => "Score {$pflegelastScore} ({$this->eingeschaetzteRiskItemCount($bewohner, $tenantId)} Risiken)",
                    'Personaldeckung' => "{$staffing->deckungGesamt()} %",
                    'Spitzenzeit' => $spitzen === null
                        ? 'keine Daten'
                        : "{$spitzen->unterdeckungen()} Unterdeckungs-Fenster",
                    'Ergonomie' => count($qualityFindings).' Findings',
                ],
            ));
        }

        return $befunde;
    }

    /** @param Collection<int, Resident> $bewohner */
    private function pflegelastScore(Collection $bewohner, int $tenantId): int
    {
        $eingeschaetzt = $this->eingeschaetzteRiskItemCount($bewohner, $tenantId);
        $pgHoch = $bewohner->filter(fn (Resident $r) => $r->pflegegrad >= 4)->count();

        return min(100, $eingeschaetzt * 12 + $pgHoch * 8);
    }

    /** @param Collection<int, Resident> $bewohner */
    private function eingeschaetzteRiskItemCount(Collection $bewohner, int $tenantId): int
    {
        if ($bewohner->isEmpty()) {
            return 0;
        }

        $residentIds = $bewohner->pluck('id');

        return RiskItem::where('tenant_id', $tenantId)
            ->where('eingeschaetzt', true)
            ->whereHas('sisAssessment', fn ($q) => $q->whereIn('resident_id', $residentIds))
            ->count();
    }

    private function deckungScore(StaffingAnalysis $staffing): int
    {
        $unterdeckungGesamt = max(0, 100 - $staffing->deckungGesamt());
        $unterdeckungFachkraft = max(0, 100 - $staffing->deckungFachkraft());

        return min(100, $unterdeckungGesamt + (int) ($unterdeckungFachkraft / 2));
    }

    private function stufe(int $gesamt, BelastungsKonfig $konfig): Belastungsstufe
    {
        if ($gesamt >= $konfig->schwelle_kritisch) {
            return Belastungsstufe::Kritisch;
        }

        if ($gesamt >= $konfig->schwelle_hoch) {
            return Belastungsstufe::Hoch;
        }

        if ($gesamt >= (int) round($konfig->schwelle_hoch / 2)) {
            return Belastungsstufe::Erhoeht;
        }

        return Belastungsstufe::Gering;
    }
}
