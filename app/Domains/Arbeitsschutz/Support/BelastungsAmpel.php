<?php

namespace App\Domains\Arbeitsschutz\Support;

/**
 * Farbverlauf-Helper für die Lage-Ampel (Wohlbefinden-Skala 0-10).
 *
 * Skala: 10 = grün = gut, 0 = rot = kritisch (invertiert zum Roh-Score).
 * Norm-Anker: § 5 Abs. 3 Nr. 6 ArbSchG (psychische Belastung); rein visuelle Darstellung, kein Personen-Ranking.
 */
class BelastungsAmpel
{
    /**
     * Konvertiert einen Roh-Belastungs-Score (0-100) in die Lage-Skala (0-10).
     *
     * score 0   → lage 10 (beste Lage)
     * score 100 → lage 0  (schlechteste Lage)
     *
     * @param  int  $score  Roh-Belastungswert 0-100
     * @return int Lage-Wert 0-10
     */
    public static function lageAusScore(int $score): int
    {
        return max(0, min(10, (int) round((100 - $score) / 10)));
    }

    /**
     * Piecewise-lineare HSL-Hue-Interpolation für den Farbverlauf.
     *
     * Anker (lage → Hue°):
     *   0  → 0°   (rot)
     *   2  → 0°   (rot, Plateau)
     *   4.5 → 50° (gelb-orange)
     *   6  → 75°  (gelb-grün)
     *   8  → 110° (grün)
     *   10 → 120° (sattes Grün)
     *
     * @param  int  $lage  Lage-Wert 0-10
     * @return string CSS-Farbwert z. B. "hsl(110, 75%, 45%)"
     */
    public static function farbe(int $lage): string
    {
        $lage = max(0, min(10, $lage));

        $hue = self::interpoliereHue((float) $lage);

        return "hsl({$hue}, 75%, 45%)";
    }

    private static function interpoliereHue(float $lage): int
    {
        $anker = [
            [0.0, 0.0],
            [2.0, 0.0],
            [4.5, 50.0],
            [6.0, 75.0],
            [8.0, 110.0],
            [10.0, 120.0],
        ];

        // Unterhalb des ersten Ankers
        if ($lage <= $anker[0][0]) {
            return (int) round($anker[0][1]);
        }

        // Oberhalb des letzten Ankers
        $last = count($anker) - 1;
        if ($lage >= $anker[$last][0]) {
            return (int) round($anker[$last][1]);
        }

        // Piecewise linear zwischen zwei Ankern
        for ($i = 0; $i < $last; $i++) {
            [$x0, $h0] = $anker[$i];
            [$x1, $h1] = $anker[$i + 1];

            if ($lage >= $x0 && $lage <= $x1) {
                $t = ($x1 - $x0) > 0 ? ($lage - $x0) / ($x1 - $x0) : 0.0;

                return (int) round($h0 + $t * ($h1 - $h0));
            }
        }

        return 0;
    }
}
