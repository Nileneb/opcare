<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Models\BelastungFreischaltung;
use App\Domains\Arbeitsschutz\Models\PersoenlicheBelastung;
use App\Domains\Arbeitsschutz\Models\SelbstmeldungUeberlastung;
use App\Domains\Arbeitsschutz\Notifications\SelbstUeberlastung;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Legt eine selbst-initiierte Überlastungsmeldung an (Mode C).
 *
 * Dedupe-Entscheidung: Existiert bereits eine offene Meldung (quittiert_am IS NULL) des Users,
 * wird eine InvalidArgumentException geworfen statt eine zweite anzulegen. Grund: der Zustand
 * „offen" muss explizit quittiert werden bevor eine neue Meldung fachlich sinnvoll ist — eine
 * stille Rückgabe der bestehenden würde den Aufrufer über den Erfolg täuschen (SSOT-Prinzip).
 */
class UeberlastungMelden
{
    /**
     * @throws HttpException 403 ohne aktive Freischaltung
     * @throws InvalidArgumentException wenn bereits eine offene Meldung des Users existiert
     */
    public function handle(User $user, ?string $notiz): SelbstmeldungUeberlastung
    {
        abort_unless(BelastungFreischaltung::aktivFuer($user->tenant_id), 403, 'Selbst-Ampel ist für diesen Mandanten nicht freigeschaltet.');

        $offene = SelbstmeldungUeberlastung::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('quittiert_am')
            ->first();

        if ($offene !== null) {
            throw new InvalidArgumentException('Es existiert bereits eine offene Überlastungsmeldung. Bitte erst quittieren lassen.');
        }

        $wert = PersoenlicheBelastung::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->latest('id')
            ->value('wert') ?? 0;

        $meldung = SelbstmeldungUeberlastung::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'wert' => $wert,
            'notiz' => $notiz,
            'gemeldet_am' => today(),
        ]);

        $admins = User::where('tenant_id', $user->tenant_id)
            ->role(['admin', 'super-admin'])
            ->get();

        Notification::send($admins, new SelbstUeberlastung($user->name, $wert));

        return $meldung;
    }
}
