# Design-Spec: OPCare — SIS®-basierte Pflegeplanung (Laravel)

**Datum:** 2026-06-04
**Status:** Freigegeben (Brainstorming abgeschlossen)
**Arbeitstitel:** OPCare
**Vorlage:** Offene-Pflege.de (OPDE), Java-Swing, inaktiv

---

## 1. Ziel & Kontext

Neuaufbau eines Pflegedokumentations- und Pflegeplanungssystems für die **stationäre Altenpflege** als
moderner Laravel-Stack. Ersetzt das eingestellte Java-Projekt OPDE konzeptionell. Die Pflegeplanung wird
nach dem deutschen **Strukturmodell / SIS®** neu modelliert; OPDEs erprobtes Stammdaten-Domänenmodell
dient als Vorlage (kein Code-Port).

**Nutzungskontext:** Einsatz in der Diakonie (perspektivisch >10 Heime). v1 zunächst für **ein Heim**.
**Lizenz:** Open Source, **AGPL-3.0** (Copyleft erstreckt sich auf netzwerkbereitgestellte Dienste). Als nicht-kommerzieller OSS-Anbieter
greift die CRA-Ausnahme; als Betreiber bleibt die DSGVO-Verantwortung (Gesundheitsdaten, Art. 9) maßgeblich.

### Erfolgskriterien
- Pflegekräfte können Bewohner-Stammdaten und eine vollständige SIS®-Pflegeplanung erfassen.
- Sprach-Workflow am Bett: gesprochene Doku → strukturierter SIS®-Entwurf → menschliche Freigabe.
- Manipulationssichere, MDK-konforme Dokumentation (append-only/versioniert, Audit-Trail).
- Sauber getrennte Backend-Domänen, an die später eine eigene Frontend-Design-Vorlage andockt.

### Nicht-Ziele (v1, YAGNI)
Medikation/BHP, Controlling/QMS, QDVS-Export, aktiver Mehrmandanten-Betrieb. Das Schema sieht
`tenant_id` und Erweiterungspunkte vor, die Module folgen später.

---

## 2. Tech-Stack

| Schicht | Technologie |
|---|---|
| Backend | Laravel 12, PHP 8.5 |
| Datenbank | PostgreSQL |
| Frontend/PWA | Blade + Livewire 3 + Alpine.js, Service Worker, Node (aktuelle LTS) + Vite |
| Realtime | Laravel Reverb (WebSockets) |
| Queue / Monitoring | Redis + Laravel Horizon |
| ASR | lokaler Whisper-Dienst (whisper.cpp-Server oder Python-FastAPI-Sidecar) |
| LLM | Ollama, on-prem via `three.linn.games`, Human-in-the-Loop |
| RBAC / Audit / Files | spatie: laravel-permission, laravel-activitylog, laravel-medialibrary |
| DTOs | spatie/laravel-data |
| Tests | Pest (inkl. Pest Arch) |

**Begründung der Frontend-Wahl:** Livewire 3 + Alpine (Ansatz „C") für schnellen Start mit minimalem
JS-Overhead. Vue/Inertia-Inseln bei Bedarf nachrüstbar. Der Audio-Recorder ist in *jedem* Fall eine
clientseitige Alpine/MediaRecorder-Insel; das KI-Ergebnis kommt asynchron über Reverb-WebSockets.

---

## 3. Architektur — Bounded Contexts

Domänen-orientierte Struktur unter `app/Domains/` (PSR-4-Ordnerkonvention; `nwidart/laravel-modules`
bei wachsender Komplexität nachrüstbar).

```
app/
├── Domains/
│   ├── Identity/     # Auth, Benutzer, Rollen/Rechte, Tenant-Scoping
│   ├── Masterdata/   # Bewohner, Diagnosen/ICD, Kassen, Betreuer, Ärzte, Gebäude/Zimmer
│   ├── CarePlanning/ # SIS®-Strukturmodell: Informationssammlung → Maßnahmen → Bericht → Evaluation
│   └── Speech/       # Audio-Handling, Transkription, LLM→SIS®-Strukturierung
│       └── (je Domain:) Models/ Actions/ Data/ Policies/ Events/ Jobs/ Database/{migrations,factories}/ Tests/
├── Support/          # domänenübergreifende Helfer, Base-Klassen
└── Http/             # dünne Controller + Livewire-Komponenten (delegieren an Actions)
```

**Layering (Einbahnstraße):** `Livewire/Controller → Action → Model/Service`. Daten zwischen Schichten
als DTOs (`spatie/laravel-data`), nicht als lose Arrays. Validierung über Form Requests / Data-Objekte.
Eine Pest-Arch-Regel erzwingt, dass `Domains` nicht von `Http` abhängt.

---

## 4. Datenmodell

Vollständiges ER-Diagramm: siehe `README.md`. Querschnittskonventionen:

- Jede Tabelle: `bigint id PK`, `tenant_id FK` (globaler Eloquent-Scope), `created_by`/`updated_by`, `timestamps`.
- **Append-only / Versionierung** bei rechtlich relevanten Einträgen (`sis_assessments`, `care_reports`,
  `evaluations`): Korrektur erzeugt neue Version, alte via `superseded_by` (self-FK) verkettet, bleibt erhalten.
- `evaluations` polymorph (`evaluable_type`/`evaluable_id`) auf SIS oder Maßnahme.
- Audio (`transcription_jobs.audio_ref`) wird nach erfolgreicher Transkription gelöscht (Art. 5 DSGVO).

### Domänen-Tabellen (Kurzform)
- **Identity:** `tenants`, `users`, Rollen/Rechte (spatie-Tabellen).
- **Masterdata:** `residents`, `icd_codes`, `resident_diagnoses`, `health_insurances`, `resident_insurance`,
  `custodians`, `physicians` (+ Pivot `resident_physician`), `resident_files`, `buildings`, `floors`,
  `stations`, `rooms`.
- **CarePlanning:** `sis_assessments`, `sis_topic_fields` (6 Themenfelder), `risk_items`, `care_measures`,
  `measure_schedules`, `care_reports`, `evaluations`.
- **Speech:** `transcription_jobs`.

### SIS®-Themenfelder (stationär)
Kognition & Kommunikation · Mobilität & Beweglichkeit · krankheitsbezogene Anforderungen & Belastungen ·
Selbstversorgung · Leben in sozialen Beziehungen · Wohnen & Häuslichkeit.

---

## 5. Sprach→SIS®-Pipeline (Human-in-the-Loop)

State-Machine auf `transcription_jobs.status`:
`queued → transcribing → structuring → review → done` (+ `failed`).

```
Tablet (Alpine/MediaRecorder) → Audio-Upload → Job queued
  → Job „transcribing": Whisper (lokal, HTTP, Timeout+Retry) → rohtranskript
  → Job „structuring": Ollama → JSON, validiert gegen Data-Schema → sis_vorschlag (jsonb)
  → status „review": Reverb-Broadcast ins UI
  → Pflegekraft prüft/korrigiert/gibt frei (Human-in-the-Loop)
  → persistiert als SIS-/Bericht-Eintrag, status „done", Audio gelöscht
```

**Regeln:**
- Jeder Schritt ein eigener, idempotenter, einzeln retrybarer Queue-Job (Horizon-überwacht).
- LLM-Output wird **niemals** ungeprüft in Domänen-Tabellen geschrieben — erst Schema-Validierung,
  dann menschliche Freigabe.
- Whisper- und Ollama-Zugriff über Adapter-Interfaces (im Test gefakt, kein echtes Modell im CI).

---

## 6. Sicherheit & Datenschutz (Gesundheitsdaten, DSGVO Art. 9)

- **Auth:** Laravel-Auth, 2FA-fähig, Session-Timeout; vorbereitet für späteres SSO/AD der Diakonie.
- **Autorisierung:** Policies/Gates pro Domäne; `tenant_id`-Scope global erzwungen (kein Query ohne Mandantenfilter).
- **Verschlüsselung:** TLS überall; sensible Felder at-rest verschlüsselt; on-prem Deployment, kein externes LLM/Cloud.
- **Audit:** `activitylog` auf allen Domänen-Schreibzugriffen; append-only Historie.
- **Datensparsamkeit:** Audio-Löschung nach ASR; dokumentiertes Aufbewahrungs-/Löschkonzept.

---

## 7. Testing-Strategie

- **Pest** Feature-Tests pro Action (Domänenlogik), **Pest Arch**-Tests für die Layering-Regel.
- **Unit-Tests** für DTOs/Wertobjekte (Turnus-Logik, Versionierungslogik).
- KI-Pipeline mit gefakten Whisper-/Ollama-Adaptern.
- Factories + Seeder pro Domäne. CI-grün als Merge-Gate.

---

## 8. Offene Punkte / spätere Entscheidungen

- Konkretes Whisper-Deployment (whisper.cpp-Server vs. FastAPI-Sidecar) — Adapter abstrahiert die Wahl.
- Frontend-Design-Vorlage des Designers (.md im Root) wird **nach** dessen Umsetzung eingearbeitet;
  Backend ist bewusst design-agnostisch (dünne Livewire-Schicht).
- ICD-10-Katalog-Quelle/Import.
- SSO/AD-Anbindung (v2).
