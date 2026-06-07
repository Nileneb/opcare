<?php

namespace App\Domains\Ti20\Contracts;

use App\Domains\Ti20\Data\ProtectedResourceMetadata;

/**
 * Abstraktion der ZETA-Zugriffsschicht (TI 2.0). opcare hängt nur an diesem Interface — die konkrete
 * Implementierung delegiert an den gematik ZETA-Guard-Sidecar (kein Protokoll-Nachbau in PHP).
 *
 * C0 (jetzt): Service Discovery (RFC 9728), creds-frei verifizierbar.
 *             Operativer Reachability-Ping gegen den gematik Test-Fachdienst (lokal).
 * C1 (mit SMC-B + Sidecar): Token-Exchange (RFC 8693) + Datentransfer über den PEP — werden hier ergänzt.
 *
 * @see docs/ti2.0/ti2.0-konformitaets-gate.md
 */
interface ZetaClient
{
    /**
     * Ruft das Protected-Resource-Metadata-Dokument des PEP ab (RFC 9728) — erster Schritt der
     * ZETA-Client-Registrierung.
     *
     * WHY: Discovery läuft gegen den PEP/ZETA-Guard-Sidecar (braucht SMC-B + RU-Zulassung in Prod).
     *      Im Dev/Test zeigt `pingFachdienst()` + `fetchHelloZeta()` operative Erreichbarkeit ohne Karten.
     */
    public function discoverProtectedResource(): ProtectedResourceMetadata;

    /**
     * Prüft die Erreichbarkeit des gematik ZETA-Test-Fachdienstes via Health-Probe.
     *
     * WHY: Der Test-Fachdienst (github.com/gematik/zeta-testfachdienst) simuliert die Ressource-Seite
     *      im ZETA-Szenario, exponiert aber KEIN /.well-known/oauth-protected-resource (das gehört dem
     *      PEP/ZETA-Guard). pingFachdienst() belegt operativ, dass opcare gegen einen echten gematik-
     *      ZETA-Testdienst sprechen kann — ohne SMC-B oder RU-Zulassung (creds-frei).
     */
    public function pingFachdienst(): bool;

    /**
     * Ruft den /hellozeta-Endpunkt des Test-Fachdienstes ab und gibt das Payload-Array zurück.
     *
     * WHY: Smoke-Test für End-to-End-Erreichbarkeit im lokalen Dev-Stack; belegt dass opcare
     *      HTTP-Requests an gematik-ZETA-Dienste erfolgreich durchführen kann.
     *
     * @return array<string, mixed>
     */
    public function fetchHelloZeta(): array;
}
