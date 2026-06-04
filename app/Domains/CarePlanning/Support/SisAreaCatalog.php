<?php

namespace App\Domains\CarePlanning\Support;

use App\Domains\CarePlanning\Enums\SisTopicField;

/**
 * Anzeige-Metadaten der 6 SIS®-Lebensbereiche für die Pflegeplanungs-UI
 * (Bergische-Diakonie-Design): Kurztitel, Leitfrage, Tint-Farbe, Icon-Key.
 * Reihenfolge ist FIX (SIS-Design-Regel #2: Bereiche immer gleich positioniert).
 */
final class SisAreaCatalog
{
    /** @return array<int, array{key:string, name:string, kurz:string, frage:string, tint:string, icon:string}> */
    public static function all(): array
    {
        return [
            self::meta(SisTopicField::Kognition, 'Denken & Sprechen', 'Wie erinnert, versteht und verständigt sich die Person?', '#6E72B0', 'kognition'),
            self::meta(SisTopicField::Mobilitaet, 'Bewegen & Gehen', 'Wie bewegt sich die Person — und wo droht ein Sturz?', '#3E8FA6', 'mobilitaet'),
            self::meta(SisTopicField::Krankheitsbezogen, 'Gesundheit & Medikamente', 'Welche Erkrankungen, Medikamente und Wunden sind zu beachten?', '#B5654A', 'krankheit'),
            self::meta(SisTopicField::Selbstversorgung, 'Essen, Trinken, Pflege', 'Was kann die Person im Alltag selbst — was braucht Unterstützung?', '#A98534', 'selbstversorgung'),
            self::meta(SisTopicField::SozialeBeziehungen, 'Familie & Kontakte', 'Wer und was gibt der Person Halt — wo droht Einsamkeit?', '#B05C7A', 'soziales'),
            self::meta(SisTopicField::Wohnen, 'Zuhause & Sicherheit', 'Wie sicher und selbstbestimmt lebt die Person im Wohnumfeld?', '#5E8A6B', 'haushalt'),
        ];
    }

    /** @return array{key:string, name:string, kurz:string, frage:string, tint:string, icon:string} */
    private static function meta(SisTopicField $field, string $kurz, string $frage, string $tint, string $icon): array
    {
        return [
            'key' => $field->value,
            'name' => $field->label(),
            'kurz' => $kurz,
            'frage' => $frage,
            'tint' => $tint,
            'icon' => $icon,
        ];
    }
}
