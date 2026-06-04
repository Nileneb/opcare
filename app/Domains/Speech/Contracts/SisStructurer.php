<?php

namespace App\Domains\Speech\Contracts;

use App\Domains\Speech\Data\SisVorschlagData;

interface SisStructurer
{
    /** Strukturiert Rohtext in einen validierten SIS-Vorschlag. */
    public function structure(string $transcript, string $kontext): SisVorschlagData;
}
