<?php

namespace App\Domains\Personnel\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Models\Delegation;
use App\Domains\Personnel\Models\MitarbeiterKompetenz;
use App\Domains\Personnel\Models\Taetigkeit;

/**
 * Prüft, ob eine Person eine Tätigkeit ausführen darf: Mindestqualifikation (Fachkraft-Vorbehalt § 4 PflBG),
 * erforderliche Zusatzkompetenz (aktiv) und — bei ärztlich anzuordnenden Tätigkeiten — eine gültige Delegation.
 * Gibt den blockierenden Grund zurück (null = darf). Eine Quelle der Wahrheit für UI und Doku-Guards.
 */
class Befugnis
{
    public function hindernis(User $user, Taetigkeit $taetigkeit): ?string
    {
        $istFachkraft = $this->istFachkraft($user);

        if ($taetigkeit->nur_fachkraft && ! $istFachkraft) {
            return $taetigkeit->vorbehaltsaufgabe
                ? 'Vorbehaltsaufgabe — nur Pflegefachkraft (§ 4 PflBG)'
                : 'nur Pflegefachkraft';
        }

        // Erforderliche Kompetenz: für Fachkräfte gelten LG-Stufen als inhärent (gewaivt); Spezial-
        // qualifikationen (z. B. eigenständige Heilkunde nach BEEP, B.Sc.) gelten auch für Fachkräfte.
        $kompetenzId = $taetigkeit->erforderliche_kompetenz_id;
        if ($kompetenzId !== null) {
            $verlangt = ! $istFachkraft || $taetigkeit->kompetenz_auch_fuer_fachkraft;
            if ($verlangt && ! $this->hatAktiveKompetenz($user, $kompetenzId)) {
                return 'fehlende Kompetenz: '.($taetigkeit->erforderlicheKompetenz->name ?? '—');
            }
        }

        // Ärztliche Anordnung: für eine Fachkraft liegt sie als Verordnung vor; eine Hilfskraft braucht
        // eine explizite, gültige Delegation.
        if ($taetigkeit->arzt_anordnung_noetig && ! $istFachkraft && ! $this->hatGueltigeDelegation($user, $taetigkeit->id)) {
            return 'keine gültige ärztliche Delegation';
        }

        return null;
    }

    public function darf(User $user, Taetigkeit $taetigkeit): bool
    {
        return $this->hindernis($user, $taetigkeit) === null;
    }

    /** Komfort-Guard für Aufrufer (SIS/Medikation/BtM): löst den Katalog auf und prüft. Fehlt die Tätigkeit
     *  im Katalog, wird nicht blockiert (fail-open) — der Katalog wird hier idempotent sichergestellt. */
    public function darfKey(User $user, string $taetigkeitKey): bool
    {
        $tenantId = (int) $user->tenant_id;
        TaetigkeitDefaults::ensureFor($tenantId);
        $taetigkeit = Taetigkeit::where('tenant_id', $tenantId)->where('key', $taetigkeitKey)->where('aktiv', true)->first();

        return $taetigkeit === null || $this->darf($user, $taetigkeit);
    }

    public function istFachkraft(User $user): bool
    {
        // Rolle (Leitung/Pflegefachkraft) ODER Qualifikation in der Personalakte ODER eine aktive
        // Grundberuf-Kompetenz mit ist_fachkraft.
        if ($user->hasAnyRole(['admin', 'super-admin', 'pflegefachkraft'])) {
            return true;
        }
        if ($user->employeeProfile?->qualifikation?->istFachkraft() ?? false) {
            return true;
        }

        return MitarbeiterKompetenz::where('user_id', $user->id)->whereHas('kompetenz', fn ($q) => $q->where('ist_fachkraft', true))
            ->get()->contains(fn (MitarbeiterKompetenz $mk) => $mk->aktiv());
    }

    private function hatAktiveKompetenz(User $user, int $kompetenzId): bool
    {
        $mk = MitarbeiterKompetenz::where('user_id', $user->id)->where('kompetenz_id', $kompetenzId)->first();

        return $mk !== null && $mk->aktiv();
    }

    private function hatGueltigeDelegation(User $user, int $taetigkeitId): bool
    {
        return Delegation::where('nehmer_id', $user->id)->where('taetigkeit_id', $taetigkeitId)
            ->get()->contains(fn (Delegation $d) => $d->aktiv());
    }
}
