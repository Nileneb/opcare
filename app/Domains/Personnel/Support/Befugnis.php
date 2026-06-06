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
        if ($taetigkeit->nur_fachkraft && ! $this->istFachkraft($user)) {
            return $taetigkeit->vorbehaltsaufgabe
                ? 'Vorbehaltsaufgabe — nur Pflegefachkraft (§ 4 PflBG)'
                : 'nur Pflegefachkraft';
        }

        $kompetenzId = $taetigkeit->erforderliche_kompetenz_id;
        if ($kompetenzId !== null && ! $this->hatAktiveKompetenz($user, $kompetenzId)) {
            return 'fehlende Kompetenz: '.($taetigkeit->erforderlicheKompetenz->name ?? '—');
        }

        if ($taetigkeit->arzt_anordnung_noetig && ! $this->hatGueltigeDelegation($user, $taetigkeit->id)) {
            return 'keine gültige ärztliche Delegation';
        }

        return null;
    }

    public function darf(User $user, Taetigkeit $taetigkeit): bool
    {
        return $this->hindernis($user, $taetigkeit) === null;
    }

    public function istFachkraft(User $user): bool
    {
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
