# Lieferschein → Wareneingang Capture (KI-WaWi Inkrement 1)

**Datum:** 2026-06-07
**Status:** Design — vom User approved (2026-06-07).

## Programm-Kontext

Erstes Inkrement eines KI-Warenwirtschafts-Programms. Drei Schichten, klare Ownership:

| Schicht | Wer | DSGVO |
|---|---|---|
| System of Record | **opcare** — Artikel/Lieferant/Bestand/Bestellung/Inventur (gebaut), HITL, Buchung | nur Waren/Beleg-Daten, **null Pflegedaten** |
| **Capture + Matching** ← *dieses Inkrement* | opcare-Capture, erweitert auf Lieferscheine + lokales Embedding-Artikel-Matching | desgl. |
| Vision-MCP | gestripptes `stockpilot` als Docker/MCP-Tool (whisper-Muster), YOLO-Regalzählung | nur Regal-Fotos |

Spätere Inkremente (nicht hier): Vision-MCP aus stockpilot ([[opcare-stockpilot-vision-mcp]]); Extraktion des
Artikel-Matchings in einen eigenständigen `ArtikelIndex`-Dienst (prefilter-`KontoIndex`-Muster), falls je
mehrere Apps ihn brauchen.

## Ziel

Ein:e Berechtigte:r fotografiert einen Lieferschein/eine Rechnung. Ein VLM extrahiert die Positionen
(Menge/Einheit/Text, optional Charge/MHD/Einzelpreis). Jede Position wird per **lokalem Embedding-Matching** auf
den Artikel-Katalog des Mandanten gemappt (Trefferrate) und — wenn vorhanden — gegen eine offene Bestellung
desselben Lieferanten abgeglichen. Der Mensch bestätigt je Position; die Bestätigung **bucht** einen FIFO-Wareneingang
(standalone oder gegen die Bestellposition) und **lernt** das Match-Gedächtnis weiter. „10 Gebinde Mehl geliefert"
→ FIFO-Schicht bei Anlieferung → angekommen in der WaWi.

**Outcome-Anker (gegen „Feature ohne Caller"):** Jede Bestätigung schreibt einen echten `Wareneingang`/
`BestellungWareneingang` mit FIFO-Schicht. Kein Demo-Pfad.

## Architektur-Leitplanken (Ist-Stand, verbindlich)

- Erweitert die bestehende `Capture`-Domäne (`app/Domains/Capture`). Provider-Pattern: ein Contract bindet den
  VLM-Adapter (`CaptureServiceProvider` bindet `config('speech.fake') ? Fake… : Ollama…`). Vorbild der ganzen
  Pipeline ist `Belegerfassung`/`BelegCapture`/`BelegVlmAnalyzer`/`BelegExtraktion` ([[opcare-vlm-beleg-capture]]).
- VLM über Ollama: `config('speech.ollama.url')`, Modell `config('speech.capture.model')`. Ollama-Routing:
  prod → GPU-Host-LAN, dev → localhost (User-Infra, nicht hier konfigurieren).
- FIFO-Spine ([[opcare-fifo-inventur]]): `Wareneingang::handle(Artikel, menge, preis, datum, notiz, chargeNr,
  mhd, lieferantId)` und `BestellungWareneingang::handle(Bestellposition, menge, preis, datum, chargeNr, mhd)`
  existieren bereits. Über-/Doppellieferung wirft (kein stilles Klemmen).
- `BaseModel` = BelongsToTenant + LogsActivity; Append-/mutierende Modelle nur `BelongsToTenant`.
- Livewire 4: `#[Layout('layouts.app')]`, `abort_unless`-Gate je Schreibaktion, `WithFileUploads`,
  tenant-scoped `exists` via `$this->tenantExists('tabelle')` (kein nacktes `exists:`), signierte Media-Downloads
  + Owner-Whitelist im `MediaDownloadController` ([[opcare-media-download-owner-pattern]]).
- Tests: Pest, `Model::create`, `app(CurrentTenant::class)->set($t)`, `Role::findOrCreate`. PHPStan L5
  (`php -d memory_limit=1G vendor/bin/phpstan analyse`), Pint. **Kein stilles Schlucken.**

## Komponenten

### VLM-Extraktion (Lieferschein)

- **Contract** `app/Domains/Capture/Contracts/LieferscheinVlmAnalyzer.php` — `analysiere(string $imageBase64,
  string $mimeType): LieferscheinExtraktion` (spiegelt `BelegVlmAnalyzer`).
- **DTO** `app/Domains/Capture/Data/LieferscheinExtraktion.php` (Spatie Data): `lieferant: ?string`,
  `datum: ?string`, `lieferschein_nr: ?string`, `konfidenz: float`, `positionen: array<LieferscheinPositionDaten>`.
- **DTO** `app/Domains/Capture/Data/LieferscheinPositionDaten.php`: `text: string`, `menge: ?float`,
  `einheit: ?string`, `einzelpreis: ?float`, `charge_nr: ?string`, `mhd: ?string`.
- **Adapter** `OllamaLieferscheinAnalyzer` (Prompt zieht strukturierte Positionszeilen; `format=json`,
  `think=false`) + **`FakeLieferscheinAnalyzer`** (deterministisch, feste Positionen für Tests/`speech.fake`).
- Binding im `CaptureServiceProvider` analog zum Beleg-Analyzer.

### Embedding-Artikel-Matching (lokal in opcare)

- **Interface** `app/Domains/Capture/Contracts/ArtikelMatcher.php` — `match(string $positionsText,
  ?int $lieferantId, int $tenantId, int $topK = 5): array<ArtikelKandidat>`.
- **DTO** `ArtikelKandidat`: `artikel_id: int`, `name: string`, `score: float`, `quelle: 'gedaechtnis'|'embedding'`.
- **`EmbeddingArtikelMatcher`** kombiniert zwei Signale (prefilter-`KontoIndex`-Muster):
  - **Primär — Match-Gedächtnis:** Tabelle `lieferant_artikel_alias` (`tenant_id`, `lieferant_id` nullable,
    `norm_text`, `artikel_id`, `treffer` count). Normalisierter Exakt-/Teiltreffer → hoher Score. Wächst bei jeder
    Bestätigung (`treffer++` oder neuer Alias).
  - **Sekundär — Embedding-Cosine:** Positionstext via Ollama-Embedding (`config('speech.capture.embedding_model')`,
    z. B. `nomic-embed-text`) einbetten; Cosine gegen `artikel.name_embedding` (JSON-Vektor + `embedding_model`).
    Brute-Force-Scan in PHP (Katalog klein) — **kein pgvector/DB-Zwang**.
  - Score-Kombination: Gedächtnis dominiert (z. B. ×1.0), Embedding sekundär (×0.6, Schwelle ~0.5). Top-k zurück.
- **Artikel-Embeddings:** Migration `artikel.name_embedding` (json nullable) + `embedding_model` (string nullable).
  Service `ArtikelEmbedder` (Ollama `/api/embeddings`) berechnet/aktualisiert bei Artikel-Anlage/Namensänderung;
  Artisan-Command `artikel:embeddings-backfill` (idempotent, nur fehlende/veraltete). Fehlt das Embedding-Modell
  → Embedding-Signal **transparent übersprungen** (Matcher liefert nur Gedächtnis-Treffer + Log-Hinweis, kein
  stiller 0-Score), nie ein gefälschter „Treffer".
- **`FakeArtikelMatcher`** (deterministisch) für Tests.

### Vorschlags-Persistenz & HITL

- **`LieferscheinAnalyse`** (BaseModel, `implements HasMedia`): `lieferant_text`, `datum`, `lieferschein_nr`,
  `roh_json`, `modell`, `konfidenz`, `lieferant_id` (nullable, gematcht), `erstellt_von`. Foto in Media-Collection
  `lieferschein` (MinIO-fähig, signierter Download via `MediaDownloadController` — Owner-Whitelist um
  `LieferscheinAnalyse` erweitern).
  - **Lieferant-Zuordnung:** `lieferant_text` wird per normalisiertem Namensabgleich (exakt/Teilstring) gegen die
    `Lieferant`-Stammdaten des Mandanten gemappt (`lieferant_id` vorbelegt); kein Treffer → Select in der UI inkl.
    „neuen Lieferant anlegen" (wie der bestehende Mini-CRUD in `Buchhaltung`). Kein Embedding nötig (Lieferanten
    pro Mandant sind eine kurze Liste).
- **`LieferscheinPositionVorschlag`** (BaseModel): `analyse_id`, extrahierte Felder (`text`, `menge`, `einheit`,
  `einzelpreis`, `charge_nr`, `mhd`), `matched_artikel_id` (nullable), `matched_bestellposition_id` (nullable),
  `kandidaten` (json — top-k für die UI), `konfidenz`, `status` (`PositionStatus`:
  Vorgeschlagen/Bestätigt/Verworfen), `wareneingang_bewegung_id` (nullable, nach Buchung), `entschieden_von/_am`.
- *Bewusst eigene Modelle* statt `EinsortierungsVorschlag` — letzteres persistiert `positionen` nicht.

### Bestell-Abgleich

- Beim Anlegen der Vorschläge: je Position offene `Bestellposition` desselben (gematchten) Lieferanten suchen,
  deren `artikel_id == matched_artikel_id` und `offen()`; eindeutiger Treffer → `matched_bestellposition_id`
  vorbelegen (Mensch kann ändern/lösen).

### UI

- Livewire **`Wareneingangerfassung`** (`app/Livewire/Capture/Wareneingangerfassung.php`, Route
  `/wareneingang-capture`, Name `wareneingang-capture`, Nav „Beleg→Wareneingang" im Finanzen-Block).
- Gate **admin/buchhaltung** (`abort_unless` je Schreibaktion — Finanz-Rollen-Gate).
- Flow: Foto hochladen → `analysieren()` (ruft Analyzer + Matcher + Bestell-Abgleich, persistiert) → Tabelle der
  Positionen mit Kandidaten-Dropdown (vorausgewählt = bester Kandidat), editierbarer Menge/Einheit/Charge/MHD und
  Buchungsziel (offene Bestellposition wenn gematcht, sonst Standalone) → `bestaetigePosition(id)` bucht →
  `verwerfePosition(id)`.

### Buchung & Lernen

`CaptureWareneingang::bestaetige(LieferscheinPositionVorschlag, ...)`:
1. `abort` wenn kein `matched_artikel_id` gewählt (kein Raten).
2. Liegt `matched_bestellposition_id` → `BestellungWareneingang::handle(...)`, sonst `Wareneingang::handle(...)`
   (mit Lieferant + Charge/MHD wenn vorhanden). Exceptions (Über-/Doppellieferung) → `addError`, kein Crash.
3. `wareneingang_bewegung_id` + Status `Bestätigt` + `entschieden_von/_am` setzen.
4. **Match-Gedächtnis füttern:** `lieferant_artikel_alias` upserten (lieferant_id + norm_text → matched_artikel_id).

## Daten-/Fehlerfluss-Zusammenfassung

Foto → `OllamaLieferscheinAnalyzer` → `LieferscheinExtraktion` → je Position `ArtikelMatcher` (Gedächtnis +
Embedding) + offene-Bestellposition-Suche → `LieferscheinAnalyse` + `…PositionVorschlag` → HITL bestätigt je
Position → `BestellungWareneingang`|`Wareneingang` (FIFO) → Gedächtnis lernt. Fehlende Embedding-Modelle oder
nicht-gematchte Positionen werden **transparent ausgewiesen**, nie als „ok" gefälscht; Über-Lieferung wirft.

## DSGVO

Pipeline berührt ausschließlich Lieferant/Artikel/Beleg — **null Gesundheits-/Bewohnerdaten** (Art. 5(1)(c)
Datenminimierung, vermeidet Art. 9). Embeddings werden **lokal** (Ollama on-prem) berechnet, mandanten-isoliert
(`tenant_id` an Alias + Embedding-Lookup), Foto-Download signiert + tenant-scoped.

## Verifikation

- `FakeLieferscheinAnalyzer` + `FakeArtikelMatcher` (deterministisch, über `speech.fake`).
- Pest-Feature: Extraktion→Match→Standalone-Wareneingang (FIFO-Schicht entsteht, Bestand steigt); Extraktion→Match
  gegen offene Bestellposition (`BestellungWareneingang`, menge_geliefert/Status); Über-Lieferung wirft; Bestätigung
  ohne Artikel → Fehler; Gedächtnis lernt (zweiter gleicher Lieferschein matcht ohne Embedding).
- Matcher-Unit: Gedächtnis-Primärsignal schlägt Embedding; fehlendes Embedding-Modell → nur Gedächtnis + Skip-Log
  (kein gefälschter Treffer).
- Livewire-Smoke (Rolle buchhaltung), Gate-403 für fremde Rolle, tenant-scoped (Fremd-Tenant-Artikel/Bestellung
  abgewiesen). Volle Suite/PHPStan/Pint Gate. `migrate:fresh --seed` + Demo-Lieferschein. Screenshot + Wiki/Doku.
