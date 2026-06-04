<?php

namespace App\Domains\Speech\Testing;

use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Data\SisVorschlagData;

class FakeSisStructurer implements SisStructurer
{
    public function structure(string $transcript, string $kontext): SisVorschlagData
    {
        return SisVorschlagData::from([
            'felder' => [
                ['themenfeld' => $kontext === 'bericht' ? 'mobilitaet' : $kontext, 'freitext' => $transcript],
            ],
        ]);
    }
}
