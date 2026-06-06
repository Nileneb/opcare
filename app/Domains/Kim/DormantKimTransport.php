<?php

namespace App\Domains\Kim;

use App\Domains\Kim\Contracts\KimTransport;
use App\Domains\Kim\Data\KimMessage;
use Illuminate\Support\Facades\Log;

/**
 * Stillgelegter KIM-Transport (Default, solange kein Clientmodul/Konto angeschlossen ist). Komponiert die
 * innere KIM-Nachricht (RFC 5322) und gibt sie zurück, **signiert/verschlüsselt/sendet aber NICHT**.
 * Der reale S/MIME-Transport wird per `config('kim.transport')` umgeschaltet (Schalter) — Track C.
 *
 * @see docs/INBETRIEBNAHME.md
 */
class DormantKimTransport implements KimTransport
{
    public function __construct(private readonly KimMessageComposer $composer) {}

    public function send(KimMessage $message): string
    {
        $eml = $this->composer->compose($message);

        // WHY: KEIN stilles Verschlucken — sichtbar protokollieren, dass NICHT versendet wurde.
        Log::warning('KIM-Transport ist stillgelegt (kein Clientmodul/Konto) — Nachricht wurde komponiert, aber NICHT gesendet.', [
            'to' => $message->to,
            'dienstkennung' => $message->dienstkennung,
            'aktivieren' => 'docs/INBETRIEBNAHME.md → KIM',
        ]);

        return $eml;
    }
}
