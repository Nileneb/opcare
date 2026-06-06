<?php

namespace App\Domains\Kim;

use App\Domains\Kim\Data\KimMessage;
use Symfony\Component\Mime\Email;

/**
 * Komponiert die INNERE KIM-Nachricht als RFC-5322/MIME (gemSpec_CM_KOMLE): From/To/Subject/Date +
 * Pflicht-Header `X-KIM-Dienstkennung` + Dokument-Anhang. Diese innere Nachricht wird im Realbetrieb
 * S/MIME-signiert + verschlüsselt und über das KOM-LE-Clientmodul versendet (Anschluss-Ebene).
 */
class KimMessageComposer
{
    public function compose(KimMessage $msg): string
    {
        $email = (new Email)
            ->from($msg->from)
            ->to($msg->to)
            ->subject($msg->subject)
            ->text($msg->body)
            ->attach($msg->attachmentContent, $msg->attachmentFilename, $msg->attachmentContentType);

        // WHY(KIM 1.5+): Dienstkennung ist Pflicht und wird (im Realbetrieb) in den äußeren Header kopiert,
        // damit der Fachdienst inhaltsabhängig zustellen kann.
        $email->getHeaders()->addTextHeader('X-KIM-Dienstkennung', $msg->dienstkennung);

        return $email->toString();
    }
}
