<?php

namespace App\Domains\Ti20\Contracts;

use App\Domains\Ti20\Data\ProtectedResourceMetadata;

/**
 * Abstraktion der ZETA-Zugriffsschicht (TI 2.0). opcare hängt nur an diesem Interface — die konkrete
 * Implementierung delegiert an den gematik ZETA-Guard-Sidecar (kein Protokoll-Nachbau in PHP).
 *
 * C0 (jetzt): Service Discovery (RFC 9728), creds-frei verifizierbar.
 * C1 (mit SMC-B + Sidecar): Token-Exchange (RFC 8693) + Datentransfer über den PEP — werden hier ergänzt.
 *
 * @see docs/ti2.0/ti2.0-konformitaets-gate.md
 */
interface ZetaClient
{
    /**
     * Ruft das Protected-Resource-Metadata-Dokument des PEP ab (RFC 9728) — erster Schritt der
     * ZETA-Client-Registrierung.
     */
    public function discoverProtectedResource(): ProtectedResourceMetadata;
}
