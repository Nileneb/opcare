<?php

use App\Domains\Ti20\Contracts\ZetaClient;
use App\Domains\Ti20\Data\ProtectedResourceMetadata;
use Illuminate\Support\Facades\Http;

it('parst ein gültiges RFC-9728 Protected-Resource-Metadata-Dokument', function () {
    $meta = ProtectedResourceMetadata::fromArray([
        'resource' => 'https://pep.example/ti2',
        'authorization_servers' => ['https://as.example/realms/zeta-guard'],
    ]);

    expect($meta->resource)->toBe('https://pep.example/ti2')
        ->and($meta->authorizationServers)->toBe(['https://as.example/realms/zeta-guard']);
});

it('weist ein RFC-9728-Dokument ohne resource zurück', function () {
    ProtectedResourceMetadata::fromArray(['authorization_servers' => ['https://as.example']]);
})->throws(InvalidArgumentException::class, 'resource');

it('weist ein RFC-9728-Dokument ohne authorization_servers zurück', function () {
    ProtectedResourceMetadata::fromArray(['resource' => 'https://pep.example/ti2']);
})->throws(InvalidArgumentException::class, 'authorization_servers');

it('führt die ZETA-Service-Discovery über den PEP-well-known-Endpunkt aus', function () {
    config(['ti20.pep_base_url' => 'http://pep.local']);
    Http::fake([
        'pep.local/.well-known/oauth-protected-resource' => Http::response([
            'resource' => 'http://pep.local/ti2',
            'authorization_servers' => ['http://keycloak.local/realms/zeta-guard'],
        ]),
    ]);

    $meta = app(ZetaClient::class)->discoverProtectedResource();

    expect($meta->resource)->toBe('http://pep.local/ti2')
        ->and($meta->authorizationServers)->toContain('http://keycloak.local/realms/zeta-guard');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/.well-known/oauth-protected-resource'));
});
