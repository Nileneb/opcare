# TI 2.0 Auth — Beschaffungs- und Aktivierungs-Runbook

**Ziel:** opcare gegen die gematik-Referenzumgebung (RU) scharf stellen, sobald Test-SMC-B +
Member-ID vorliegen. Bis dahin läuft Mock-Modus (`TI20_ZETA_MOCK_AUTH=true`).

**Voraussetzung lesen:** [`ti2.0-konformitaets-gate.md`](ti2.0-konformitaets-gate.md) — dort steht
die ZETA-Architektur-Entscheidung (Sidecar-Modell) und der Konformitäts-Vertrag.

---

## Was ist gebaut — was wartet auf die Karte

| Baustein | Status | Abhängigkeit |
|---|---|---|
| ZETA-Client-Interface + HTTP-Implementierung | ✅ gebaut | — |
| Service Discovery (RFC 9728) | ✅ gebaut, unit-getestet | PEP/Sidecar erreichbar |
| Test-Fachdienst-Ping + HelloZeta | ✅ gebaut, unit-getestet | laufender zeta-testfachdienst |
| Config-Seams (`config/ti20.php`) | ✅ vollständig | — |
| Mock-Auth-Schalter (`TI20_ZETA_MOCK_AUTH`) | ✅ gebaut | — |
| CI-Konformitäts-Gate (`run-gate.sh`) | ✅ gebaut, cert-gated | SMC-B-Cert als CI-Secret |
| Credential-Check-Skript | ✅ gebaut | — |
| SMC-B-Token-Exchange (echter Auth, C1) | ⏳ wartet auf Karte | Test-SMC-B + ZETA-Guard-Sidecar |
| RU-Konformität live (Testhub-Suiten grün) | ⏳ wartet auf Karte | Test-SMC-B + Member-ID |
| ePA / VSDM2 / POPP (C2) | ⏳ nach C1 | C1 grün |

**Ehrliche Einordnung:**
- **Mock-Auth lokal** (ohne Karte): `HttpZetaClient` macht creds-freie Pings gegen den lokalen
  Test-Fachdienst. Der ZETA-Guard-Sidecar (gematik/ZETA) kann mit einem `SubjectTokenProvider`-Stub
  auch ohne echte SMC-B instanziert werden — für Protokoll-Tests, aber ohne RU-Konformität.
- **Echte RU-Konformität** braucht zwingend SMC-B-Cert + Member-ID.
- **TI 2.0 / HSM-B (kartenlos):** gematik plant für 2026 Pilot/FUT einen SM-B-ORG (HSM-basiert,
  kein physischer Kartenleser). Klassischer IPSec-VPN-Zugangsdienst entfällt bei ZETA (Zero-Trust+TLS).
  Der Sidecar-Ansatz ist HSM-B-kompatibel — opcare-Code ändert sich nicht.

---

## Schritt 1 — Test-SMC-B bestellen

**Was:** Physische Smart Card mit Test-Zertifikat der Pflegeeinrichtung (fiktive Identität, für
TU + RU zugelassen, kein Echtbetrieb).

**Wo:** gematik-Onlineshop
```
https://fachportal.gematik.de/gematik-onlineshop/testkarten
```

**Kosten:** ~35 € (Stand 2025), kein Vertrag, keine Jahreslizenz.

**Was kommt:** ZIP-Archiv mit `.p12`-Datei (der Dateiname enthält `AUT_E256_`).
Ein Begleit-PDF dokumentiert die fiktiven Zertifikatsdaten (TelematikID, Rolle-OID).

**Rolle-OID Pflegeeinrichtung:** `1.2.276.0.76.4.156`
(gemSpec_OID, § 291a SGB V — bereits als Default in `config/ti20.php` hinterlegt)

---

## Schritt 2 — Member-ID beantragen

**Was:** Eintrag von opcare in den gematik-IDP-Entity-Statement-Verbund (TI 2.0 / ZETA-Federation).
Kostenlos, Bearbeitungszeit ~5 Werktage.

**Kontakt:** `idp-registrierung@gematik.de`

**Angaben in der Anfrage:**

| Feld | Inhalt |
|---|---|
| Produktname | opcare |
| Hersteller | (Träger / Organisation) |
| Entity-Statement-URL (HTTPS) | `https://<domain>/.well-known/openid-federation` |
| Public Keys (JWKS) | Signing-Key (ES256/P-256) + Encryption-Key |
| Redirect-URIs | OAuth2-Callback-URLs der opcare-Instanz |
| Kontakt-E-Mail | technischer Ansprechpartner |

**Ergebnis:** gematik teilt eine `member_id` mit → in `TI20_MEMBER_ID` eintragen (Schritt 3).

---

## Schritt 3 — P12 in CI-Secret + lokale `.env` einspielen

### P12 → base64 kodieren

```bash
# Linux
base64 -w 0 /pfad/zur/smcb_private.p12 > smcb.b64

# macOS
base64 -i /pfad/zur/smcb_private.p12 -o smcb.b64
```

### GitHub CI-Secrets setzen

Im GitHub-Repository → Settings → Secrets and variables → Actions:

| Secret-Name | Inhalt |
|---|---|
| `TI20_SMCB_P12_BASE64` | Inhalt von `smcb.b64` |
| `TI20_SMCB_P12_PASSWORD` | Passwort der P12-Datei (aus Begleit-PDF) |

Das CI-Gate (`.github/workflows/ti2.0-conformance.yml`) liest `TI20_SMCB_P12_BASE64` bereits —
kein Workflow-Änderungsbedarf.

### Lokal in `.env` eintragen

```dotenv
TI20_SMCB_P12_BASE64=<Inhalt von smcb.b64>
TI20_SMCB_P12_PASSWORD=<Passwort aus Begleit-PDF>
TI20_SMCB_ROLE_OID=1.2.276.0.76.4.156
TI20_MEMBER_ID=<von gematik mitgeteilte ID>
```

**Credential-Check ausführen** (Diagnose, kein Gate, exit 0):

```bash
bash scripts/ti2.0/check-credentials.sh
```

---

## Schritt 4 — RU-Endpunkte setzen + ZETA-Guard lokal deployen

### Option A — RU-Endpunkt direkt nutzen (empfohlen für ersten Test)

gematik stellt RU-Endpunkte für registrierte Member bereit.
Nach der Member-ID-Vergabe meldet gematik die konkreten URLs mit.

```dotenv
TI20_RU_IDP_URL=https://idp-ref.app.ti-dienste.de          # Platzhalter — echte URL von gematik
TI20_RU_ZETA_GUARD_URL=https://zeta-guard-ref.ti-dienste.de # Platzhalter — echte URL von gematik
TI20_RU_TSL_URL=https://download-test.tsl.ti-dienste.de/ECC/ECC-RSA_TSL-test.xml
```

Die Test-TSL ist öffentlich verfügbar ohne Login:
`https://download-test.tsl.ti-dienste.de/ECC/ECC-RSA_TSL-test.xml`

### Option B — ZETA-Guard lokal (gematik/ZETA, Docker/k8s)

```bash
# ZETA-Guard-Repo klonen (KEIN opcare-Commit)
git clone https://github.com/gematik/ZETA ~/Desktop/WebDev/zeta-guard

# SMC-B mounten + Stack starten (Details im gematik/ZETA-README)
cd ~/Desktop/WebDev/zeta-guard
# smcb_private.p12 → doc/docker/backend/zeta/smcb-private/smcb_private.p12
docker compose up -d
```

Dann lokal:
```dotenv
TI20_ZETA_SIDECAR_URL=http://localhost:8081
TI20_ZETA_PEP_BASE_URL=http://localhost:8080
```

---

## Schritt 5 — Mock-Auth deaktivieren und Gate scharf stellen

Wenn Schritt 1–4 abgeschlossen:

```dotenv
TI20_ZETA_MOCK_AUTH=false
```

**Credential-Check erneut ausführen** — Ausgabe sollte `[SCHARF]` zeigen:

```bash
bash scripts/ti2.0/check-credentials.sh
```

**Konformitäts-Gate ausführen:**

```bash
# Lokal (braucht laufenden ZETA-Guard-Sidecar / RU-Zugang):
bash scripts/ti2.0/run-gate.sh

# CI: automatisch, sobald TI20_SMCB_P12_BASE64 als Secret hinterlegt ist.
# Ohne Secret: sichtbares ::warning:: in GitHub Actions, kein Build-Fehler.
```

---

## Referenzen

| Ressource | URL |
|---|---|
| gematik-Onlineshop (Testkarten) | https://fachportal.gematik.de/gematik-onlineshop/testkarten |
| ZETA-Guard-Repo | https://github.com/gematik/ZETA |
| TI 2.0 Testhub | https://github.com/gematik/ti2.0-testhub |
| Test-Fachdienst | https://github.com/gematik/zeta-testfachdienst |
| Test-TSL | https://download-test.tsl.ti-dienste.de/ECC/ECC-RSA_TSL-test.xml |
| Konformitäts-Gate-Doku | docs/ti2.0/ti2.0-konformitaets-gate.md |
| Schalter-Register | docs/INBETRIEBNAHME.md |
