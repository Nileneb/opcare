<?php

namespace App\Domains\Ti20;

use App\Domains\Ti20\Contracts\ZetaClient;
use App\Domains\Ti20\Data\ProtectedResourceMetadata;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * ZETA-Client gegen den gematik ZETA-Guard-Sidecar / PEP + optionalen lokalen Test-Fachdienst.
 *
 * C0: Service Discovery (RFC 9728) via PEP — braucht SMC-B + ZETA-Guard-Sidecar in Prod/RU.
 *     Operativer Ping + HelloZeta gegen den gematik Test-Fachdienst (creds-frei, lokal).
 *
 * @see docs/ti2.0/ti2.0-konformitaets-gate.md
 */
class HttpZetaClient implements ZetaClient
{
    private const WELL_KNOWN_PROTECTED_RESOURCE = '/.well-known/oauth-protected-resource';

    private const HELLO_ZETA_PATH = '/achelos_testfachdienst/hellozeta';

    private const HEALTH_PATH = '/achelos_testfachdienst/actuator/health';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $pepBaseUrl,
        private readonly string $testfachdienstUrl,
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

    public function pingFachdienst(): bool
    {
        try {
            $response = $this->http
                ->timeout($this->timeout)
                ->acceptJson()
                ->get(rtrim($this->testfachdienstUrl, '/').self::HEALTH_PATH);

            return $response->successful() && ($response->json('status') === 'UP');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchHelloZeta(): array
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->acceptJson()
            ->get(rtrim($this->testfachdienstUrl, '/').self::HELLO_ZETA_PATH)
            ->throw();

        return (array) $response->json();
    }
}
