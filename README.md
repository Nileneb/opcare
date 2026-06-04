# OPCare — SIS®-basierte Pflegeplanung (Arbeitstitel)

Pflegedokumentations- und Pflegeplanungssystem für die stationäre Altenpflege, neu aufgebaut als
moderner Laravel-Stack. Geistiger Nachfolger des inaktiven Java-Projekts
**[Offene-Pflege.de (OPDE)](#herkunft)** — dessen erprobtes Stammdaten-Domänenmodell dient als Vorlage,
die Pflegeplanung wird jedoch von Grund auf nach dem **Strukturmodell / SIS®** neu modelliert.

> **Status:** In Entwurf/Design-Phase. Noch kein Produktivcode. Open Source.

---

## Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | **Laravel 12**, **PHP 8.5** |
| Datenbank | **PostgreSQL** |
| Frontend/PWA | Blade + **Livewire 3** + Alpine.js, Service Worker (installierbar), **Node (aktuelle LTS)** + Vite |
| Realtime | **Laravel Reverb** (WebSockets) |
| Queue/Monitoring | Redis + **Laravel Horizon** |
| Spracherfassung | lokaler **Whisper**-Dienst (ASR) |
| KI-Strukturierung | **Ollama**-LLM (on-prem via `three.linn.games`), Human-in-the-Loop |
| Sicherheit | RBAC (`spatie/laravel-permission`), Audit (`spatie/laravel-activitylog`), Verschlüsselung at-rest |

## Architektur — Bounded Contexts

Domänen-orientierte Struktur unter `app/Domains/` (PSR-4-Ordnerkonvention; `nwidart/laravel-modules`
bei wachsender Komplexität nachrüstbar). Pro Domäne: `Models/ Actions/ Data/ Policies/ Events/ Jobs/
Database/ Tests/`. Layering als Einbahnstraße: **Livewire/Controller → Action → Model/Service**, Daten
zwischen Schichten als DTOs (`spatie/laravel-data`).

- **Identity** — Auth, Benutzer, Rollen/Rechte, Mandanten-Scoping (`tenant_id` überall)
- **Masterdata** — Bewohner, Diagnosen/ICD, Krankenkassen, Betreuer, Ärzte, Gebäude/Zimmer
- **CarePlanning** — SIS®-Strukturmodell: Informationssammlung → Maßnahmenplanung → Berichteblatt → Evaluation
- **Speech** — Audio-Handling, Transkription, LLM→SIS®-Strukturierung

## Datenmodell

Konventionen: alle Tabellen `bigint id PK` + `tenant_id FK` (globaler Eloquent-Scope) +
`created_by`/`updated_by` + `timestamps`. Rechtlich relevante Einträge (SIS, Berichte, Evaluationen)
sind **append-only / versioniert** (manipulationssicher, MDK-konform): Korrekturen erzeugen eine neue
Version, die alte wird via `superseded_by` verkettet und bleibt erhalten. Audio wird nach erfolgreicher
Transkription gelöscht (Datensparsamkeit, Art. 5 DSGVO).

### Identity & Masterdata

```mermaid
erDiagram
    TENANTS   ||--o{ USERS              : "hat"
    TENANTS   ||--o{ RESIDENTS          : "hat"

    BUILDINGS ||--o{ FLOORS             : "gliedert"
    FLOORS    ||--o{ STATIONS           : "gliedert"
    STATIONS  ||--o{ ROOMS              : "gliedert"
    ROOMS     ||--o{ RESIDENTS          : "beherbergt"

    RESIDENTS ||--o{ RESIDENT_DIAGNOSES : "hat"
    ICD_CODES ||--o{ RESIDENT_DIAGNOSES : "klassifiziert"
    RESIDENTS ||--o{ RESIDENT_INSURANCE : "versichert über"
    HEALTH_INSURANCES ||--o{ RESIDENT_INSURANCE : "deckt"
    RESIDENTS ||--o{ CUSTODIANS         : "wird betreut von"
    RESIDENTS }o--o{ PHYSICIANS         : "behandelt durch"
    RESIDENTS ||--o{ RESIDENT_FILES     : "Anhänge"

    TENANTS {
        bigint id PK
        string name "NOT NULL"
        string slug UK "NOT NULL"
        timestamp created_at
    }
    USERS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        string name "NOT NULL"
        string email UK "NOT NULL"
        string password "NOT NULL, hashed"
        timestamp created_at
    }
    BUILDINGS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        string name "NOT NULL"
    }
    FLOORS {
        bigint id PK
        bigint building_id FK "NOT NULL"
        string name "NOT NULL"
    }
    STATIONS {
        bigint id PK
        bigint floor_id FK "NOT NULL"
        string name "NOT NULL"
    }
    ROOMS {
        bigint id PK
        bigint station_id FK "NOT NULL"
        string nummer "NOT NULL"
        smallint betten "default 1"
    }
    RESIDENTS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        bigint room_id FK "nullable"
        string name "NOT NULL"
        date geburtsdatum "NOT NULL"
        string geschlecht "enum m/w/d"
        smallint pflegegrad "1-5, nullable"
        date aufnahme_am "NOT NULL"
        date entlassung_am "nullable"
        string status "enum aktiv/abwesend/entlassen"
        timestamp created_at
    }
    ICD_CODES {
        bigint id PK
        string code UK "NOT NULL, ICD-10"
        string bezeichnung "NOT NULL"
    }
    RESIDENT_DIAGNOSES {
        bigint id PK
        bigint resident_id FK "NOT NULL"
        bigint icd_code_id FK "NOT NULL"
        string art "enum primär/sekundär"
        date diagnostiziert_am "nullable"
    }
    HEALTH_INSURANCES {
        bigint id PK
        string name "NOT NULL"
        string ik_nummer UK "nullable"
    }
    RESIDENT_INSURANCE {
        bigint id PK
        bigint resident_id FK "NOT NULL"
        bigint health_insurance_id FK "NOT NULL"
        string versichertennr "nullable"
        boolean ist_primaer "default true"
    }
    CUSTODIANS {
        bigint id PK
        bigint resident_id FK "NOT NULL"
        string name "NOT NULL"
        string umfang "Betreuungsumfang"
        string kontakt "nullable"
    }
    PHYSICIANS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        string name "NOT NULL"
        string fachrichtung "nullable"
        string kontakt "nullable"
    }
    RESIDENT_FILES {
        bigint id PK
        bigint resident_id FK "NOT NULL"
        string collection "medialibrary"
        string pfad "NOT NULL"
    }
```

### CarePlanning & Speech

`RESIDENTS` (oben definiert) ist der gemeinsame Anker; hier verkürzt dargestellt.

```mermaid
erDiagram
    RESIDENTS ||--o{ SIS_ASSESSMENTS    : "Informationssammlung"
    SIS_ASSESSMENTS ||--o{ SIS_TOPIC_FIELDS : "6 Themenfelder"
    SIS_ASSESSMENTS ||--o{ RISK_ITEMS   : "Risikomatrix"
    SIS_ASSESSMENTS ||--o| SIS_ASSESSMENTS : "superseded_by"

    RESIDENTS ||--o{ CARE_MEASURES      : "Maßnahmenplan"
    CARE_MEASURES ||--o{ MEASURE_SCHEDULES : "Turnus"
    CARE_MEASURES ||--o| CARE_MEASURES  : "superseded_by"
    RESIDENTS ||--o{ CARE_REPORTS       : "Berichteblatt"
    CARE_REPORTS ||--o| CARE_REPORTS    : "superseded_by"
    CARE_MEASURES ||--o{ EVALUATIONS    : "Überprüfung"
    EVALUATIONS ||--o| EVALUATIONS      : "superseded_by"

    RESIDENTS ||--o{ TRANSCRIPTION_JOBS : "Spracherfassung"

    RESIDENTS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        string name "NOT NULL"
    }
    SIS_ASSESSMENTS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        bigint resident_id FK "NOT NULL"
        bigint created_by FK "NOT NULL"
        bigint superseded_by FK "nullable, self"
        int version "default 1"
        date erstellt_am "NOT NULL"
        string status "enum entwurf/aktiv/abgelöst"
        text eingangsfrage "Sichtweise d. Pflegebed."
        timestamp created_at
    }
    SIS_TOPIC_FIELDS {
        bigint id PK
        bigint sis_assessment_id FK "NOT NULL"
        string themenfeld "enum 6 Felder"
        text freitext "nullable"
        jsonb strukturdaten "nullable"
    }
    RISK_ITEMS {
        bigint id PK
        bigint sis_assessment_id FK "NOT NULL"
        string risiko "enum Dekubitus/Sturz/..."
        boolean eingeschaetzt "default false"
        text begruendung "nullable"
    }
    CARE_MEASURES {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        bigint resident_id FK "NOT NULL"
        bigint superseded_by FK "nullable, self"
        int version "default 1"
        string themenfeld "enum 6 Felder"
        text beschreibung "NOT NULL"
        text ziel "nullable"
        string verantwortlich "nullable"
        boolean aktiv "default true"
    }
    MEASURE_SCHEDULES {
        bigint id PK
        bigint care_measure_id FK "NOT NULL"
        string turnus_typ "enum schicht/uhrzeit/intervall"
        jsonb turnus_daten "NOT NULL"
    }
    CARE_REPORTS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        bigint resident_id FK "NOT NULL"
        bigint created_by FK "NOT NULL"
        bigint superseded_by FK "nullable, self"
        timestamp datum "NOT NULL"
        string schicht "enum früh/spät/nacht"
        text text "NOT NULL"
    }
    EVALUATIONS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        string evaluable_type "polymorph (sis/measure)"
        bigint evaluable_id "polymorph"
        bigint created_by FK "NOT NULL"
        bigint superseded_by FK "nullable, self"
        date datum "NOT NULL"
        string zielerreichung "enum erreicht/teilweise/nicht"
        string anlass "nullable"
    }
    TRANSCRIPTION_JOBS {
        bigint id PK
        bigint tenant_id FK "NOT NULL, idx"
        bigint resident_id FK "NOT NULL"
        bigint reviewer_id FK "nullable"
        string kontext "Themenfeld/Bericht"
        string audio_ref "temp, nach ASR gelöscht"
        string status "enum queued/transcribing/structuring/review/done"
        text rohtranskript "nullable"
        jsonb sis_vorschlag "nullable"
        timestamp freigegeben_at "nullable"
    }
```

## Sprach-Workflow (Human-in-the-Loop)

```
Tablet (Mikrofon, Alpine/MediaRecorder)
  → Audio-Upload → Queue-Job
  → Whisper (lokal): Audio → Rohtranskript
  → Ollama-LLM: Rohtranskript → SIS®-Themenfeld-Vorschlag (jsonb)
  → Reverb-Broadcast zurück ins UI
  → Pflegekraft prüft/korrigiert/gibt frei (Human-in-the-Loop)
  → Speicherung als SIS-/Bericht-Eintrag · Audio wird gelöscht
```

## Scope v1

**Enthalten:** Stammdaten/Bewohnerverwaltung · SIS®-Pflegeplanung (4 Strukturmodell-Elemente) ·
voller Sprach-Workflow · RBAC · Audit-Trail.

**Bewusst später (Schema lässt Platz):** Medikation/BHP · Controlling/QMS · QDVS-Export ·
Mehrmandanten-Betrieb über mehrere Heime (`tenant_id` ist aber von Beginn an vorgesehen).

## Herkunft

Basiert konzeptionell auf **Offene-Pflege.de (OPDE)**, einem freien Java-Swing-Pflegedokumentationssystem,
das 2025 wegen der regulatorischen Anforderungen (EU Cyber Resilience Act, EU-Produkthaftungsrichtlinie)
eingestellt wurde. OPCare übernimmt dessen Domänenwissen (Stammdaten, QDVS-Mapping als Referenz), nicht
dessen Code.

## Lizenz

**AGPL-3.0** — schützt die Software auch im SaaS-/Netzwerk-Betrieb (Copyleft erstreckt sich auf
über das Netzwerk bereitgestellte Dienste).
