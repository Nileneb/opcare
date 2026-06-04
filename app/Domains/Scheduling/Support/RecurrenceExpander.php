<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use Illuminate\Support\Carbon;

class RecurrenceExpander
{
    // WHY: read-only Expansion einer RFC-5545-Teilmenge (FREQ/INTERVAL/BYDAY/UNTIL/COUNT).
    // Vorkommen werden NICHT persistiert — die UI/Fälligkeitslogik fragt pro Fenster ab.
    // Hard-Cap gegen Endlosschleifen bei until=null & count=null.
    private const HARD_CAP = 1000;

    /**
     * @param  Carbon  $start  Erstes Vorkommen (trägt die Uhrzeit).
     * @param  array{freq:RecurrenceFreq,intervall:int,byday:?array,until:?string,count:?int}  $rule
     * @return array<int, Carbon> Vorkommen (mit Uhrzeit) im Fenster [von, bis], aufsteigend.
     */
    public function expand(Carbon $start, array $rule, string $von, string $bis): array
    {
        $freq = $rule['freq'] instanceof RecurrenceFreq ? $rule['freq'] : RecurrenceFreq::from($rule['freq']);
        $intervall = max(1, (int) ($rule['intervall'] ?? 1));
        $byday = $rule['byday'] ?? null;
        $until = $rule['until'] ? Carbon::parse($rule['until'])->endOfDay() : null;
        $maxCount = $rule['count'] ?? null;

        $fensterVon = Carbon::parse($von)->startOfDay();
        $fensterBis = Carbon::parse($bis)->endOfDay();
        $obergrenze = $until ? $until->min($fensterBis) : $fensterBis;

        $h = (int) $start->format('H');
        $m = (int) $start->format('i');

        $out = [];
        $produziert = 0;
        $stepTag = $start->copy()->startOfDay();
        $iterationen = 0;

        while ($stepTag->lte($obergrenze) && $iterationen < self::HARD_CAP) {
            $iterationen++;
            $kandidaten = $this->kandidatenFuerSchritt($freq, $stepTag, $byday);

            foreach ($kandidaten as $tag) {
                if ($tag->lt($start->copy()->startOfDay())) {
                    continue;
                }
                $occ = $tag->copy()->setTime($h, $m, 0);
                if ($maxCount !== null && $produziert >= $maxCount) {
                    return $out;
                }
                if ($until && $occ->gt($until)) {
                    return $out;
                }
                $produziert++;
                if ($occ->betweenIncluded($fensterVon, $fensterBis)) {
                    $out[] = $occ;
                }
            }

            $stepTag = $this->naechsterSchritt($freq, $stepTag, $intervall);
        }

        return $out;
    }

    /** @return array<int, Carbon> die im aktuellen Schritt erzeugten Tage */
    private function kandidatenFuerSchritt(RecurrenceFreq $freq, Carbon $stepTag, ?array $byday): array
    {
        return match ($freq) {
            RecurrenceFreq::Daily => [$stepTag->copy()],
            RecurrenceFreq::Weekly => $this->wochentageInWoche($stepTag, $byday),
            RecurrenceFreq::Monthly => $this->monatstage($stepTag, $byday),
        };
    }

    /** @return array<int, Carbon> */
    private function wochentageInWoche(Carbon $stepTag, ?array $byday): array
    {
        $tage = $byday ?: [$stepTag->dayOfWeekIso];
        sort($tage);
        $wochenStart = $stepTag->copy()->startOfWeek(Carbon::MONDAY);

        return array_map(fn ($iso) => $wochenStart->copy()->addDays($iso - 1), $tage);
    }

    /** @return array<int, Carbon> */
    private function monatstage(Carbon $stepTag, ?array $byday): array
    {
        $tage = $byday ?: [$stepTag->day];

        return array_values(array_filter(array_map(function ($tag) use ($stepTag) {
            return $tag <= $stepTag->daysInMonth
                ? $stepTag->copy()->setDay($tag)
                : null;
        }, $tage)));
    }

    private function naechsterSchritt(RecurrenceFreq $freq, Carbon $stepTag, int $intervall): Carbon
    {
        return match ($freq) {
            RecurrenceFreq::Daily => $stepTag->copy()->addDays($intervall),
            RecurrenceFreq::Weekly => $stepTag->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($intervall),
            RecurrenceFreq::Monthly => $stepTag->copy()->startOfMonth()->addMonths($intervall),
        };
    }
}
