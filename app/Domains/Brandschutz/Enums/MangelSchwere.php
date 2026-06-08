<?php

namespace App\Domains\Brandschutz\Enums;

enum MangelSchwere: string
{
    case Gering = 'Gering';
    case Wesentlich = 'Wesentlich';
    case Kritisch = 'Kritisch';

    public function label(): string
    {
        return $this->value;
    }

    public function ampel(): string
    {
        return match ($this) {
            self::Gering => 'green',
            self::Wesentlich => 'amber',
            self::Kritisch => 'red',
        };
    }

    public function rang(): int
    {
        return match ($this) {
            self::Gering => 1,
            self::Wesentlich => 2,
            self::Kritisch => 3,
        };
    }
}
