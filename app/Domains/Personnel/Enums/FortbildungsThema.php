<?php

namespace App\Domains\Personnel\Enums;

/**
 * Fortbildungsthemen der Pflege (QPR QB6 „Fortbildungsplanung", § 132a SGB V-Rahmenverträge, Landesheimrecht).
 * Pflichtthemen sind wiederkehrend (Intervall in Monaten); fachliche Themen sind anlassbezogen (kein Intervall).
 * Abgegrenzt von den Arbeitsschutz-Nachweisen (Unterweisung/Vorsorge/Erste Hilfe/Brandschutzhelfer/BEM),
 * die ihren eigenen Nachweis-mit-Frist-Mechanismus haben.
 */
enum FortbildungsThema: string
{
    case Hygiene = 'hygiene';
    case Datenschutz = 'datenschutz';
    case Gewaltschutz = 'gewaltschutz';
    case Reanimation = 'reanimation';
    case Brandschutzunterweisung = 'brandschutzunterweisung';
    case Sturzprophylaxe = 'sturzprophylaxe';
    case Dekubitus = 'dekubitus';
    case Schmerzmanagement = 'schmerzmanagement';
    case Demenz = 'demenz';
    case Ernaehrung = 'ernaehrung';
    case Medikation = 'medikation';
    case Palliativ = 'palliativ';
    case Kinaesthetik = 'kinaesthetik';
    case Fachlich = 'fachlich';

    public function label(): string
    {
        return match ($this) {
            self::Hygiene => 'Hygiene & Infektionsschutz',
            self::Datenschutz => 'Datenschutz & Schweigepflicht',
            self::Gewaltschutz => 'Gewaltprävention & Deeskalation',
            self::Reanimation => 'Reanimation / Notfallmanagement',
            self::Brandschutzunterweisung => 'Brandschutzunterweisung',
            self::Sturzprophylaxe => 'Sturzprophylaxe (DNQP)',
            self::Dekubitus => 'Dekubitusprophylaxe (DNQP)',
            self::Schmerzmanagement => 'Schmerzmanagement (DNQP)',
            self::Demenz => 'Umgang mit Demenz / herausforderndem Verhalten',
            self::Ernaehrung => 'Ernährung & Dysphagie (DNQP)',
            self::Medikation => 'Arzneimittel- & Medikationssicherheit',
            self::Palliativ => 'Palliativ- & Sterbebegleitung',
            self::Kinaesthetik => 'Kinästhetik / Mobilitätsförderung',
            self::Fachlich => 'sonstige fachliche Fortbildung',
        };
    }

    /** Pflicht-/Regelfortbildung mit Wiederholungspflicht. */
    public function pflicht(): bool
    {
        return $this->intervallMonate() !== null;
    }

    /** Standard-Wiederholungsintervall in Monaten; null = anlassbezogene fachliche Fortbildung. */
    public function intervallMonate(): ?int
    {
        return match ($this) {
            self::Hygiene, self::Datenschutz, self::Reanimation, self::Brandschutzunterweisung => 12,
            self::Gewaltschutz => 24,
            default => null,
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::Hygiene => '§ 23 IfSG · RKI/KRINKO',
            self::Datenschutz => 'Art. 32 DSGVO · § 53 BDSG',
            self::Gewaltschutz => '§ 113 SGB XI · QPR QB6',
            self::Reanimation => 'GRC/ERC · QPR QB5 Notfall',
            self::Brandschutzunterweisung => '§ 12 ArbSchG · ASR A2.2',
            self::Sturzprophylaxe, self::Dekubitus, self::Schmerzmanagement, self::Ernaehrung => 'DNQP-Expertenstandard',
            default => 'QPR QB6 · § 132a SGB V',
        };
    }
}
