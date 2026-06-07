# gematik app-referencevalidator — Integration und Diagnose-Ergebnisse

## Was integriert ist

- **Validator:** `gematik app-referencevalidator` v2.16.2 (offline Fat-JAR, ~201 MB)
- **Module:** `erp` (E-Rezept, eingebaut) · `isip1` (ISiP-Basismodul v1, Plugin-ZIP ~11 MB)
- **Download:** idempotent via `scripts/fhir/validate.sh` (curl beim ersten Aufruf)
- **Ablage:** `tools/fhir-validator/` (gitignored — JAR und Plugins werden nicht committet)

## Nutzung

```bash
# Einzelne Ressource prüfen
scripts/fhir/validate.sh isip1 storage/app/fhir-samples/isip-patient.json

# Alle ISiP-Ressourcen auf einmal
scripts/fhir/validate.sh isip1 \
  storage/app/fhir-samples/isip-patient.json \
  storage/app/fhir-samples/isip-encounter.json \
  storage/app/fhir-samples/isip-organization.json \
  storage/app/fhir-samples/isip-relatedperson.json \
  storage/app/fhir-samples/isip-practitioner.json

# E-Rezept-Bundle
scripts/fhir/validate.sh erp storage/app/fhir-samples/erezept.json
```

### Voraussetzungen

- JDK 21 (`java -version`)
- `jq` (`apt install jq`)
- Internet-Zugang beim ersten Aufruf (JAR + Plugin werden geladen)

### FHIR-Samples erzeugen

```bash
# 1. Demo-Datenbank aufsetzen (im App-Container)
docker compose exec app php artisan migrate:fresh --seed --force

# 2. Samples exportieren
docker compose exec app php artisan isip:export patient   --output /var/www/storage/app/fhir-samples/isip-patient.json
docker compose exec app php artisan isip:export encounter --output /var/www/storage/app/fhir-samples/isip-encounter.json
docker compose exec app php artisan isip:export organization --output /var/www/storage/app/fhir-samples/isip-organization.json
docker compose exec app php artisan isip:export angehoeriger --output /var/www/storage/app/fhir-samples/isip-relatedperson.json
docker compose exec app php artisan isip:export person    --output /var/www/storage/app/fhir-samples/isip-practitioner.json
docker compose exec app php artisan erezept:export        --output /var/www/storage/app/fhir-samples/erezept.json

# 3. Aus Container-Volume auf Host kopieren
docker compose cp app:/var/www/storage/app/fhir-samples/. storage/app/fhir-samples/
```

> Der Validator arbeitet **offline** — keine Testkarten, kein gematik-Testsystem, kein Konnektor erforderlich.

## Diagnose-Ergebnisse (Lauf 2026-06-07, Validator v2.16.2)

| Modul | Datei | valid/invalid | error | fatal | Bemerkung |
|-------|-------|---------------|-------|-------|-----------|
| isip1 | isip-patient.json | **valid** | 0 | 0 | ISiPPflegeempfaenger |
| isip1 | isip-encounter.json | **valid** | 0 | 0 | ISiPPflegeepisode |
| isip1 | isip-organization.json | **valid** | 0 | 0 | IsipOrganization |
| isip1 | isip-relatedperson.json | **valid** | 0 | 0 | ISiPAngehoeriger |
| isip1 | isip-practitioner.json | **valid** | 0 | 0 | ISiPPersonImGesundheitswesen |
| erp | erezept.json | **valid** | 0 | 0 | KBV_PR_ERP_Bundle 1.3 |

**Gesamt: 6/6 Ressourcen valide, 0 error, 0 fatal.**

## Bekannte Besonderheiten

### erp-Modul erwartet standardmäßig XML

Das `erp`-Modul ist für XML konfiguriert (KBV-Spezifikation sieht XML als Übertragungsformat vor).
Das Script übergibt `-ae json`, um JSON zu akzeptieren. Das ist für reine Konformitätsprüfung
der Datenstruktur korrekt — beim echten TI-Einsatz (Fachdienst-Übertragung) ist XML erforderlich.

**TODO Track C:** `ErezeptBundleMapper` um einen XML-Serializer ergänzen (HAPI FHIR oder ähnlich),
damit das ausgelieferte Dokument die KBV-Encoding-Anforderung ohne Override erfüllt.

### Placeholder-Daten in Demo-Exporten

Die Demo-Daten enthalten echte LANR/BSNR/KVNR-Testwerte aus dem Seeder:
- LANR: `838382202`, BSNR: `031234567`
- KVNR: `X110411319` (Muster-KVNR, nicht real)
- IK-Nummer AOK: `104212505`

Diese Werte sind für Validierungszwecke korrekt formatiert aber fiktiv.

### Nicht geprüfte Module

- `epa3-medication` — EPA 3.0 Medikationsdaten (optional)
- `vsdm2` — Versichertenstammdatenmanagement 2.0 (optional)

Diese können über den gleichen Workflow eingebunden werden, sobald die entsprechenden
Export-Commands implementiert sind.
