<?php

// Track C (KIM — Kommunikation im Medizinwesen). opcare komponiert die INNERE KIM-Nachricht
// (MIME mit X-KIM-Dienstkennung + FHIR-Anhang). Die äußere S/MIME-Signatur/-Verschlüsselung + der
// Versand über das KOM-LE-Clientmodul sind die Anschluss-Ebene (dormant) — siehe docs/INBETRIEBNAHME.md.
return [
    // KIM-Adresse des Absenders (Einrichtung). Im Betrieb aus den Stammdaten/dem KIM-Konto.
    'sender_address' => env('KIM_SENDER_ADDRESS', 'einrichtung@opcare.kim.telematik-test'),

    // Dienstkennung der KIM-Anwendung (Pflicht ab KIM 1.5). Format: <Anwendung>;<Version>.
    // Default: generischer Dokumentenversand; reale Kennung je Anwendung registriert.
    'dienstkennung' => env('KIM_DIENSTKENNUNG', 'Dokument;1.0'),

    // Schalter: solange kein Clientmodul/Konto angeschlossen ist, läuft der dormant-Transport
    // (komponiert + legt die .eml ab, signiert/sendet aber NICHT). Auf 'smime' umstellen bei Anschluss.
    'transport' => env('KIM_TRANSPORT', 'dormant'),
];
