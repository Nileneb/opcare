# Lieferschein → Wareneingang Capture (KI-WaWi Inkrement 1)

Ein:e Berechtigte:r fotografiert einen Lieferschein/eine Rechnung; ein VLM liest die Positionen, jede wird auf den
Artikel-Katalog des Mandanten gemappt und nach Bestätigung als **FIFO-Wareneingang** gebucht — „10 Gebinde Mehl
geliefert" → angekommen in der Warenwirtschaft. Erstes Inkrement eines KI-Warenwirtschafts-Programms
([[opcare-stockpilot-vision-mcp]] für die spätere Regal-Zählung).

## Ablauf

1. **Foto** hochladen (Route `/wareneingang-capture`, Nav „Beleg→Wareneingang", Gate admin/buchhaltung).
2. **VLM-Extraktion** (`LieferscheinVlmAnalyzer` → `OllamaLieferscheinAnalyzer`, Modell
   `config('speech.capture.model')`): Lieferant, Datum, Lieferschein-Nr. und Positionen mit
   `text/menge/einheit/einzelpreis/charge_nr/mhd`.
3. **Artikel-Matching** (`ArtikelMatcher` → `EmbeddingArtikelMatcher`) je Position:
   - **Primär — Lern-Gedächtnis** (`lieferant_artikel_aliasse`): normalisierter Text (+ Lieferant) → Artikel,
     wächst bei jeder Bestätigung (wie ein Kreditor→Konto-Signal). Exakt = höchster Score.
   - **Sekundär — Embedding-Cosine**: Positionstext lokal via Ollama embedden
     (`config('speech.capture.embedding_model')`, Default `nomic-embed-text`), Cosine gegen die vorberechneten
     `artikel.name_embedding`-Vektoren (Brute-Force in PHP, kein pgvector-Zwang). Fehlt das Embedding-Modell, wird
     das Embedding-Signal **transparent übersprungen** (nur Gedächtnis), nie ein gefälschter Treffer.
   - Backfill der Artikel-Vektoren: `php artisan artikel:embeddings-backfill` (idempotent).
4. **Bestell-Abgleich**: passt eine eindeutige offene `Bestellposition` desselben Lieferanten zum gematchten
   Artikel, wird sie vorbelegt (mehrdeutig → kein Auto-Match).
5. **Bestätigung je Position** (`CaptureWareneingang::bestaetige`): bucht gegen die Bestellposition
   (`BestellungWareneingang`, führt menge_geliefert/Status nach) oder standalone (`Wareneingang`, FIFO-Schicht mit
   Lieferant + Charge/MHD). Über-/Doppellieferung wirft (kein stilles Klemmen). Jede Bestätigung **lernt** das
   Gedächtnis weiter (`merke`).

## Persistenz

`LieferscheinAnalyse` (Foto als signiert + tenant-scoped abrufbares Media, Collection `lieferschein`) +
`LieferscheinPositionVorschlag` je Zeile (`Vorgeschlagen/Bestätigt/Verworfen`, `matched_artikel_id`,
`matched_bestellposition_id`, `kandidaten`, nach Buchung `wareneingang_bewegung_id`). Bewusst eigene Modelle —
das Beleg-Capture-`EinsortierungsVorschlag` persistiert keine Positionen.

## DSGVO

Die Pipeline berührt ausschließlich **Lieferant/Artikel/Beleg** — **null Gesundheits-/Bewohnerdaten**
(Datenminimierung Art. 5(1)(c), vermeidet Art.-9-Sonderkategorien). Embeddings werden **lokal** (Ollama on-prem)
berechnet, mandanten-isoliert; Foto-Download signiert + tenant-scoped.

## Wiederverwendung & Ausblick

Spiegelt das bestehende Beleg-Capture-Muster (`BelegVlmAnalyzer`/Provider-Binding/HITL-Gate) und nutzt die
FIFO-Spine ([[opcare-fifo-inventur]]) + `BestellungWareneingang` aus der Beschaffung. Spätere Inkremente: ein
eigenständiger `ArtikelIndex`-Dienst (prefilter-`KontoIndex`-Muster) und das Regal-Zähl-Vision-MCP aus stockpilot.

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-lieferschein-wareneingang-capture-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-lieferschein-wareneingang-capture.md`
