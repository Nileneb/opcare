<?php

use App\Domains\Ti20\Contracts\ZetaClient;
use App\Domains\Ti20\HttpZetaClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Unit-Test (Http::fake) — pinnt den ZetaClient-Vertrag deterministisch.
 * Läuft immer grün, ohne laufenden Test-Fachdienst.
 */
it('ping meldet true wenn der Test-Fachdienst UP zurückgibt', function () {
    config(['ti20.testfachdienst_url' => 'http://zeta-testfachdienst.local']);
    Http::fake([
        'zeta-testfachdienst.local/achelos_testfachdienst/actuator/health' => Http::response(
            ['status' => 'UP', 'groups' => ['liveness', 'readiness']],
            200
        ),
    ]);

    $result = app(ZetaClient::class)->pingFachdienst();

    expect($result)->toBeTrue();
    Http::assertSent(fn ($req) => str_contains($req->url(), '/actuator/health'));
});

it('ping meldet false bei einem HTTP-Fehler', function () {
    config(['ti20.testfachdienst_url' => 'http://zeta-testfachdienst.local']);
    Http::fake([
        'zeta-testfachdienst.local/achelos_testfachdienst/actuator/health' => Http::response('', 503),
    ]);

    $result = app(ZetaClient::class)->pingFachdienst();

    expect($result)->toBeFalse();
});

it('fetchHelloZeta liefert das Payload-Array des Test-Fachdienstes', function () {
    config(['ti20.testfachdienst_url' => 'http://zeta-testfachdienst.local']);
    Http::fake([
        'zeta-testfachdienst.local/achelos_testfachdienst/hellozeta' => Http::response(
            ['message' => 'Hello ZETA!'],
            200
        ),
    ]);

    $payload = app(ZetaClient::class)->fetchHelloZeta();

    expect($payload)->toBeArray()
        ->and($payload['message'])->toBe('Hello ZETA!');
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/hellozeta'));
});

it('HttpZetaClient ist direkt ohne Container instanzierbar', function () {
    $client = new HttpZetaClient(
        app(Factory::class),
        'http://pep.local',
        'http://zeta-testfachdienst.local',
    );

    expect($client)->toBeInstanceOf(ZetaClient::class);
});

/**
 * Integrations-Test gegen den laufenden lokalen gematik ZETA-Test-Fachdienst.
 *
 * Skippt sauber wenn der Dienst nicht erreichbar ist — CI/Suite ohne Sidecar bleibt grün.
 *
 * Dienst starten: scripts/ai-services.sh up zeta
 * Dann: php vendor/bin/pest tests/Feature/Ti20/ZetaTestfachdienstTest.php
 */
describe('operativer Test gegen lokalen Test-Fachdienst', function () {
    beforeEach(function () {
        $url = config('ti20.testfachdienst_url', 'http://localhost:8082');
        $reachable = @file_get_contents(
            rtrim($url, '/').'/achelos_testfachdienst/actuator/health',
            false,
            stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]])
        );
        if ($reachable === false || ! str_contains((string) $reachable, '"UP"')) {
            $this->markTestSkipped(
                "gematik ZETA-Test-Fachdienst nicht erreichbar ($url) — "
                ."'scripts/ai-services.sh up zeta' zum Starten."
            );
        }
    });

    it('pingFachdienst() meldet true gegen den echten Test-Fachdienst', function () {
        Http::preventStrayRequests(false);
        $result = app(ZetaClient::class)->pingFachdienst();

        expect($result)->toBeTrue();
    });

    it('fetchHelloZeta() liefert {"message":"Hello ZETA!"} vom echten Test-Fachdienst', function () {
        Http::preventStrayRequests(false);
        $payload = app(ZetaClient::class)->fetchHelloZeta();

        expect($payload)->toBeArray()
            ->and($payload)->toHaveKey('message')
            ->and($payload['message'])->toContain('Hello ZETA');
    });
});
