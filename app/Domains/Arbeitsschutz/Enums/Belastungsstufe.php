<?php

namespace App\Domains\Arbeitsschutz\Enums;

enum Belastungsstufe: string
{
    case Gering = 'gering';
    case Erhoeht = 'erhoeht';
    case Hoch = 'hoch';
    case Kritisch = 'kritisch';

    public function label(): string
    {
        return match ($this) {
            self::Gering => 'Gering',
            self::Erhoeht => 'Erhöht',
            self::Hoch => 'Hoch',
            self::Kritisch => 'Kritisch',
        };
    }

    public function ampel(): string
    {
        return match ($this) {
            self::Gering, self::Erhoeht => 'green',
            self::Hoch => 'amber',
            self::Kritisch => 'red',
        };
    }

    public function rang(): int
    {
        return match ($this) {
            self::Gering => 1,
            self::Erhoeht => 2,
            self::Hoch => 3,
            self::Kritisch => 4,
        };
    }

    public function istMeldepflichtig(): bool
    {
        return match ($this) {
            self::Hoch, self::Kritisch => true,
            default => false,
        };
    }
}
