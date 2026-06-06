<?php

// Track C — TI 2.0 / ZETA-Anbindung. opcare spricht den gematik ZETA Guard als lokalen Sidecar an
// (kein PHP-Krypto-Nachbau). Werte in Prod/Test über env; Defaults passen zum lokalen Testhub.
return [
    // Basis-URL des lokalen ZETA-Guard-Sidecars (er terminiert ZETA: Discovery, Token-Exchange, PEP).
    'sidecar_url' => env('TI20_ZETA_SIDECAR_URL', 'http://localhost:8081'),

    // Basis-URL des Policy Enforcement Point (PEP) — Service Discovery / Datentransfer laufen hierüber.
    'pep_base_url' => env('TI20_ZETA_PEP_BASE_URL', 'http://localhost:8080'),

    // HTTP-Timeout (Sekunden) für Sidecar-Aufrufe.
    'timeout' => (int) env('TI20_ZETA_TIMEOUT', 10),
];
