<?php

namespace App\Domains\Import\Services;

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Support\LieferantMatch;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportZeile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportCommit
{
    public function __construct(private readonly ArtikelMatcher $matcher) {}

    public function commit(ImportZeile $z, int $tenantId, ?int $userId = null): ImportZeile
    {
        if (! $z->offen()) {
            throw new RuntimeException("ImportZeile #{$z->id} ist nicht mehr offen (Status: {$z->status->value}).");
        }

        return DB::transaction(function () use ($z, $tenantId) {
            if ($z->ziel_typ === 'lieferant') {
                $this->commitLieferant($z, $tenantId);
            } else {
                $this->commitArtikel($z, $tenantId);
            }

            $z->save();

            return $z->fresh();
        });
    }

    private function commitLieferant(ImportZeile $z, int $tenantId): void
    {
        if ($z->aktion === ImportAktion::Ueberspringen) {
            $z->status = ImportZeileStatus::Uebersprungen;

            return;
        }

        if ($z->aktion === ImportAktion::Mergen) {
            $z->ergebnis_lieferant_id = $z->matched_lieferant_id;
        } else {
            $lief = Lieferant::create([
                'tenant_id' => $tenantId,
                'name' => $z->name ?: $z->lieferant_text,
            ]);
            $z->ergebnis_lieferant_id = $lief->id;
        }

        $z->status = ImportZeileStatus::Importiert;
    }

    private function commitArtikel(ImportZeile $z, int $tenantId): void
    {
        if ($z->aktion === ImportAktion::Ueberspringen) {
            $z->status = ImportZeileStatus::Uebersprungen;

            return;
        }

        if ($z->aktion === ImportAktion::Anlegen) {
            $abteilung = Abteilung::tryFrom((string) $z->abteilung) ?? Abteilung::Verwaltung;
            $ziel = Artikel::create([
                'tenant_id' => $tenantId,
                'name' => $z->name,
                'einheit' => $z->einheit ?: 'Stück',
                'abteilung' => $abteilung,
                'bestand' => 0,
                'mindestbestand' => $z->mindestbestand,
                'einkaufspreis' => $z->einkaufspreis,
                'pg_nummer' => $z->pg_nummer,
            ]);
        } else {
            $ziel = Artikel::findOrFail($z->matched_artikel_id);

            if ($ziel->mindestbestand === null && $z->mindestbestand !== null) {
                $ziel->mindestbestand = $z->mindestbestand;
            }
            if ($ziel->einkaufspreis === null && $z->einkaufspreis !== null) {
                $ziel->einkaufspreis = $z->einkaufspreis;
            }
            if ($ziel->pg_nummer === null && $z->pg_nummer !== null) {
                $ziel->pg_nummer = $z->pg_nummer;
            }
            $ziel->save();

            $this->matcher->merke((string) $z->name, null, $tenantId, $ziel->id);
        }

        if ((float) $z->bestand > 0) {
            $lieferantId = LieferantMatch::finde((string) ($z->lieferant_text ?? ''), $tenantId)?->id;
            $gegenkonto = $z->batch->anfangsbestand_modus === 'verbindlichkeit'
                ? null
                : AccountingDefaults::ANFANGSBESTAND;

            $bewegung = app(Wareneingang::class)->handle(
                $ziel,
                (float) $z->bestand,
                $z->einstandspreis !== null ? (float) $z->einstandspreis : ($z->einkaufspreis !== null ? (float) $z->einkaufspreis : null),
                today()->toDateString(),
                'Anfangsbestand-Import',
                $z->charge_nr,
                $z->mhd?->toDateString(),
                $lieferantId,
                $gegenkonto,
            );

            $z->wareneingang_bewegung_id = $bewegung->id;
        }

        $z->ergebnis_artikel_id = $ziel->id;
        $z->status = ImportZeileStatus::Importiert;
    }
}
