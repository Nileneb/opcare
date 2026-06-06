<?php

namespace App\Domains\Ti20\Data;

use InvalidArgumentException;

/**
 * OAuth 2.0 Protected Resource Metadata (RFC 9728) — erster Schritt der ZETA-Client-Registrierung:
 * der Client ruft `${PEP}/.well-known/oauth-protected-resource` ab und erhält dieses Dokument.
 * Architektur-agnostisch + ohne SMC-B verifizierbar (reines HTTP/JSON).
 *
 * @see docs/ti2.0/ti2.0-konformitaets-gate.md
 */
final readonly class ProtectedResourceMetadata
{
    /**
     * @param  array<int, string>  $authorizationServers
     */
    public function __construct(
        public string $resource,
        public array $authorizationServers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // WHY(RFC 9728): resource + authorization_servers sind die Pflichtfelder des Discovery-Dokuments.
        $resource = $data['resource'] ?? null;
        if (! is_string($resource) || $resource === '') {
            throw new InvalidArgumentException('RFC 9728: "resource" fehlt oder ist leer.');
        }

        $servers = $data['authorization_servers'] ?? null;
        if (! is_array($servers) || $servers === [] || array_filter($servers, fn ($s) => ! is_string($s) || $s === '')) {
            throw new InvalidArgumentException('RFC 9728: "authorization_servers" fehlt oder ist ungültig.');
        }

        return new self($resource, array_values($servers));
    }
}
