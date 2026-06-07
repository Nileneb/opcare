<?php

namespace App\Domains\Capture\Services;

use App\Domains\Accounting\Actions\BestellungWareneingang;
use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Bestellposition;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Contracts\LieferscheinVlmAnalyzer;
use App\Domains\Capture\Enums\PositionStatus;
use App\Domains\Capture\Models\LieferscheinAnalyse;
use App\Domains\Capture\Models\LieferscheinPositionVorschlag;
use App\Domains\Capture\Support\LieferantMatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CaptureWareneingang
{
    public function __construct(
        private readonly LieferscheinVlmAnalyzer $analyzer,
        private readonly ArtikelMatcher $matcher,
    ) {}

    public function erfasse(string $imageBase64, string $mimeType, int $tenantId, ?int $userId = null): LieferscheinAnalyse
    {
        return DB::transaction(function () use ($imageBase64, $mimeType, $tenantId, $userId) {
            $ext = $this->analyzer->analysiere($imageBase64, $mimeType);

            $lieferant = LieferantMatch::finde($ext->lieferant ?? '', $tenantId);
            $lieferantId = $lieferant?->id;

            $analyse = LieferscheinAnalyse::create([
                'tenant_id' => $tenantId,
                'lieferant_text' => $ext->lieferant,
                'lieferant_id' => $lieferantId,
                'datum' => $ext->datum,
                'lieferschein_nr' => $ext->lieferschein_nr,
                'roh_json' => $ext->toArray(),
                'modell' => config('speech.capture.model'),
                'konfidenz' => $ext->konfidenz,
                'erstellt_von' => $userId,
            ]);

            $analyse->addMediaFromString(base64_decode($imageBase64))
                ->usingFileName('lieferschein_'.$analyse->id.'.jpg')
                ->toMediaCollection('lieferschein');

            foreach ($ext->positionen as $pos) {
                $kandidaten = $this->matcher->match($pos->text, $lieferantId, $tenantId);
                $besterKandidat = $kandidaten[0] ?? null;
                $matchedArtikelId = $besterKandidat?->artikel_id;

                $bestellpositionId = $this->findeOffeneBestellposition($lieferantId, $matchedArtikelId, $tenantId);

                $analyse->positionen()->create([
                    'tenant_id' => $tenantId,
                    'text' => $pos->text,
                    'menge' => $pos->menge,
                    'einheit' => $pos->einheit,
                    'einzelpreis' => $pos->einzelpreis,
                    'charge_nr' => $pos->charge_nr,
                    'mhd' => $pos->mhd,
                    'matched_artikel_id' => $matchedArtikelId,
                    'matched_bestellposition_id' => $bestellpositionId,
                    'kandidaten' => array_map(fn ($k) => $k->toArray(), $kandidaten),
                    'konfidenz' => $besterKandidat?->score,
                    'status' => PositionStatus::Vorgeschlagen,
                ]);
            }

            return $analyse;
        });
    }

    public function bestaetige(
        LieferscheinPositionVorschlag $p,
        int $artikelId,
        float $menge,
        ?float $preis,
        ?string $chargeNr,
        ?string $mhd,
        ?int $bestellpositionId,
        int $tenantId,
        ?int $userId = null,
    ): LieferscheinPositionVorschlag {
        if ($artikelId <= 0) {
            throw new InvalidArgumentException('Artikel erforderlich');
        }

        if (! $p->offen()) {
            throw new InvalidArgumentException('Position ist nicht mehr offen.');
        }

        return DB::transaction(function () use ($p, $artikelId, $menge, $preis, $chargeNr, $mhd, $bestellpositionId, $tenantId, $userId) {
            if ($bestellpositionId !== null) {
                $pos = Bestellposition::findOrFail($bestellpositionId);
                $bewegung = app(BestellungWareneingang::class)->handle($pos, $menge, $preis, today()->toDateString(), $chargeNr, $mhd);
            } else {
                $artikel = Artikel::findOrFail($artikelId);
                $bewegung = app(Wareneingang::class)->handle($artikel, $menge, $preis, today()->toDateString(), 'Lieferschein-Capture', $chargeNr, $mhd, $p->analyse->lieferant_id);
            }

            $p->update([
                'matched_artikel_id' => $artikelId,
                'matched_bestellposition_id' => $bestellpositionId,
                'wareneingang_bewegung_id' => $bewegung->id,
                'status' => PositionStatus::Bestaetigt,
                'entschieden_von' => $userId,
                'entschieden_am' => now(),
            ]);

            $this->matcher->merke($p->text, $p->analyse->lieferant_id, $tenantId, $artikelId);

            return $p->fresh();
        });
    }

    public function verwerfe(LieferscheinPositionVorschlag $p, ?int $userId = null): void
    {
        $p->update([
            'status' => PositionStatus::Verworfen,
            'entschieden_von' => $userId,
            'entschieden_am' => now(),
        ]);
    }

    private function findeOffeneBestellposition(?int $lieferantId, ?int $artikelId, int $tenantId): ?int
    {
        if ($lieferantId === null || $artikelId === null) {
            return null;
        }

        $treffer = Bestellposition::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('artikel_id', $artikelId)
            ->whereHas('bestellung', fn ($q) => $q->where('lieferant_id', $lieferantId))
            ->get()
            ->filter(fn (Bestellposition $p) => $p->offen());

        if ($treffer->count() === 1) {
            return $treffer->first()->id;
        }

        return null;
    }
}
