# OPCare — Open-Source-Pflegedokumentation für die stationäre Altenpflege

OPCare ist eine moderne Pflegedokumentations- und Pflegeplanungs-Software für die vollstationäre
Altenpflege, ausgerichtet an aktuellen deutschen Standards: **SIS®/Strukturmodell**, **indikatoren­basierte
Qualitätssicherung (QDVS/DAS-Pflege)** und **FHIR / ÜLB-MIO** (Pflegeüberleitung). Geistiger Nachfolger
des eingestellten Java-Projekts **[Offene-Pflege.de (OPDE)](#herkunft)** — dessen Domänenwissen dient als
Vorlage, der Code ist von Grund auf neu.

> **Status:** Funktionsfähig und aktiv in Entwicklung. **938 Tests grün**, CI durchgehend grün
> (Tests · Linter · Security-Audit · FHIR-Validierung). Open Source (AGPL-3.0), **kein Rechtsgate**,
> solange keine Echt-Patientendaten verarbeitet werden.
>
> 📖 Ausführliche Doku + Screenshots im **[Projekt-Wiki](https://github.com/Nileneb/opcare/wiki)**.

---

## Was OPCare kann

Norm-verankerte Module für den gesamten Heimbetrieb — von der Pflegedokumentation über Personal, Qualität und
Warenwirtschaft bis zu KI-Assistenz und TI-Anbindung. Nach Themen aufgeklappt:

<details open>
<summary><b>🩺 Pflege & Dokumentation</b></summary>

- **Stammdaten & Bewohnerverwaltung** — Gebäude/Etage/Station/Zimmer, Bewohner, Diagnosen (ICD-10-GM, ~16.000 Codes),
  Versicherungen, Betreuer:innen, Ärzt:innen, **Allergien**, **Medizinprodukte/Hilfsmittel**, **Angehörige**,
  **pflegerische Einschätzungen** (Bewusstsein/Kontinenz/Ernährung/Atmung).
- **SIS®-Pflegeplanung** — Strukturmodell: Informationssammlung → Maßnahmenplanung (Katalog ~230 Einträge) →
  Berichteblatt → Evaluation. Append-only/versioniert (manipulationssicher).
- **Assessments** — generische Instrument-Engine mit Scoring + Risiko-Bändern: **Braden** (Dekubitus),
  **Sturzrisiko**, **BESD** (Schmerz), **Barthel-Index** (Funktion/ADL, LOINC).
- **Medikation** — Verordnungen, Stellplan, Bestände, Gabe-Dokumentation, Vitalwerte.
- **Sprache → SIS®** — Audio/Transkription → LLM-Strukturierung in die SIS-Felder (Human-in-the-Loop).
</details>

<details>
<summary><b>👥 Personal, Dienstplan & Arbeitsschutz</b></summary>

- **Dienstplan & ArbZG-Compliance** — Wochenplan mit **Live-Prüfung (ArbZG)**: editierbares Regelwerk
  (§ 3/4/5/9–11/14, je mit Gesetzeslink) + dokumentierte **§ 14-Begründungen** für zwingende Abweichungen.
  Soll-Ist gegen das Vertrags-Pensum. **Wunschdienstplan** (Dienstwünsche im Raster eingeblendet).
- **Arbeitszeiterfassung (BAG/EuGH)** — Kommen/Gehen stempeln (oder manuell); Wochen-Ist gegen Dienstplan-Soll,
  Team-Sicht für die Leitung.
- **Personalakte** — Personalfragebogen (Person, Steuer/ELStAM, SV, Bank, Vertrag/Pensum, Masernschutz § 20 IfSG)
  1:1 am Benutzer, an die Rollenverwaltung gekoppelt; sensible Felder (Steuer-ID/SV-Nr/IBAN) At-Rest-verschlüsselt.
- **Gefährdungsbeurteilung** (§ 5/§ 6 ArbSchG) — GBU-Register je Arbeitsbereich mit den **6 Gefährdungsfaktoren**
  (inkl. psychischer Belastung), **Risiko-Matrix** (Wahrscheinlichkeit × Schwere), **TOP-Maßnahmen**,
  Fortschreibungs-Frist-Ampel + Wirksamkeitskontrolle (§ 3).
- **Belastungs-Live-Index** (§ 5 Abs. 3 Nr. 6 ArbSchG, live) — Belastung **je Wohnbereich** aus Pflegelast +
  Unterdeckung + Ergonomie; Überschreitung → Meldung an die Leitung + Ein-Klick-**Entlastungsmaßnahme** in der GBU.
  Bewusst **schicht-/bereichsbezogen, kein Personen-Scoring** (keine § 87-BetrVG-Leistungskontrolle).
- **Arbeitsschutz-Nachweise & Fortbildung** — Nachweise-mit-Frist + **Betriebsarzt/Sifa** (ASiG/DGUV V2),
  **Beauftragten-Register**, **Fortbildungsplan** (QPR QB6, Pflicht-Themen-Matrix).
- **Team-Energiebarometer** — freiwillig/anonym (k-Anonymität, § 26 BDSG/§ 87 BetrVG).
</details>

<details>
<summary><b>📋 Qualität, Recht & Beteiligung</b></summary>

- **Qualität & Controlling** — Vorkommnis-Erfassung (Sturz/Dekubitus/FEM strukturiert), QS-Indikatoren,
  KPI-Dashboard + **QM-Norm-Checkliste** (QPR QB1–6 + Hygiene/Datenschutz/Arbeitsschutz/LMIV/DIN 31051/Heimrecht)
  mit Erfüllungsgrad, Zuständigkeit und Gesetzeslink je Anforderung.
- **Beschwerde- & Gewaltschutz-Management** (§ 113 SGB XI / Landes-WTG / § 5 SGB XI) — Eingang erfassen und
  **anonym oder namentlich** an die betroffene Abteilung weiterleiten; Gewaltvorfälle bleiben bis zur Sofortmaßnahme rot.
- **Betreuung/Vertretung** (§§ 1814 ff. BGB) — rechtliche Vertretungen mit **Aufgabenkreisen** (§ 1815),
  **read-only Portal-Login**, **Pflicht-mit-Frist** (§ 1863 Jahresbericht), **Ereignis-Workflow** (§ 1821:
  MD-Begutachtung/Heilbehandlung/Krankenhaus/Heimvertrag/Posteingang → berechtigte Vertretungen benachrichtigt).
- **Gremien & Heimbeirat** (HeimmwV, § 10 WBVG, § 11 ASiG) + **Abstimmungen & Wahlen** — anonym (echte
  Anonymität: Stimme UUID-/timestamp-frei) oder namentlich; geheime Wahl erzwungen (§ 5 HeimmwV / § 11 MVG-EKD).
- **Datenschutz-Register** (Art. 30/28 DSGVO) — Verarbeitungstätigkeiten + Auftragsverarbeitungen mit
  Prüf-Frist-Ampel + Art-30-Export.
</details>

<details>
<summary><b>🏥 Hygiene, Gebäude & Verpflegung</b></summary>

- **Haustechnik & Instandhaltung** (DIN 31051) — Mängelmeldungen (offen → in Arbeit → erledigt) + **Wartungsplan**
  mit Prüffristen (überfällig rot; DGUV V3 / MPBetreibV / BetrSichV / TrinkwV).
- **Brandschutz-Organisation** (§ 10 ArbSchG / ASR A2.2/A2.3 / DIN 14096) — **Brandschutzordnung** (Teil A/B/C,
  Revisions-Ampel), **Begehungs-Eigenkontrolle** mit Mängel-Workflow (Schwere + Frist) und **Räumungs-/Evakuierungsübung**
  mit Frist-Ampel. (Anlagen-Prüfung im Wartungsplan, Brandschutzhelfer in den Arbeitsschutz-Nachweisen.)
- **Medizinprodukte** (MPBetreibV § 13/§ 14) — Bestandsverzeichnis + Medizinproduktebuch mit
  **STK/MTK-Prüffristen-Ampel**, **Einweisungen**, Vorkommnissen (BfArM-Meldung).
- **Trinkwasser/Legionellen** (TrinkwV 2023) — Großanlagen-Untersuchungspflicht: Probenahmestellen-Register,
  jährliche **Untersuchungs-Frist-Ampel**, technischer Maßnahmenwert **100 KbE/100 ml** + § 51-Workflow (Maßnahmen
  + Gesundheitsamt-Anzeige bei Überschreitung).
- **Hygiene** — Hygieneplan (Dokument-mit-Freigabe + Revisions-Ampel) + **MRE-/Infektions-Surveillance**
  je Bewohner mit Meldepflicht-Verfolgung (§ 23 / §§ 6/7 IfSG).
- **Küche & Verpflegung** (LMIV) — Lebensmittelallergien + Kostformen der Bewohner, **Speiseplan mit
  Allergenwarnung** (14 EU-Allergene), Bewohner-Warnung je Gericht, Essenswünsche + Menüwahl je Mahlzeit.
- **HACCP-Eigenkontrolle** (VO 852/2004 Art. 5, LMHV, DIN 10508) — CCP-**Temperaturüberwachung** der Küche
  (Kühlung ≤ 7 °C / TK ≤ −18 °C / Heißhaltung ≥ 65 °C): Messpunkte, tägliches Tagesblatt, Abweichungs- +
  Korrekturmaßnahmen-Workflow. **Reinigungs-/Desinfektionsplan** (VO 852/2004 Anhang II): Aufgaben mit Intervall + Fälligkeits-Ampel + Erledigungs-Nachweis.
- **Soziale Betreuung** (§ 43b SGB XI) — Angebote planen, Teilnahme + Betreuungs-Nachweis (Einheiten/Minuten) je Bewohner.
</details>

<details>
<summary><b>📦 Warenwirtschaft & Finanzen (HGB/PBV)</b></summary>

- **Doppelte Buchführung** (Soll/Haben, Saldo je Kontoart) + **freie Hauptbuchung** (GoB/PBV), verzahnt mit der
  **Lagerwirtschaft je Abteilung**: Wareneingang *Warenbestand an Verbindlichkeiten*, Verbrauch *Abteilungs-Aufwand
  an Warenbestand* — jeder Materialfluss schlägt automatisch in der Finanzbuchhaltung durch.
- **FIFO-Bewertung & Inventur** (§ 256, §§ 240/241 HGB/PBV) — Schichten-Ledger, Bestandswert, Zähldifferenz-Buchung.
- **Charge/MHD-Rückverfolgung & Lieferanten** (Art. 18 VO 178/2002: one-step-back, MHD-Monitor).
- **Pflegehilfsmittel-Verbrauch** (§ 40 SGB XI: bewohnerbezogen, 42-€-Referenz, nur ambulant Anspruch).
- **Gefahrstoffverzeichnis** (§ 6 GefStoffV: GHS/CLP, SDB) **+ druckbare Betriebsanweisung** (§ 14 GefStoffV, TRGS 555).
- **Beschaffung/Bestellwesen** (Wareneingang gegen Bestellung, Bedarfsvorschlag) + **generische Budgets**
  (Konto-/Treuhand-Budget mit Warn-/Sperr-Ampel). **Taschengeldkasse** (Treuhand, § 27b SGB XII).
</details>

<details>
<summary><b>🤖 KI-Module (lokal, DSGVO-konform)</b></summary>

- **Beleg-Capture** — Belegfoto → Ollama-VLM-Extraktion → Vorschlag → berechtigte Bestätigung bucht (HITL).
- **Lieferschein → Wareneingang** — Foto → VLM-Positionen → lokales **Embedding-Artikel-Matching**
  (Match-Gedächtnis + Ollama-Cosine, DSGVO-lokal) → bestätigter FIFO-Wareneingang (standalone/gegen Bestellung).
- **Stammdaten-Datenimport** — CSV → editierbares Spalten-Mapping → Artikel-Matching (anlegen/mergen, Dedup) →
  bewerteter Anfangsbestand (EBK/Verbindlichkeit).
- **Vision-Regalzählung** — Regalfoto → externes [Vision-MCP](https://github.com/Nileneb/vision-mcp) (YOLO,
  whisperX-Muster) → `ProductLabel`-Mapping → bestätigter `Inventurposition.ist_menge`. Training hinter Schalter.
</details>

<details>
<summary><b>🔌 Interoperabilität & Telematik (TI)</b></summary>

- **QDVS / DAS-Pflege** — datengetriebene **Plausibilitäts-Regel-Engine** (440 DAS-Regeln, Pattern-Matcher;
  ehrlicher Coverage-Report).
- **FHIR-Export** — FHIR-R4-Pflegeüberleitungs-**Document-Bundle**, im CI gegen den **amtlichen HL7-FHIR-Validator**
  (0 errors) Richtung **ÜLB-MIO**. Lokal mit dem gematik [`app-referencevalidator`](docs/gematik-validierung.md)
  geprüft: ISiP + E-Rezept **6/6 konform**.
- **TI 2.0 / ZETA** (Track C) — Service-Discovery-Seam (`HttpZetaClient`); lokaler gematik
  **`zeta-testfachdienst`** angebunden (operativ). Echter RU-Auth vorbereitet (Test-SMC-B + Member-ID-Runbook).
- **KIM** — Anbindung für sichere Kommunikation (Vorbereitung).
</details>

<details>
<summary><b>🔐 Sicherheit & Plattform</b></summary>

- **MFA (TOTP, Pflicht für alle)**, Row-Level-Mandantentrennung (`tenant_id` + Global Scope), **RBAC** (Rollen je
  Mandant), Audit-Log, **IDOR-Härtung**, **At-Rest-Verschlüsselung** sensibler Gesundheits-Freitextdaten,
  **Security-Header** (CSP/HSTS/…), DSGVO-Guards auf Export-Routen.
- **CI-Gates**: Dependency-CVE-Audit + **SAST (Semgrep)** + FHIR-Validierung. Konzept:
  [`docs/security/sicherheitskonzept.md`](docs/security/sicherheitskonzept.md).
- **Föderales Heimrecht** — Bundesland automatisch aus der Adresse → Landesheimgesetz + Personalbemessungs-Defaults
  (Bund → Land → Träger).
</details>

## Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | **Laravel 13**, **PHP 8.3+** |
| Frontend | Blade + **Livewire 4** + Alpine.js |
| Datenbank | **SQLite** (Dev/CI) · **PostgreSQL** (Prod) |
| Tests | **Pest 4** (938 Tests) |
| Lint/Style | **Laravel Pint** · **Larastan/PHPStan L5** |
| DTOs / RBAC / Audit | `spatie/laravel-data` · `spatie/laravel-permission` · `spatie/laravel-activitylog` · `spatie/laravel-medialibrary` |
| KI (lokal) | **Ollama** (VLM/Embeddings) · externe **MCP-Tools** (Vision-MCP, whisperX-mcp) |
| Deployment | **Docker Compose** (self-contained: eine `.env`, `docker compose up --build`) |
| FHIR-Validierung (CI) | amtlicher **HL7 FHIR Validator** + gematik **app-referencevalidator** (offline) |

## Architektur — Bounded Contexts

Domänen-orientierte Struktur unter `app/Domains/`. Layering als Einbahnstraße:
**Livewire/Controller → Action → Model/Service**, Daten zwischen Schichten als DTOs.

<details open>
<summary><b>🩺 Pflege & Dokumentation</b></summary>

| Domäne | Inhalt |
|---|---|
| **Masterdata** | Bewohner, Diagnosen/ICD, Versicherungen, **rechtliche Vertretung mit Aufgabenkreisen (§§ 1814 ff. BGB) + Vertreter-Portal + Bewohner-Ereignisse**, Ärzte, Gebäude/Zimmer, Allergien, Medizinprodukte, Kontakte, Status-Observationen |
| **CarePlanning** | SIS®-Strukturmodell: Informationssammlung → Maßnahmenplan → Bericht → Evaluation |
| **Assessment** | Instrument-Engine (Braden/Sturz/BESD/Barthel), Scoring, Risiko-Bänder, Eskalation |
| **Medication** | Verordnungen, Stellplan, Bestände, Gaben, Vitalwerte |
| **Speech** | Audio-Handling, Transkription, LLM→SIS®-Strukturierung (Human-in-the-Loop) |
</details>

<details>
<summary><b>📋 Qualität, Compliance & Beteiligung</b></summary>

| Domäne | Inhalt |
|---|---|
| **Quality** | Vorkommnisse/CareEvents, QS-Indikatoren, KPIs, **QM-Norm-Checkliste**, **Beschwerde-/Gewaltschutz-Management** (Weiterleitung anonym/namentlich), **Gremien/Heimbeirat** (HeimmwV/§ 11 ASiG) |
| **Compliance** | **Datenschutz-Register**: Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) mit Prüf-Frist-Ampel + Auftragsverarbeitungen (Art. 28) + Art-30-Export |
| **Voting** | **Abstimmungen & Wahlen** (anonym + namentlich): drei entkoppelte Modelle (`Stimme` UUID-PK + timestamp-frei = echte Anonymität ErwG 26; `Wahlteilnahme` nur Boolean), Beleg-Token, geheime Wahl erzwungen (§ 5 HeimmwV / § 11 MVG-EKD), bindende Online-Wahl hinter Inbetriebnahme-Schalter |
</details>

<details>
<summary><b>👥 Personal & Dienstplan</b></summary>

| Domäne | Inhalt |
|---|---|
| **Scheduling** | Dienstplan, Schichten, Kalender, **ArbZG-Compliance-Engine** (Regelwerk + § 14) + **Arbeitszeiterfassung** (BAG/EuGH) |
| **Personnel** | Personalakte (verschlüsselt) 1:1 am Benutzer, rollen-gekoppelt; Arbeitsschutz-Nachweise + **Betriebsarzt/Sifa** (ASiG/DGUV V2), Beauftragten-Register, **Fortbildungsplan** (QPR QB6), **Team-Energiebarometer** (freiwillig/anonym) |
| **Arbeitsschutz** | **Gefährdungsbeurteilung** (§ 5/§ 6 ArbSchG): GBU je Arbeitsbereich, 6 Gefährdungsfaktoren (inkl. psychischer Belastung), Nohl-Risiko-Matrix, TOP-Maßnahmen, Fortschreibungs-Frist-Ampel + Wirksamkeitskontrolle (§ 3); **Belastungs-Live-Index** je Wohnbereich (§ 5 Abs. 3 Nr. 6, kein Personen-Scoring) → Meldung an Leitung + Entlastungsmaßnahme |
</details>

<details>
<summary><b>🏥 Hygiene, Gebäude & Verpflegung</b></summary>

| Domäne | Inhalt |
|---|---|
| **Hygiene** | **Hygieneplan** (Dokument-mit-Freigabe + Revisions-Ampel) + **MRE-/Infektions-Surveillance** je Bewohner mit Meldepflicht-Verfolgung (§ 23 / §§ 6/7 IfSG) |
| **Facility** | Haustechnik/Instandhaltung (DIN 31051): Mängelmeldungen + Wartungsplan mit Prüffristen; **Medizinprodukte**-Bestandsverzeichnis + Medizinproduktebuch (MPBetreibV); **Trinkwasser/Legionellen-Überwachung** (TrinkwV 2023: Frist-Ampel, Maßnahmenwert 100 KbE/100 ml, § 51-Workflow) |
| **Catering** | Küche/Verpflegung (LMIV): Diät-/Allergen-Sicht der Bewohner + Speiseplan mit Allergenwarnung; **HACCP-Eigenkontrolle** (VO 852/2004 Art. 5: CCP-Temperaturüberwachung + Abweichungs-/Korrektur-Workflow) + **Reinigungs-/Desinfektionsplan** (Anhang II: Frist-Ampel + Erledigungs-Nachweis) |
| **Brandschutz** | **Brandschutz-Organisation** (§ 10 ArbSchG / ASR A2.2/A2.3 / DIN 14096): Brandschutzordnung (Teil A/B/C, Revisions-Ampel), Begehungs-Eigenkontrolle mit Mängel-Workflow (Schwere/Frist) + Räumungs-/Evakuierungsübung mit Frist-Ampel |
| **SocialCare** | Soziale Betreuung (§ 43b SGB XI): Angebote + Teilnahme-Nachweis je Bewohner |
</details>

<details>
<summary><b>📦 Warenwirtschaft, Finanzen & KI-Capture</b></summary>

| Domäne | Inhalt |
|---|---|
| **Accounting** | Doppelte Buchführung + **freie Hauptbuchung** (GoB/PBV) + Warenwirtschaft je Abteilung + **FIFO-Bewertung & Inventur** (§ 256/§§ 240/241 HGB) + **Charge/MHD-Rückverfolgung & Lieferanten** (Art. 18 VO 178/2002) + **Pflegehilfsmittel** (§ 40 SGB XI) + **Gefahrstoffverzeichnis + Betriebsanweisung** (§ 6/§ 14 GefStoffV) + **Beschaffung** + generische **Budgets** + Taschengeldkasse (§ 27b SGB XII) |
| **Capture** | **VLM-Beleg-Capture** (Belegfoto → Vorschlag → bestätigte Buchung) + **Lieferschein→Wareneingang** (Foto → VLM-Positionen → Embedding-Artikel-Matching → bestätigter FIFO-Wareneingang) |
| **Import** | **WaWi-Stammdaten-Datenimport** (Onboarding): CSV → editierbares Spalten-Mapping → Artikel-Matching → bewerteter Anfangsbestand (EBK/Verbindlichkeit) |
| **Vision** | **Regalzählung** über das externe [Vision-MCP](https://github.com/Nileneb/vision-mcp) (YOLO): Regalfoto → `detect` → `ProductLabel`-Mapping → `Inventurposition.ist_menge`; Training hinter Inbetriebnahme-Schalter |
</details>

<details>
<summary><b>🔌 Interoperabilität & TI</b></summary>

| Domäne | Inhalt |
|---|---|
| **Fhir** | FHIR-R4-Mapper + Document-Bundle-Export (ÜLB-MIO) + ISiP/E-Rezept (gegen gematik-Validator 6/6 konform) |
| **Qdvs** | DAS-Plausibilitäts-Regel-Engine + QDVS-Export |
| **Ti20** | **TI 2.0 / ZETA** — Service-Discovery-Seam (`HttpZetaClient`), lokaler gematik `zeta-testfachdienst` angebunden; RU-Auth vorbereitet (Inbetriebnahme-Schalter) |
| **Kim** | KIM-Anbindung (sichere Kommunikation) — Vorbereitung |
</details>

<details>
<summary><b>🔐 Plattform & Identität</b></summary>

| Domäne | Inhalt |
|---|---|
| **Identity** | Auth, Benutzer, Rollen/Rechte, Mandanten, Tenant-Scoping, **föderales Heimrecht** (Bundesland automatisch aus der Adresse → Landesheimgesetz + Personalbemessungs-Defaults Bund→Land→Träger) |
</details>

## FHIR / ÜLB-MIO-Konformität

Der FHIR-Export erzeugt ein **vollständig ÜLB-MIO-konformes Document-Bundle** (`KBV_PR_MIO_ULB_Bundle`,
PIO Überleitungsbogen `kbv.mio.ueberleitungsbogen` 1.0.0). Im CI **blockierend** gegen FHIR R4 +
`de.basisprofil.r4` + das ÜLB-Paket validiert (**0 errors**).

<details>
<summary><b>Composition mit bis zu 12 slice-konformen Sektionen</b></summary>

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
| `harn-/stuhlkontinenz…` / `ernaehrung` | `Continence_Differentiated_Assessment` / `Presence_Information_Nutrition` |
| `medizinprodukte` | `Relevant_Information_Medical_Devices` → `DeviceUseStatement` → `Device` |
| `patientenAdressbuch` | `RelatedPerson_Contact_Person` |

Dazu die dokumentierende Einheit (Organization/Practitioner/PractitionerRole) und der ÜLB-Patient. Genuin offener,
optionaler Rest: [Wiki → Track A](https://github.com/Nileneb/opcare/wiki).
</details>

## Schnellstart (Docker)

```bash
git clone https://github.com/Nileneb/opcare.git && cd opcare
cp .env.example .env
docker compose up --build
# danach: Migrationen + Demo-Daten werden geseedet; App unter http://localhost:8099
# Demo-Login: admin@opcare.local / password
```

<details>
<summary>Lokal ohne Docker</summary>

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
```
</details>

## Entwicklung

```bash
php artisan test                 # bzw. vendor/bin/pest   (938 Tests)
vendor/bin/pint                  # Code-Style
php -d memory_limit=1G vendor/bin/phpstan analyse   # Larastan L5
php artisan fhir:export --output=bundle.json        # FHIR-Document-Bundle
scripts/fhir/validate.sh isip1 <datei.json>         # gematik-Referenzvalidator (offline)
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
</content>
