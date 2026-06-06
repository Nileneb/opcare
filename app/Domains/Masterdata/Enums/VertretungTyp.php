<?php

namespace App\Domains\Masterdata\Enums;

/**
 * Art der Vertretung eines Bewohners. Rechtliche Vertretungen (Betreuer/Bevollmächtigte) handeln innerhalb
 * ihrer Aufgabenkreise; Angehörige ohne Vollmacht sind reine Kontakt-/Informationsempfänger.
 */
enum VertretungTyp: string
{
    case GesetzlicherBetreuer = 'gesetzlicher_betreuer';
    case Vorsorgebevollmaechtigter = 'vorsorgebevollmaechtigter';
    case Bevollmaechtigter = 'bevollmaechtigter';
    case Angehoeriger = 'angehoeriger';

    public function label(): string
    {
        return match ($this) {
            self::GesetzlicherBetreuer => 'Gesetzliche:r Betreuer:in',
            self::Vorsorgebevollmaechtigter => 'Vorsorgebevollmächtigte:r',
            self::Bevollmaechtigter => 'Bevollmächtigte:r',
            self::Angehoeriger => 'Angehörige:r (ohne Vollmacht)',
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::GesetzlicherBetreuer => '§ 1814 BGB, BtOG',
            self::Vorsorgebevollmaechtigter, self::Bevollmaechtigter => '§ 1820 BGB (Vollmacht)',
            self::Angehoeriger => '—',
        };
    }

    /**
     * Rechtliche Vertretung = darf in den Aufgabenkreisen handeln/Einwilligungen abgeben.
     * Angehörige ohne Vollmacht werden nur informiert.
     */
    public function istRechtlicheVertretung(): bool
    {
        return $this !== self::Angehoeriger;
    }
}
