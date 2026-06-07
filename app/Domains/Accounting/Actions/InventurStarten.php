<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Support\Lagerwert;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Startet eine Inventur (§§ 240/241 HGB): legt die Kampagne an und snapshottet je aktivem Artikel eine
 * Zählposition mit der aktuellen Soll-Menge und dem FIFO-Bewertungsschnitt (für den späteren Differenzwert).
 */
class InventurStarten
{
    public function __construct(private readonly Lagerwert $lagerwert) {}

    public function handle(string $stichtag, ?Abteilung $abteilung, ?int $userId): Inventur
    {
        $tenantId = app(CurrentTenant::class)->id();

        return DB::transaction(function () use ($stichtag, $abteilung, $userId, $tenantId) {
            $inventur = Inventur::create([
                'tenant_id' => $tenantId,
                'abteilung' => $abteilung?->value,
                'stichtag' => $stichtag,
                'status' => InventurStatus::Offen->value,
                'erstellt_von' => $userId,
            ]);

            $artikel = Artikel::where('tenant_id', $tenantId)->where('aktiv', true)
                ->when($abteilung, fn ($q) => $q->where('abteilung', $abteilung->value))->get();

            foreach ($artikel as $a) {
                $sollMenge = (float) $a->bestand;
                $wert = $this->lagerwert->bestandswert($a);
                $schnitt = $sollMenge > 0 ? round($wert / $sollMenge, 4) : (float) ($a->einkaufspreis ?? 0);
                $inventur->positionen()->create([
                    'tenant_id' => $tenantId,
                    'artikel_id' => $a->id,
                    'soll_menge' => $sollMenge,
                    'einstandspreis_schnitt' => $schnitt,
                ]);
            }

            return $inventur->load('positionen');
        });
    }
}
