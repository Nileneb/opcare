# OPCare — Sicherheitskonzept (Track B)

**Zweck:** Nachvollziehbare Darstellung der technischen und organisatorischen Maßnahmen (TOM) gemäß
**DSGVO Art. 32** (Sicherheit der Verarbeitung) und in Anlehnung an **BSI IT-Grundschutz**. Grundlage für
erste Anträge/Selbstauskünfte. **Hinweis:** OPCare ist ein Open-Source-Projekt; verpflichtende TOM treffen
den späteren *Betreiber* (Verantwortlicher i. S. d. DSGVO). Dieses Dokument beschreibt, was die Software
*bereitstellt* und was der Betreiber *ergänzen* muss.

## 1. Verschlüsselung im Transport (in-transit)

- **TLS** ist Betreiber-Pflicht (Reverse-Proxy / Ingress). Die App setzt **HSTS** (`Strict-Transport-Security`,
  1 Jahr, `includeSubDomains; preload`) sobald der Request über HTTPS kommt — siehe `SecurityHeaders`-Middleware.
- Session-Cookies: `Secure`/`HttpOnly`/`SameSite` über die Laravel-Session-Config (Betreiber setzt `SESSION_SECURE_COOKIE=true` in Prod).

## 2. Verschlüsselung im Ruhezustand (at-rest)

Zweistufig — Infrastruktur **und** selektive Feldverschlüsselung in der App:

### 2a. Infrastruktur (Betreiber-Pflicht)
- **Verschlüsselte Volumes / Festplatten** (LUKS / Cloud-Volume-Encryption) für DB-Datenfiles, Backups, Object-Storage.
- **PostgreSQL** in Prod: TDE bzw. verschlüsselte Volumes; Backups verschlüsselt ablegen.
- Schlüsselverwaltung außerhalb der App (KMS / Secrets-Manager); `APP_KEY` als Secret, nicht im Image.

### 2b. App-Feldverschlüsselung (in OPCare implementiert)
Sensible **Gesundheits-Freitext-/Strukturdaten**, die nicht SQL-durchsucht/-sortiert werden, sind über den
Laravel-`encrypted`-Cast (AES-256-GCM via `APP_KEY`) **at-rest verschlüsselt**. Transparent beim Lesen über das
Modell, als Chiffretext in der DB:

| Modell | Feld(er) | Inhalt |
|---|---|---|
| `CareReport` | `text` | Pflegeverlauf |
| `SisTopicFieldEntry` | `freitext`, `strukturdaten` | SIS-Narrativ + Strukturdaten |
| `RiskItem` | `begruendung` | Risiko-Begründung |
| `CareMeasure` | `beschreibung`, `ziel` | Maßnahmen-Freitext |
| `Assessment` | `notiz` | Assessment-Notiz |
| `TranscriptionJob` | `rohtranskript`, `sis_vorschlag` | gesprochene Gesundheitsdaten (Speech-Pipeline) |

**Bewusste Grenze (KISS):** Strukturierte Identifikatoren (Name, Geburtsdatum) bleiben unverschlüsselt, da
Bewohner-Suche/Sortierung auf SQL-Ebene laufen — deren At-Rest-Schutz liefert die Infrastruktur (2a). Eine
durchsuchbare Verschlüsselung (Blind-Index) wäre der nächste Ausbaugrad. Bestandsdaten werden per
idempotenter Migration (`...encrypt_sensitive_freetext_at_rest`) nachverschlüsselt.

## 3. Authentifizierung & Zugriffskontrolle

- **Passwörter:** bcrypt (Laravel `hashed`-Cast).
- **Login-Härtung:** Rate-Limiting (5 Versuche / IP+E-Mail), Session-Regeneration nach Login, `Lockout`-Event.
- **MFA:** _(folgt in diesem Track — TOTP, Pflicht für alle Rollen)._
- **RBAC:** `spatie/laravel-permission` mit Team-Scope je Mandant (Rollen: admin, pflegefachkraft,
  pflegehilfskraft, leserecht, super-admin).
- **Mandantentrennung:** row-level `tenant_id` + globaler `TenantScope`; IDOR-Härtung über
  tenant-skopierte `exists`-Validierung.

## 4. Anwendungssicherheits-Header

`SecurityHeaders`-Middleware (web-Gruppe): `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, `X-XSS-Protection: 0`, restriktive `Permissions-Policy`
(Mikrofon nur `self`), **Content-Security-Policy** (`default-src 'self'`, `frame-ancestors 'none'`,
`form-action 'self'`; `'unsafe-inline'`/`'unsafe-eval'` für Livewire/Alpine erforderlich), HSTS über TLS.

## 5. Protokollierung & Nachvollziehbarkeit

- **Audit-Log** über `spatie/laravel-activitylog` (wer hat was wann geändert).
- Versionierte, append-only Pflegedoku (SIS/Berichte) — Manipulationssicherheit.

## 6. Supply-Chain & statische Analyse (CI-Gates, blockierend)

- **Dependency-CVE-Gate:** `composer audit` (auch wöchentlich per Cron) — schlägt bei bekannter CVE im Lock fehl.
- **SAST:** Semgrep (`p/php`, `p/security-audit`, `p/secrets`) — blockierend bei ERROR-Findings.
- Tests + Linter + FHIR-Konformitäts-Gate ergänzen die Pipeline.

## 7. Offen / Betreiber-Verantwortung

- TLS-Terminierung, verschlüsselte Volumes/Backups, KMS, `SESSION_SECURE_COOKIE`, Netzwerk-Segmentierung,
  Patch-Management, DSGVO-Betroffenenrechte-Prozesse, Lösch-/Aufbewahrungskonzept.
- TI-Anbindung/Zulassung (gematik/BSI-TR) ist **Track C** (aufgeschoben, siehe `docs/roadmap-ti-fhir.md`).
