# OPCare — Open-Source-Pflegedokumentation für die stationäre Altenpflege

OPCare ist eine moderne Pflegedokumentations- und Pflegeplanungs-Software für die vollstationäre
Altenpflege, ausgerichtet an aktuellen deutschen Standards: **SIS®/Strukturmodell**, **indikatoren­basierte
Qualitätssicherung (QDVS/DAS-Pflege)** und **FHIR / ÜLB-MIO** (Pflegeüberleitung). Geistiger Nachfolger
des eingestellten Java-Projekts **[Offene-Pflege.de (OPDE)](#herkunft)** — dessen Domänenwissen dient als
Vorlage, der Code ist von Grund auf neu.

> **Status:** Funktionsfähig und aktiv in Entwicklung. **322 Tests grün**, CI durchgehend grün
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
- **Dienstplan & Arbeitszeit-Compliance** — Wochen-Dienstplan mit arbeitsrechtlicher **Live-Prüfung
  (ArbZG)**: editierbares, einrichtungseigenes Regelwerk (§ 3/4/5/9–11/14, je mit Link zum amtlichen
  Gesetzestext) + dokumentierte **§ 14-Begründungen** für zwingende Abweichungen (z. B. ausbleibende
  Nachfolgekraft). Soll-Ist-Stunden gegen das Vertrags-Pensum.
- **Mitarbeiterverwaltung** — vollständige **Personalakte** (Personalfragebogen: Person, Steuer/ELStAM,
  Sozialversicherung, Bank, Vertrag/Pensum, Pflege-Compliance inkl. Masernschutz § 20 IfSG) 1:1 am
  App-Benutzer, **an die Rollenverwaltung gekoppelt**; sensible Felder (Steuer-ID/SV-Nr/IBAN)
  At-Rest-verschlüsselt.
- **Qualität & Controlling** — Vorkommnis-Erfassung (Sturz strukturiert mit Folgen, Dekubitus mit Stadium,
  FEM …), QS-Indikatoren, KPI-Dashboard sowie eine **QM-Norm-Checkliste**: norm-verankerte Anforderungen
  (QPR-Qualitätsbereiche QB1–6 + Hygiene/IfSG, Datenschutz/DSGVO, Arbeitsschutz, Hauswirtschaft/LMIV,
  Haustechnik/DIN 31051, Heimrecht) mit Erfüllungsgrad, Zuständigkeit und Gesetzeslink je Anforderung.
- **Haustechnik & Instandhaltung (DIN 31051)** — jede:r Mitarbeitende **meldet Mängel**; die Haustechnik
  arbeitet die Queue ab (offen → in Arbeit → erledigt) und führt den **Wartungsplan** mit Prüffristen
  (überfällige Prüfungen rot; DGUV V3 / MPBetreibV / BetrSichV / TrinkwV).
- **Küche & Verpflegung (LMIV)** — die Küche sieht die **Lebensmittelallergien + Kostformen** der Bewohner
  (aus den vorhandenen Pflegedaten) und pflegt den **Speiseplan mit Allergenkennzeichnung** (14 EU-Allergene);
  je Gericht werden **betroffene Bewohner gewarnt**.
- **QDVS / DAS-Pflege** — datengetriebene **Plausibilitäts-Regel-Engine** (440 DAS-Regeln, Pattern-Matcher
  statt Voll-XPath; ehrlicher Coverage-Report; aktuell 57 Regeln scharf).
- **FHIR-Export** — FHIR-R4-Pflegeüberleitungs-**Document-Bundle**, validiert im CI mit dem **amtlichen
  HL7-FHIR-Validator** (0 errors) — Richtung **ÜLB-MIO** (`kbv.mio.ueberleitungsbogen`). Siehe
  [FHIR-Konformität](#fhir--ülb-mio-konformität).
- **Sicherheit** — **MFA (TOTP, Pflicht für alle)**, Row-Level-Mandantentrennung (`tenant_id` + Global
  Scope), RBAC (Rollen je Mandant), Audit-Log, IDOR-Härtung, **At-Rest-Verschlüsselung** sensibler
  Gesundheits-Freitextdaten, **Security-Header** (CSP/HSTS/…), DSGVO-Guards auf Export-Routen. CI-Gates:
  Dependency-CVE-Audit + **SAST (Semgrep)**. Konzept: [`docs/security/sicherheitskonzept.md`](docs/security/sicherheitskonzept.md).

## Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | **Laravel 13**, **PHP 8.3+** |
| Frontend | Blade + **Livewire 4** + Alpine.js |
| Datenbank | **SQLite** (Dev/CI) · **PostgreSQL** (Prod) |
| Tests | **Pest 4** (322 Tests) |
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
| **Quality** | Vorkommnisse/CareEvents, QS-Indikatoren, KPIs, **QM-Norm-Checkliste** (datengetrieben, Erfüllungsgrad) |
| **Qdvs** | DAS-Plausibilitäts-Regel-Engine + QDVS-Export |
| **Fhir** | FHIR-R4-Mapper + Document-Bundle-Export (ÜLB-MIO-Richtung) |
| **Scheduling** | Dienstplan, Schichten, Kalender, **ArbZG-Compliance-Engine** (editierbares Regelwerk + § 14-Begründungen) |
| **Personnel** | Personalakte (Personalfragebogen, verschlüsselt) 1:1 am Benutzer, gekoppelt an die Rollenverwaltung |
| **Facility** | Haustechnik/Instandhaltung (DIN 31051): Mängelmeldungen + Wartungsplan mit Prüffristen |
| **Catering** | Küche/Verpflegung (LMIV): Diät-/Allergen-Sicht der Bewohner + Speiseplan mit Allergenwarnung |
| **Speech** | Audio-Handling, Transkription, LLM→SIS®-Strukturierung (Human-in-the-Loop) |

## FHIR / ÜLB-MIO-Konformität

Der FHIR-Export erzeugt ein **vollständig ÜLB-MIO-konformes Document-Bundle** (`KBV_PR_MIO_ULB_Bundle`,
PIO Überleitungsbogen `kbv.mio.ueberleitungsbogen` 1.0.0 — der veröffentlichte FHIR-MIO der
Pflegeüberleitung). Im CI **blockierend** gegen FHIR R4 + `de.basisprofil.r4` + das ÜLB-Paket validiert,
zusätzlich explizit gegen das ÜLB-Bundle-Profil (**0 errors**).

**Konforme Composition mit bis zu 12 slice-konformen Sektionen** (`meta.profile` durchgängig, closed slicing):

| ÜLB-Sektion | FHIR-Ressource |
|---|---|
| `pflegegrad` (Pflicht) | Observation `Care_Level` (Beantragungsstatus, OPS-Pflegegrad) |
| `vitalparameter` | `DiagnosticReport` über die konformen Vital-Observations (7 Arten) |
| `probleme` (Diagnosen) | Presence-Observation → `Condition` (ICD-10-GM) |
| `allergienUndUnvertraeglichkeiten` | Presence-Observation → `AllergyIntolerance` |
| `medikationsplan` | Information-Observation → `MedicationStatement` + `Medication` |
| `funktionsbeurteilungen` | Presence-Observation → `Assessment_Free` (Barthel) |
| `pflegerischeMassnahme` | `Procedure` (je Maßnahme) |
| `orientierungPsyche` / `qualitativeBeschreibungAtmung` | Status-Observations `Cognitive_Awareness` / `Qualitative_Description_Breathing` |
| `harn-/stuhlkontinenzDifferenzierteEinschaetzung` / `ernaehrung` | `Continence_Differentiated_Assessment` / `Presence_Information_Nutrition` |
| `medizinprodukte` | `Relevant_Information_Medical_Devices` → `DeviceUseStatement` → `Device` |
| `patientenAdressbuch` | `RelatedPerson_Contact_Person` (An-/Zugehörige) |

Dazu die dokumentierende Einheit (Organization/Practitioner/PractitionerRole) und der ÜLB-Patient. Die
Status-/Medizinprodukte-/Angehörige-Sektionen erscheinen, sobald die Daten erfasst sind. Genuin offener,
optionaler Rest (Drainage, gradDerBehinderung, Patientenwunsch …): [Wiki → Track A](https://github.com/Nileneb/opcare/wiki).

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
php artisan test                 # bzw. vendor/bin/pest   (322 Tests)
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
