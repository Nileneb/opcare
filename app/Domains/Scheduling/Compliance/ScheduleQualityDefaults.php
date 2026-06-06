<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Models\ScheduleQualityRule;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saat für die editierbare `schedule_quality_rules`-Tabelle: ergonomische Schichtplan-Empfehlungen aus den
 * gesicherten arbeitswissenschaftlichen Erkenntnissen (§ 6 ArbZG, BAuA/BGHM/DGAUM-S2k-Leitlinie). Im Gegensatz
 * zur ArbZG-Engine sind dies bewusst Empfehlungen (Warnung/Hinweis) — anpassbar, weil Tarif/Konzept variieren.
 */
class ScheduleQualityDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array<string, mixed>> */
    public static function rules(): array
    {
        return [
            [
                'key' => 'max-folge-arbeitstage',
                'label' => 'Max. aufeinanderfolgende Arbeitstage',
                'kategorie' => 'erholung',
                'severity' => ViolationSeverity::Warnung->value,
                'params' => ['max_tage' => 7],
                'quelle' => 'BAuA/BGHM: max. 7 Arbeitstage am Stück (Limit 12)',
            ],
            [
                'key' => 'max-folge-nachtdienste',
                'label' => 'Max. aufeinanderfolgende Nachtdienste',
                'kategorie' => 'nacht',
                'severity' => ViolationSeverity::Warnung->value,
                'params' => ['max_naechte' => 3],
                'quelle' => 'BAuA/BGHM/DGAUM: max. 3 Nachtschichten in Folge',
            ],
            [
                'key' => 'quick-return',
                'label' => 'Quick Return (Spät → Früh)',
                'kategorie' => 'rotation',
                'severity' => ViolationSeverity::Warnung->value,
                'params' => ['min_ruhe_stunden' => 16],
                'quelle' => 'BGHM: kurze Spät→Früh-Wechsel vermeiden (ergonomisch ≥ 16 h)',
            ],
            [
                'key' => 'vorwaerts-rotation',
                'label' => 'Vorwärtsrotation (Früh → Spät → Nacht)',
                'kategorie' => 'rotation',
                'severity' => ViolationSeverity::Hinweis->value,
                'params' => [],
                'quelle' => 'BAuA/BGHM/DGAUM: keine Rückwärtsrotation',
            ],
            [
                'key' => 'min-freiblock',
                'label' => 'Zusammenhängende freie Tage',
                'kategorie' => 'erholung',
                'severity' => ViolationSeverity::Warnung->value,
                'params' => ['min_tage' => 2],
                'quelle' => 'BGHM/BAuA: Freizeit blocken statt stückeln',
            ],
        ];
    }

    /** @return Collection<int, ScheduleQualityRule> */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::rules() as $rule) {
            ScheduleQualityRule::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $rule['key']],
                [...$rule, 'tenant_id' => $tenantId],
            );
        }

        return ScheduleQualityRule::where('tenant_id', $tenantId)->orderBy('id')->get();
    }
}
