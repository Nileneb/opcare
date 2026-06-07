<?php

namespace App\Domains\Facility\Services;

use App\Domains\Facility\Models\Trinkwasseranlage;

class LegionellenMonitor
{
    /** @return array<int, array{anlage: Trinkwasseranlage, faelligkeit: string, offene_ueberschreitung: bool}> */
    public function status(): array
    {
        return Trinkwasseranlage::with(['befunde'])->get()
            ->map(fn (Trinkwasseranlage $anlage) => [
                'anlage' => $anlage,
                'faelligkeit' => $anlage->faelligkeitsStatus(),
                'offene_ueberschreitung' => $anlage->offeneUeberschreitung(),
            ])
            ->all();
    }
}
