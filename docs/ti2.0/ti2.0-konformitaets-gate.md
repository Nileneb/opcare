# Track C — TI 2.0 Konformitäts-Gate (gematik Testhub 2.0)

**Quelle:** [gematik/ti2.0-testhub](https://github.com/gematik/ti2.0-testhub) — offizielle Testplattform für die
**TI 2.0** (modernisierte, Konnektor-lose Telematikinfrastruktur mit **Zero-Trust-Architektur / ZETA**).
Ersetzt das frühere „Konnektor-Light"-Bild im Roadmap-Track-C.

**Leitprinzip (wie Track A/B):** Gegen die veröffentlichte Testsuite *vorbauen* hat **kein Rechtsgate** —
das Gate betrifft nur den späteren Echtbetrieb/die Zulassung. „Konformitäts-Gate zuerst": Vertrag verstehen +
Gate aufsetzen, **bevor** Client-Code entsteht.

## Was der Testhub testet (3 Suiten, Cucumber/Gherkin auf gematik **Tiger**)

- **zeta-testsuite** — Zero-Trust-Kern: Service-Discovery, Client-Registrierung, SMC-B-Authentisierung,
  PEP-Header-Management, TLS-Guard, REST-/WebSocket-Datentransfer über den PEP, Policy-Updateability, Smoke.
- **popp-testsuite** — Proof-of-Possession (eGK-Besitznachweis, JWKS, EntityStatement, HashDB-Import).
- **vsdm-testsuite** — Versichertenstammdatenmanagement 2.0 (RVSD lesen, Fehlerfälle, Last).

Stack: Java 21, Maven (`./mvnw`), Docker-Compose-Mock-Services (PEP/PDP/OPA/Ingress, VSDM-Server,
POPP-Token-Generator), Tiger-Proxy als Sniffer. opcare ist darin die **zu testende Client-Anwendung**.

## Der ZETA-Client-Konformitäts-Vertrag (aus den Feature-Files extrahiert)

Ein TI-2.0-Client (= opcare-Zugriffsschicht) muss:

1. **Service Discovery (RFC 9728):** `GET ${PEP}/.well-known/oauth-protected-resource` → Dokument mit
   `resource` + `authorization_servers`; danach OAuth-AS-Metadata via PEP → Keycloak
   (`/auth/realms/zeta-guard/.well-known/...`). _(läuft rein über HTTP/JSON, kein SMC-B nötig)_
2. **Client-Registrierung** beim ZETA-Authorization-Server (Keycloak/OAuth2).
3. **SMC-B-Authentisierung / Token-Exchange (RFC 8693):**
   - SMC-B-Zertifikat aus `.p12` laden;
   - `subject_token` mit **Brainpool P-256 R1** signieren (enthält `sub` = TelematikID + `professionOid`);
   - `client_assertion`-JWT (**ES256/P-256**) erzeugen;
   - Token-Exchange an den PDP-Token-Endpunkt → `access_token`.
4. **Datentransfer über den PEP:** ZETA-/PEP-Header anhängen, mTLS/TLS-Guard, REST **und** WebSocket;
   für eGK/VSDM zusätzlich **POPP**.

## Harte Abhängigkeit: SMC-B-Testzertifikat (organisationsgebunden)

Der ZETA-Backend mountet `smcb_private.p12` als Pflicht-Volume → **der Testhub bootet nicht ohne**. Mitgeliefert
sind nur Truststore + Tiger-Proxy-Keys, **nicht** der private SMC-B-Schlüssel. Anforderung (nur durch die
Organisation möglich): gematik-Anfrageportal → Test-SMC-B als ZIP → `.p12` mit `AUT_E256_` im Namen →
umbenennen zu `smcb_private.p12` → nach `doc/docker/backend/zeta/smcb-private/`.

| Was | ohne SMC-B-Cert | mit SMC-B-Cert |
|---|---|---|
| Vertrag/Spec dokumentieren | ✅ | ✅ |
| Client-Request-Konstruktion unit-testen (Fixtures) | ✅ | ✅ |
| Testhub booten (Compose) | ❌ | ✅ |
| Smoke / Service-Discovery / SMC-B-Auth / VSDM2 live | ❌ | ✅ |

## Architektur-Entscheidung opcare: ZETA als **Sidecar** (Empfehlung)

gematik liefert eine **ZETA-Client/Guard-SDK** (open source, „production-capable", für Hersteller-Integration
gedacht; vgl. [gematik/ZETA](https://github.com/gematik/ZETA)). Empfehlung: opcare betreibt den **ZETA Guard als
Sidecar** und spricht ihn lokal über plain HTTP an — opcare reimplementiert **nicht** Brainpool-P256 / RFC 8693 /
PEP in PHP (PHP-Brainpool-Support ist schwach; Nachbau zertifizierter Krypto = Risiko + Wartungslast).
opcare-Aufgabe schrumpft auf: HTTP-Aufrufe an den lokalen ZETA-Guard + Fachdaten (FHIR aus Track A).

Alternative (nicht empfohlen): PHP-nativer ZETA-Client — voller Protokoll-/Krypto-Nachbau.

## Gate-Aufsetzung (vorgeschlagen, sobald Architektur bestätigt)

- **Testhub vendoren/pinnen** (Submodule oder fixierte Version) als Konformitäts-Ziel.
- **CI-Gate-Skeleton:** Workflow, der den Testhub via Compose bootet + relevante Suiten fährt — **gated auf**
  das SMC-B-Cert als CI-Secret. Fehlt das Secret: Job läuft, **loggt explizit „SMC-B fehlt → übersprungen"**
  (kein stilles Grün), bricht den Build nicht. Liegt es vor: blockierend wie `fhir-validate`.
- **opcare-Seam** (`app/Domains/Ti20/`): `ZetaClient`-Interface (Sidecar-HTTP), Vertrag aus Abschnitt oben
  als Pest-Unit-Tests gegen Fixtures (Request-Form/Discovery-Parsing) — architektur-agnostisch verifizierbar.

## gematik ZETA-Test-Fachdienst (lokal, creds-frei)

**Repo:** [github.com/gematik/zeta-testfachdienst](https://github.com/gematik/zeta-testfachdienst)

Der Test-Fachdienst (Spring Boot, Java 21, H2) simuliert die **Ressource-Seite** im ZETA-Szenario —
also den Dienst *hinter* dem PEP (E-Rezept-CRUD + Hello-Endpunkt). Er ist **kein PEP** und exponiert
**kein** `/.well-known/oauth-protected-resource` (RFC 9728) — dieses Dokument liegt am PEP/ZETA-Guard
(C1, braucht SMC-B).

### Was er exponiert (HTTP, kein Auth)

| Pfad | Beschreibung |
|---|---|
| `GET /achelos_testfachdienst/hellozeta` | `{"message":"Hello ZETA!"}` — Ping/Smoke |
| `GET /achelos_testfachdienst/actuator/health` | `{"status":"UP"}` — Health-Probe |
| `GET/POST/PUT/DELETE /achelos_testfachdienst/api/erezept` | E-Rezept-CRUD (H2-backed) |
| `GET /achelos_testfachdienst/swagger-ui/index.html` | Swagger UI |

### Was er NICHT exponiert (braucht PEP/ZETA-Guard, C1)

`/.well-known/oauth-protected-resource` (RFC 9728) — fehlt im Test-Fachdienst absichtlich (er ist
die Ressource, nicht der PEP). opcares `discoverProtectedResource()` läuft in C1 gegen den
ZETA-Guard-Sidecar.

### Lokal hochbringen

```bash
# Einmalig klonen (KEIN opcare-Commit):
git clone https://github.com/gematik/zeta-testfachdienst ~/Desktop/WebDev/zeta-testfachdienst

# Über ai-services.sh (empfohlen — prüft erst Erreichbarkeit):
scripts/ai-services.sh up zeta

# Oder direkt via Docker (Multi-Stage-Build, kein lokales Gradle):
docker build -f ~/Desktop/WebDev/zeta-testfachdienst/Dockerfile.opcare-dev \
  -t zeta-testfachdienst:opcare-dev ~/Desktop/WebDev/zeta-testfachdienst
docker run -d --name zeta-testfachdienst-dev -p 8082:8080 \
  -e SERVER_SSL_ENABLED=false zeta-testfachdienst:opcare-dev
```

**Port:** 8082 (Default, konfigurierbar via `TI20_ZETA_TESTFACHDIENST_URL`).

### Anbindung opcare

`HttpZetaClient` implementiert neben `discoverProtectedResource()` (C1, PEP-gebunden) zwei
creds-freie Methoden:

- `pingFachdienst()` — HTTP-GET auf `/actuator/health`, gibt `true` wenn `status == "UP"`.
- `fetchHelloZeta()` — HTTP-GET auf `/hellozeta`, gibt das JSON-Payload-Array zurück.

Konfiguration: `TI20_ZETA_TESTFACHDIENST_URL` (Default `http://localhost:8082`).

### Tests

```bash
# Unit-Tests (Http::fake, immer grün):
php vendor/bin/pest tests/Feature/Ti20/ZetaTestfachdienstTest.php

# Integrations-Tests (brauchen laufenden Test-Fachdienst):
TI20_ZETA_TESTFACHDIENST_URL=http://localhost:8082 \
  php vendor/bin/pest tests/Feature/Ti20/ZetaTestfachdienstTest.php
```

Ohne laufenden Container skippen die Integrations-Tests sauber (`markTestSkipped`).

## Reihenfolge

1. **C0 (jetzt, creds-frei):** dieses Dokument; Testhub vendoren; Seam + Discovery/Request-Konstruktion
   unit-getestet; CI-Skeleton (cert-gated). **Test-Fachdienst lokal angebunden** — operativ verifiziert.
2. **C1 (braucht SMC-B-Cert + Sidecar-Image):** ZETA-Guard-Sidecar einsetzen; Testhub-Suiten live grün;
   `discoverProtectedResource()` gegen echten PEP.
3. **C2:** ePA-Schreibzugriff / VSDM2-Fachlogik auf der grünen ZETA-Schicht (FHIR aus Track A wiederverwenden).
