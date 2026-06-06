<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Models\ComplianceRule;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ableitbare Standard-Regeln des Arbeitszeitgesetzes (ArbZG) für die stationäre Pflege — Saat für die
 * editierbare `compliance_rules`-Tabelle. Jede Regel verweist auf den amtlichen Gesetzestext
 * (gesetze-im-internet.de) + ein Wortlaut-Zitat, damit die Herleitung jederzeit nachvollziehbar bleibt.
 *
 * Bewusst NICHT als gesetzliche Beratung gedacht: Schwellwerte sind editierbar, weil Tarifverträge/
 * Betriebsvereinbarungen abweichen können. § 4 (Pausen) ist mangels Pausen-Erfassung „nicht prüfbar".
 */
class ArbeitszeitgesetzDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array<string, mixed>> */
    public static function rules(): array
    {
        return [
            [
                'key' => 'tageshoechstarbeitszeit',
                'paragraph' => '§ 3 ArbZG',
                'label' => 'Tägliche Höchstarbeitszeit',
                'severity' => ViolationSeverity::Verstoss->value,
                'params' => ['max_stunden' => 10, 'hinweis_ab_stunden' => 8],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__3.html',
                'gesetz_zitat' => 'Die werktägliche Arbeitszeit der Arbeitnehmer darf acht Stunden nicht überschreiten. '
                    .'Sie kann auf bis zu zehn Stunden nur verlängert werden, wenn innerhalb von sechs Kalendermonaten '
                    .'oder innerhalb von 24 Wochen im Durchschnitt acht Stunden werktäglich nicht überschritten werden.',
                'note' => null,
            ],
            [
                'key' => 'ruhezeit',
                'paragraph' => '§ 5 ArbZG',
                'label' => 'Ununterbrochene Ruhezeit',
                'severity' => ViolationSeverity::Verstoss->value,
                // Pflege/Behandlung: § 5 Abs. 2 erlaubt Kürzung auf 10 h mit Ausgleich.
                'params' => ['min_stunden' => 11, 'ausnahme_pflege_stunden' => 10],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__5.html',
                'gesetz_zitat' => 'Die Arbeitnehmer müssen nach Beendigung der täglichen Arbeitszeit eine ununterbrochene '
                    .'Ruhezeit von mindestens elf Stunden haben. Die Dauer der Ruhezeit kann in Krankenhäusern und anderen '
                    .'Einrichtungen zur Behandlung, Pflege und Betreuung von Personen um bis zu eine Stunde verkürzt werden, '
                    .'wenn jede Verkürzung innerhalb eines Kalendermonats ausgeglichen wird.',
                'note' => null,
            ],
            [
                'key' => 'wochenarbeitszeit',
                'paragraph' => '§ 3 ArbZG',
                'label' => 'Wöchentliche Höchstarbeitszeit',
                'severity' => ViolationSeverity::Warnung->value,
                // 48 h ergeben sich aus 6 Werktagen × 8 h (Durchschnittsgrenze über 24 Wochen).
                'params' => ['max_stunden_woche' => 48],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__3.html',
                'gesetz_zitat' => 'Maßgeblich ist der Durchschnitt von acht Stunden werktäglich über sechs Kalendermonate '
                    .'bzw. 24 Wochen — bei sechs Werktagen entspricht das 48 Wochenstunden im Schnitt.',
                'note' => null,
            ],
            [
                'key' => 'sonntagsruhe',
                'paragraph' => '§§ 9–11 ArbZG',
                'label' => 'Sonntagsbeschäftigung',
                'severity' => ViolationSeverity::Hinweis->value,
                'params' => ['min_freie_sonntage_jahr' => 15],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__10.html',
                'gesetz_zitat' => '§ 9: An Sonn- und Feiertagen dürfen Arbeitnehmer von 0 bis 24 Uhr nicht beschäftigt werden. '
                    .'§ 10 Abs. 1 Nr. 3 lässt Ausnahmen u. a. in Einrichtungen zur Behandlung, Pflege und Betreuung von Personen zu. '
                    .'§ 11: Mindestens 15 Sonntage im Jahr müssen beschäftigungsfrei bleiben; für Sonntagsarbeit ist ein '
                    .'Ersatzruhetag innerhalb von zwei Wochen zu gewähren.',
                'note' => 'In der Pflege grundsätzlich zulässig (§ 10-Ausnahme) — Ersatzruhetag + freie Sonntage nach § 11 beachten.',
            ],
            [
                'key' => 'ruhepausen',
                'paragraph' => '§ 4 ArbZG',
                'label' => 'Ruhepausen',
                'severity' => ViolationSeverity::Hinweis->value,
                'params' => ['pause_30_ab_stunden' => 6, 'pause_45_ab_stunden' => 9],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__4.html',
                'gesetz_zitat' => 'Die Arbeit ist durch im Voraus feststehende Ruhepausen von mindestens 30 Minuten bei einer '
                    .'Arbeitszeit von mehr als sechs bis zu neun Stunden und 45 Minuten bei einer Arbeitszeit von mehr als neun '
                    .'Stunden insgesamt zu unterbrechen. Länger als sechs Stunden hintereinander dürfen Arbeitnehmer nicht ohne '
                    .'Ruhepause beschäftigt werden.',
                // WHY: opcare erfasst keine Pausenzeiten → der Analyzer kann § 4 nicht verifizieren und meldet
                // ehrlich „nicht prüfbar" statt Konformität vorzutäuschen.
                'note' => 'Pausen werden in opcare nicht erfasst — diese Regel wird als „nicht prüfbar" ausgewiesen.',
            ],
            [
                'key' => 'notfall_ausnahme',
                'paragraph' => '§ 14 ArbZG',
                'label' => 'Außergewöhnliche Fälle (Ausnahmegrundlage)',
                'severity' => ViolationSeverity::Hinweis->value,
                'params' => [],
                'gesetz_url' => 'https://www.gesetze-im-internet.de/arbzg/__14.html',
                'gesetz_zitat' => 'Von den §§ 3 bis 5, 6 Abs. 2, §§ 7, 11 Abs. 1 bis 3 darf abgewichen werden bei '
                    .'vorübergehenden Arbeiten in Notfällen und in außergewöhnlichen Fällen, die unabhängig vom Willen der '
                    .'Betroffenen eintreten und deren Folgen nicht auf andere Weise zu beseitigen sind.',
                // WHY: keine auto-Prüfung — § 14 ist die Rechtsgrundlage, auf die sich eine dokumentierte
                // Begründung beruft (z. B. ausbleibende Nachfolgekraft). Der Analyzer erzeugt hierfür keine Befunde.
                'note' => 'Rechtsgrundlage für dokumentierte, begründete Abweichungen (z. B. ausbleibende Nachfolgekraft). '
                    .'Eine begründete Überschreitung bleibt ein Verstoß, ist aber nachvollziehbar dokumentiert.',
            ],
        ];
    }

    /**
     * Stellt sicher, dass der Mandant die Default-Regeln besitzt (idempotent) — vorhandene (editierte)
     * Regeln bleiben unangetastet. Liefert die aktuellen Regeln des Mandanten zurück.
     *
     * @return Collection<int, ComplianceRule>
     */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::rules() as $rule) {
            ComplianceRule::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $rule['key']],
                [...$rule, 'tenant_id' => $tenantId],
            );
        }

        return ComplianceRule::where('tenant_id', $tenantId)->orderBy('id')->get();
    }
}
