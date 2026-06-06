<?php

namespace App\Domains\Kim\Contracts;

use App\Domains\Kim\Data\KimMessage;

/**
 * Abstraktion des KIM-Versands. opcare hängt nur am Interface. Der reale Transport
 * (S/MIME-Signatur + -Verschlüsselung über das KOM-LE-Clientmodul) ist die Anschluss-Ebene und wird
 * eingehängt, sobald KIM-Konto + Clientmodul vorliegen — siehe docs/INBETRIEBNAHME.md.
 */
interface KimTransport
{
    /** Versendet (bzw. komponiert, solange dormant) die Nachricht und liefert die RFC-5322-Repräsentation. */
    public function send(KimMessage $message): string;
}
