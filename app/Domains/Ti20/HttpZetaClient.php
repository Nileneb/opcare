<?php

namespace App\Domains\Ti20;

use App\Domains\Ti20\Contracts\ZetaClient;
use App\Domains\Ti20\Data\ProtectedResourceMetadata;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * ZETA-Client gegen den gematik ZETA-Guard-Sidecar / PEP. C0 implementiert die Service Discovery
 * (RFC 9728) — der Sidecar terminiert ZETA (mTLS/Token); opcare spricht plain HTTP.
 *
 * @see docs/ti2.0/ti2.0-konformitaets-gate.md
 */
class HttpZetaClient implements ZetaClient
{
    private const WELL_KNOWN_PROTECTED_RESOURCE = '/.well-known/oauth-protected-resource';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $pepBaseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function discoverProtectedResource(): ProtectedResourceMetadata
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->acceptJson()
            ->get(rtrim($this->pepBaseUrl, '/').self::WELL_KNOWN_PROTECTED_RESOURCE)
            ->throw();

        return ProtectedResourceMetadata::fromArray($response->json());
    }
}
