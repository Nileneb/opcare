<?php

// Track C — TI 2.0 / ZETA-Anbindung. opcare spricht den gematik ZETA Guard als lokalen Sidecar an
// (kein PHP-Krypto-Nachbau). Werte in Prod/Test über env; Defaults passen zum lokalen Testhub.
return [
    // Basis-URL des lokalen ZETA-Guard-Sidecars (er terminiert ZETA: Discovery, Token-Exchange, PEP).
    'sidecar_url' => env('TI20_ZETA_SIDECAR_URL', 'http://localhost:8081'),

    // Basis-URL des Policy Enforcement Point (PEP) — Service Discovery / Datentransfer laufen hierüber.
    'pep_base_url' => env('TI20_ZETA_PEP_BASE_URL', 'http://localhost:8080'),

    // Basis-URL des gematik ZETA-Test-Fachdienstes (lokal via Docker, creds-frei).
    // WHY: Test-Fachdienst simuliert die Ressource-Seite im ZETA-Szenario (E-Rezept-CRUD + hello).
    //      RFC-9728-Discovery liegt am PEP/ZETA-Guard, nicht hier — pingFachdienst()/fetchHelloZeta()
    //      belegen operative Erreichbarkeit ohne SMC-B.
    // Repo: https://github.com/gematik/zeta-testfachdienst
    'testfachdienst_url' => env('TI20_ZETA_TESTFACHDIENST_URL', 'http://localhost:8082'),

    // HTTP-Timeout (Sekunden) für Sidecar-Aufrufe.
    'timeout' => (int) env('TI20_ZETA_TIMEOUT', 10),

    // --- Real-Auth-Seams (C1) ---
    // WHY: Solange mock_auth=true sendet opcare keinen echten ZETA-Token-Exchange.
    //      Schalter wird auf false gesetzt sobald SMC-B-Cert + Member-ID vorliegen (siehe Runbook).
    'mock_auth' => (bool) env('TI20_ZETA_MOCK_AUTH', true),

    // WHY: SMC-B-Identität der Pflegeeinrichtung — PKCS#12 base64-kodiert (kein Klartext im Repo).
    //      Wird vom ZETA-Guard-Sidecar gemountet; opcare selbst macht kein Brainpool-Krypto in PHP.
    //      Beschaffung: gematik-Onlineshop Test-SMC-B (~35 €), kein Vertrag nötig.
    'smcb_p12_base64' => env('TI20_SMCB_P12_BASE64'),
    'smcb_p12_password' => env('TI20_SMCB_P12_PASSWORD'),

    // WHY: OID der SMC-B-Rolle Pflegeeinrichtung (§ 291a SGB V / gemSpec_OID).
    //      Default = 1.2.276.0.76.4.156 (Pflegeeinrichtung nach gematik-OID-Katalog, Stand 2025).
    'smcb_role_oid' => env('TI20_SMCB_ROLE_OID', '1.2.276.0.76.4.156'),

    // WHY: Member-ID wird von gematik vergeben (kostenfrei, ~5 Werktage via idp-registrierung@gematik.de).
    //      Pflicht für den IDP-Entity-Statement-Eintrag (TI 2.0 / ZETA-Federation).
    'member_id' => env('TI20_MEMBER_ID'),

    // Referenzumgebung (RU) — Endpunkte für echten ZETA-Auth gegen gematik-RU.
    // WHY: RU-Endpunkte von Prod getrennt halten; so kann mock_auth=false lokal gegen RU testen
    //      ohne Prod-Infrastruktur zu berühren. IDP/Guard-URLs kommen aus der gematik-Dokumentation.
    'ru' => [
        'idp_url' => env('TI20_RU_IDP_URL'),
        'guard_url' => env('TI20_RU_ZETA_GUARD_URL'),
        // WHY: Test-TSL öffentlich verfügbar ohne Login — kein Secret nötig.
        'tsl_url' => env('TI20_RU_TSL_URL', 'https://download-test.tsl.ti-dienste.de/ECC/ECC-RSA_TSL-test.xml'),
    ],
];
