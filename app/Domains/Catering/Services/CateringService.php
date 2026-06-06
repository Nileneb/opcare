<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\Gericht;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentStatusObservation;
use App\Domains\Masterdata\Support\StatusObservationCatalog;
use Illuminate\Support\Collection;

/**
 * Versorgt die Küche aus den vorhandenen Pflegedaten: Lebensmittelallergien (`ResidentAllergy` mit
 * kategorie=nahrung) + Kostform/Ernährungsform (`ResidentStatusObservation`). Gleicht außerdem die
 * LMIV-Allergene eines Gerichts unscharf gegen die Allergie-Freitexte ab (Hinweis, keine Garantie).
 */
class CateringService
{
    /**
     * Aktive Bewohner mit küchenrelevanter Diät-Info (Lebensmittelallergie oder Kostform/Ernährungsform).
     *
     * @return Collection<int, Resident>
     */
    public function diaetBewohner(): Collection
    {
        return Resident::query()->where('status', 'aktiv')
            ->with([
                'allergies' => fn ($q) => $q->where('kategorie', 'nahrung'),
                'statusObservations' => fn ($q) => $q->whereIn('typ', ['kostform', 'ernaehrungsform']),
            ])
            ->orderBy('name')->get()
            ->filter(fn (Resident $r) => $r->allergies->isNotEmpty() || $r->statusObservations->isNotEmpty())
            ->values();
    }

    /** Menschlich lesbares Kostform-/Ernährungsform-Label aus dem Katalog. */
    public function kostformLabel(ResidentStatusObservation $o): string
    {
        $def = StatusObservationCatalog::get($o->typ);

        return $o->wert_code ? ($def['options'][$o->wert_code] ?? (string) $o->wert_code) : (string) $o->wert_text;
    }

    /**
     * Bewohner, deren Lebensmittelallergie zu einem Allergen des Gerichts passt.
     *
     * @param  Collection<int, Resident>  $bewohner  bereits mit Nahrungsmittel-Allergien geladen
     * @return array<int, array{resident: Resident, allergen: string, substanz: string}>
     */
    public function betroffene(Gericht $gericht, Collection $bewohner): array
    {
        $allergene = $gericht->allergeneEnum();
        if ($allergene === []) {
            return [];
        }

        $treffer = [];
        foreach ($bewohner as $r) {
            foreach ($r->allergies as $allergie) {
                $sub = mb_strtolower($allergie->substanz);
                foreach ($allergene as $al) {
                    foreach ($al->keywords() as $kw) {
                        if (str_contains($sub, $kw)) {
                            $treffer[$r->id] = ['resident' => $r, 'allergen' => $al->label(), 'substanz' => $allergie->substanz];
                            break 3;
                        }
                    }
                }
            }
        }

        return array_values($treffer);
    }
}
