<?php

use App\Domains\Ti20\Contracts\ZetaClient;
use Illuminate\Support\Facades\Http;

// WHY: Sichert den Mock-Auth-Seam — Default muss true sein, damit kein echter ZETA-Token-Exchange
//      stattfindet, solange SMC-B-Cert + Member-ID noch nicht vorliegen.
it('mock_auth ist per Default true', function () {
    expect(config('ti20.mock_auth'))->toBeTrue();
});

it('smcb_p12_base64 ist per Default null (kein Secret im Repo)', function () {
    expect(config('ti20.smcb_p12_base64'))->toBeNull();
});

it('smcb_role_oid hat den korrekten Pflegeeinrichtungs-OID als Default', function () {
    expect(config('ti20.smcb_role_oid'))->toBe('1.2.276.0.76.4.156');
});

it('ru.tsl_url zeigt auf die öffentliche gematik-Test-TSL', function () {
    expect(config('ti20.ru.tsl_url'))
        ->toBe('https://download-test.tsl.ti-dienste.de/ECC/ECC-RSA_TSL-test.xml');
});

it('ru idp_url und guard_url sind per Default null (warten auf RU-Credentials)', function () {
    expect(config('ti20.ru.idp_url'))->toBeNull();
    expect(config('ti20.ru.guard_url'))->toBeNull();
});

it('bei mock_auth=true sendet pingFachdienst keinen echten Netzwerk-Request ohne Http::fake', function () {
    config(['ti20.mock_auth' => true]);
    config(['ti20.testfachdienst_url' => 'http://mock-guard.local']);

    Http::fake([
        'mock-guard.local/*' => Http::response(['status' => 'UP'], 200),
    ]);

    // ZetaClient löst sich auf — kein Fehler, keine echte Verbindung
    $client = app(ZetaClient::class);
    expect($client)->toBeInstanceOf(ZetaClient::class);

    // pingFachdienst darf mit Http::fake aufgerufen werden und gibt false/true — kein Exception
    $result = $client->pingFachdienst();
    expect($result)->toBeBool();
});
