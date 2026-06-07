# WaWi-Stammdaten-Datenimport (Mandanten-Onboarding)

Ein:e Admin lädt eine CSV mit Artikeln/Lieferanten/Anfangsbestand hoch; das System parst sie, gleicht jede Zeile
gegen den bestehenden Katalog ab (anlegen vs. mergen) und legt nach **menschlicher Bestätigung** Stammdaten + einen
**bewerteten Anfangsbestand** (FIFO-Schicht + Buchung) an. Danach ist ein Mandant einsatzbereit. Route
`/datenimport` (Gate admin/buchhaltung).

Dieses Modul erhebt den in der Lieferschein-Capture ([[opcare-lieferschein-capture]]) gebauten
**Artikel-Matching-Index** zum geteilten WaWi-Fundament: der Import ist sein zweiter Consumer (Dedup/Merge) und macht
ihn dabei besser (bestätigte Merges füttern das Match-Gedächtnis). opcare ist System of Record; **prefilter bleibt
unangetastet** — nur das Upload→Parse→Spalten-Alias-Muster wurde geborgt (opcare-nativ, DSGVO-lokal).

## Leitprinzip: editierbar, nicht starr

- **Spalten-Mapping** wird automatisch erkannt (`SpaltenAlias`: viele Header-Varianten je Zielfeld) und ist in der
  Vorschau **manuell korrigierbar** („Mapping anwenden" parst neu).
- Jede Zeile ist editierbar: Aktion (anlegen/mergen/überspringen), Match-Kandidat, Menge/Einstandspreis.
- **Nichts wird ohne Bestätigung gebucht** (HITL). Zeilenweise oder „Alle übernehmen".

## Ablauf

1. CSV hochladen (Delimiter `;`/`,` auto-erkannt, BOM/UTF-8). Ziel-Typ (Artikel/Lieferant) + Anfangsbestand-Modus wählen.
2. **Parse + Matching**: je Zeile `ArtikelMatcher`/`LieferantMatch` → Vorschlag *anlegen* (kein Treffer) oder *mergen*
   (Score ≥ `config('import.merge_threshold')`, Default 0.85).
3. **Vorschau prüfen/korrigieren** (Mapping + Zeilen).
4. **Commit**: Lieferant/Artikel anlegen oder mergen (**nur leere Felder ergänzt — kein Blind-Overwrite**). Bei
   Anfangsbestand > 0 → FIFO-Eröffnungsschicht + Buchung. Bestätigte Merges lernen das Match-Gedächtnis.

## Anfangsbestand-Buchung (umschaltbar)

- **Eröffnungsbilanz (EBK)** — Standard: *Warenbestand an Konto 9000 „Anfangsbestand (Eröffnungsbilanz)"* (Passiv),
  buchhalterisch sauber beim Go-live, keine Scheinschuld.
- **Echte Verbindlichkeit**: *Warenbestand an Verbindlichkeiten* — falls der Anfangsbestand eine reale offene
  Lieferung ist.

In beiden Fällen entsteht die FIFO-Bewertungsschicht (`Wareneingang` mit optionalem Gegenkonto).

## DSGVO

Nur Artikel/Lieferant/Bestand — **null Personendaten** (Datenminimierung Art. 5(1)(c)). Upload-Datei tenant-scoped +
signiert abrufbar; die UI warnt vor bewohnerbezogenen Inhalten.

## Folge-Inkremente

XLSX-Import (eigene Lib), ein eigenständiger Artikel-Matching-Index-Dienst (falls mehrere Apps), das
Regal-Zähl-Vision-MCP aus stockpilot ([[opcare-stockpilot-vision-mcp]]).

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-wawi-stammdaten-import-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-wawi-stammdaten-import.md`
