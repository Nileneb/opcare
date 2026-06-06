# OPCare — Open-Source-Pflegedokumentation für die stationäre Altenpflege

OPCare ist eine moderne Pflegedokumentations- und Pflegeplanungs-Software für die vollstationäre
Altenpflege, ausgerichtet an aktuellen deutschen Standards: **SIS®/Strukturmodell**, **indikatoren­basierte
Qualitätssicherung (QDVS/DAS-Pflege)** und **FHIR / ÜLB-MIO** (Pflegeüberleitung). Geistiger Nachfolger
des eingestellten Java-Projekts **[Offene-Pflege.de (OPDE)](#herkunft)** — dessen Domänenwissen dient als
Vorlage, der Code ist von Grund auf neu.

> **Status:** Funktionsfähig und aktiv in Entwicklung. **239 Tests grün**, CI durchgehend grün
> (Tests · Linter · Security-Audit · FHIR-Validierung). Open Source (AGPL-3.0), **kein Rechtsgate**,
> solange keine Echt-Patientendaten verarbeitet werden.
>
> 📖 Ausführliche Doku + Screenshots im **[Projekt-Wiki](https://github.com/Nileneb/opcare/wiki)**.

---

## Was OPCare kann

- **Stammdaten & Bewohnerverwaltung** — Gebäude/Etage/Station/Zimmer, Bewohner, Diagnosen (ICD-10-GM-Katalog,
  ~16.000 Codes), Versicherungen, Betreuer:innen, Ärzt:innen, **Allergien**, **Medizinprodukte/Hilfsmittel**,
  **Angehörige/Kontaktpersonen**, **pflegerische Einschätzungen** (Bewusstsein/Kontinenz/Ernährung/Atmung).
- **SIS®-Pflegeplanung** — Strukturmodell: Informationssammlung → Maßnahmenplanung (Maßnahmen-Katalog,
  ~230 Einträge) → Berichteblatt → Evaluation. Append-only/versioniert (manipulationssicher).
- **Assessments** — generische Instrument-Engine mit Scoring + Risiko-Bändern: **Braden** (Dekubitus),
  **Sturzrisiko**, **BESD** (Schmerz), **Barthel-Index** (Funktion/ADL, mit LOINC-Codes).
- **Medikation** — Verordnungen, Stellplan, Bestände, Gabe-Dokumentation.
- **Qualität & Controlling** — Vorkommnis-Erfassung (Sturz strukturiert mit Folgen, Dekubitus mit Stadium,
  FEM …), QS-Indikatoren, KPI-Dashboard.
- **QDVS / DAS-Pflege** — datengetriebene **Plausibilitäts-Regel-Engine** (440 DAS-Regeln, Pattern-Matcher
  statt Voll-XPath; ehrlicher Coverage-Report; aktuell 57 Regeln scharf).
- **FHIR-Export** — FHIR-R4-Pflegeüberleitungs-**Document-Bundle**, validiert im CI mit dem **amtlichen
  HL7-FHIR-Validator** (0 errors) — Richtung **ÜLB-MIO** (`kbv.mio.ueberleitungsbogen`). Siehe
  [FHIR-Konformität](#fhir--ülb-mio-konformität).
- **Sicherheit** — Row-Level-Mandantentrennung (`tenant_id` + Global Scope), RBAC (Rollen je Mandant),
  Audit-Log, IDOR-Härtung, DSGVO-Guards auf Export-Routen.

## Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | **Laravel 13**, **PHP 8.3+** |
| Frontend | Blade + **Livewire 4** + Alpine.js |
| Datenbank | **SQLite** (Dev/CI) · **PostgreSQL** (Prod) |
| Tests | **Pest 4** (239 Tests) |
| Lint/Style | **Laravel Pint** |
| DTOs / RBAC / Audit | `spatie/laravel-data` · `spatie/laravel-permission` · `spatie/laravel-activitylog` |
| Deployment | **Docker Compose** (self-contained: eine `.env`, `docker compose up --build`) |
| FHIR-Validierung (CI) | amtlicher **HL7 FHIR Validator** (`validator_cli.jar`) gegen R4 + `de.basisprofil.r4` + ÜLB |

## Architektur — Bounded Contexts

Domänen-orientierte Struktur unter `app/Domains/`. Layering als Einbahnstraße:
**Livewire/Controller → Action → Model/Service**, Daten zwischen Schichten als DTOs.

| Domäne | Inhalt |
|---|---|
| **Identity** | Auth, Benutzer, Rollen/Rechte, Mandanten, Tenant-Scoping |
| **Masterdata** | Bewohner, Diagnosen/ICD, Versicherungen, Betreuer, Ärzte, Gebäude/Zimmer, Allergien, Medizinprodukte, Kontakte, Status-Observationen |
| **CarePlanning** | SIS®-Strukturmodell: Informationssammlung → Maßnahmenplan → Bericht → Evaluation |
| **Assessment** | Instrument-Engine (Braden/Sturz/BESD/Barthel), Scoring, Risiko-Bänder, Eskalation |
| **Medication** | Verordnungen, Stellplan, Bestände, Gaben, Vitalwerte |
| **Quality** | Vorkommnisse/CareEvents, QS-Indikatoren, KPIs |
| **Qdvs** | DAS-Plausibilitäts-Regel-Engine + QDVS-Export |
| **Fhir** | FHIR-R4-Mapper + Document-Bundle-Export (ÜLB-MIO-Richtung) |
| **Scheduling** | Dienstplan, Schichten, Kalender |
| **Speech** | Audio-Handling, Transkription, LLM→SIS®-Strukturierung (Human-in-the-Loop) |

## FHIR / ÜLB-MIO-Konformität

Der FHIR-Export erzeugt ein **vollständig ÜLB-MIO-konformes Document-Bundle** (`KBV_PR_MIO_ULB_Bundle`,
PIO Überleitungsbogen `kbv.mio.ueberleitungsbogen` 1.0.0 — der veröffentlichte FHIR-MIO der
Pflegeüberleitung). Im CI **blockierend** gegen FHIR R4 + `de.basisprofil.r4` + das ÜLB-Paket validiert,
zusätzlich explizit gegen das ÜLB-Bundle-Profil (**0 errors**).

**Konforme Composition mit 7 slice-konformen Sektionen** (`meta.profile` durchgängig, closed slicing):

| ÜLB-Sektion | FHIR-Ressource |
|---|---|
| `pflegegrad` (Pflicht) | Observation `Care_Level` (Beantragungsstatus, OPS-Pflegegrad) |
| `vitalparameter` | `DiagnosticReport` über die konformen Vital-Observations (7 Arten) |
| `probleme` (Diagnosen) | Presence-Observation → `Condition` (ICD-10-GM) |
| `allergienUndUnvertraeglichkeiten` | Presence-Observation → `AllergyIntolerance` |
| `medikationsplan` | Information-Observation → `MedicationStatement` + `Medication` |
| `funktionsbeurteilungen` | Presence-Observation → `Assessment_Free` (Barthel) |
| `pflegerischeMassnahme` | `Procedure` (je Maßnahme) |

Dazu die dokumentierende Einheit (Organization/Practitioner/PractitionerRole) und der ÜLB-Patient.

**Optionaler Backlog** (dokumentiert, nicht konformitätskritisch — alle Sektionen sind optional):
Status-Beobachtungen (Orientierung/Ernährung/Atmung/Kontinenz, je eigenes Profil), Medizinprodukte
(Basis-`Device`-Variante), Angehörige. Details: [Wiki → Track A](https://github.com/Nileneb/opcare/wiki).

## Schnellstart (Docker)

```bash
git clone https://github.com/Nileneb/opcare.git && cd opcare
cp .env.example .env
docker compose up --build
# danach: Migrationen + Demo-Daten werden geseedet; App unter http://localhost:8099
# Demo-Login: admin@opcare.local / password
```

Lokal ohne Docker:

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Entwicklung

```bash
php artisan test                 # bzw. vendor/bin/pest   (239 Tests)
vendor/bin/pint                  # Code-Style
php artisan fhir:export --output=bundle.json   # FHIR-Document-Bundle erzeugen
```

**CI-Gates** (GitHub Actions): `tests` · `lint` · `security` (composer audit) ·
`fhir-validate` (amtlicher HL7-Validator). Direkter Push auf `master` ist im Projekt autorisiert;
jede Änderung läuft über die Gates.

## Herkunft

Basiert konzeptionell auf **Offene-Pflege.de (OPDE)**, einem freien Java-Swing-Pflegedokumentationssystem,
das 2025 wegen regulatorischer Anforderungen (EU Cyber Resilience Act, EU-Produkthaftungsrichtlinie)
eingestellt wurde. OPCare übernimmt dessen Domänenwissen (Stammdaten, QDVS-Mapping als Referenz), **nicht**
dessen Code.

## Lizenz

**AGPL-3.0** — Copyleft erstreckt sich auch auf über das Netzwerk bereitgestellte Dienste (SaaS).
