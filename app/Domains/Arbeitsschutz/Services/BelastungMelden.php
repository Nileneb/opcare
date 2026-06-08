<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Data\BelastungsBefund;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Notifications\BelastungKritisch;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\Notification;

/**
 * Legt eine Belastungsmeldung an wenn die Stufe meldepflichtig ist.
 * Norm-Anker: § 6 ArbSchG (Dokumentation), § 5 Abs. 3 Nr. 6 ArbSchG.
 */
class BelastungMelden
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Gibt null zurück wenn:
     * - Stufe nicht meldepflichtig (Gering/Erhoeht)
     * - Für diese station_id bereits eine offene (quittiert_am IS NULL) Meldung existiert (Dedupe)
     */
    public function handle(BelastungsBefund $befund): ?Belastungsmeldung
    {
        if (! $befund->stufe->istMeldepflichtig()) {
            return null;
        }

        $tenantId = $this->currentTenant->id();

        // WHY: Dedupe — SSOT ist quittiert_am IS NULL; verhindert Notification-Spam solange Meldung offen
        $offeneExistiert = Belastungsmeldung::where('tenant_id', $tenantId)
            ->where('station_id', $befund->stationId)
            ->whereNull('quittiert_am')
            ->exists();

        if ($offeneExistiert) {
            return null;
        }

        $meldung = Belastungsmeldung::create([
            'tenant_id' => $tenantId,
            'station_id' => $befund->stationId,
            'wohnbereich' => $befund->wohnbereich,
            'stufe' => $befund->stufe,
            'score' => $befund->score,
            'signale' => $befund->signale,
            'gemeldet_am' => today(),
        ]);

        $topSignal = array_key_first($befund->signale) ?? '';
        $topWert = $befund->signale[$topSignal] ?? '';

        $empfaenger = User::where('tenant_id', $tenantId)
            ->get()
            ->filter(fn (User $u) => $u->hasAnyRole(['admin', 'super-admin']));

        Notification::send($empfaenger, new BelastungKritisch(
            wohnbereich: $befund->wohnbereich,
            stufe: $befund->stufe->label(),
            score: $befund->score,
            topSignal: $topSignal ? "{$topSignal}: {$topWert}" : '',
        ));

        return $meldung;
    }
}
