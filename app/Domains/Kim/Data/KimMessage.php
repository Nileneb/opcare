<?php

namespace App\Domains\Kim\Data;

/**
 * Eine KIM-Nachricht (innere Schicht): Empfänger, Betreff, Dienstkennung + ein Dokument-Anhang
 * (z. B. ein FHIR-Überleitungsbogen). Die äußere S/MIME-Verschlüsselung + Versand sind separat (Transport).
 */
final readonly class KimMessage
{
    public function __construct(
        public string $from,
        public string $to,
        public string $subject,
        public string $dienstkennung,
        public string $body,
        public string $attachmentContent,
        public string $attachmentFilename,
        public string $attachmentContentType = 'application/fhir+json',
    ) {}
}
