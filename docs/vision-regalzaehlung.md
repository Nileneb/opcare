# Vision-Regalzählung (YOLO über das Vision-MCP)

Dritte Komponente des KI-WaWi-Programms: ein Regalfoto wird per YOLO-Objekterkennung gezählt, die Zahl auf den
Artikel-Katalog gemappt und nach Bestätigung als **`Inventurposition.ist_menge`** in eine offene Inventur gebucht.
Route `/regalzaehlung` (Gate admin/buchhaltung/pflegefachkraft).

**opcare bleibt System of Record.** Die Optik (YOLO-Training + Inferenz) läuft in einem eigenen, zustandsarmen
Dienst — dem **Vision-MCP** (`Nileneb/vision-mcp`), gestrippt aus stockpilot, verpackt wie whisperX-mcp (MCP über
HTTP + Docker, GPU-CDI, `scripts/ai-services.sh`). Das MCP hält nur die `.pt`-Gewichte je Mandant; Katalog, Mapping
und Buchung bleiben in opcare.

## Der Loop (Human-in-the-loop)

1. **Labeling** — Regalfoto → MCP-`auto_annotate` (YOLO11x + SAM2) schlägt Boxen vor; der Mensch korrigiert/bestätigt
   → wachsender Mandanten-Datensatz.
2. **Training** — ab genug gelabelten Bildern → MCP-`train` (async Job) → neues aktives `YoloModell` (Pfad im
   MCP-Volume). **Hinter Inbetriebnahme-Schalter** (`vision.training_aktiv`, default aus).
3. **Zählen** — Regalfoto → MCP-`detect(model_path)` → Detektionen → über `ProductLabel` (yolo-Label → Artikel ×
   Multiplier, z. B. „1 Kiste = 12 Stück") auf einen **Mengenvorschlag je Artikel**.
4. **Buchen** — berechtigte Person bestätigt die (editierbare) Menge → schreibt `Inventurposition.ist_menge` in eine
   **offene** Inventur. Fehlt der Artikel in der Inventur → Fehler (kein stilles Anlegen); geschlossene Inventur →
   abgelehnt. **Das ist der Outcome-Anker** — die Zählung landet echt in der WaWi.

## Tool-Surface des Vision-MCP

`detect(image_base64, model_path, confidence?)` → `{detections, counts, model_used}` · `auto_annotate(image_base64,
use_sam?)` → `{suggestions}` · `train(dataset_zip_base64, tenant_id, …)` → `{job_id}` (async) · `train_status(job_id)`
→ `{status, model_path?, metrics?}`. Bearer-Token auf `/mcp*`, `/health` offen, Path-Traversal-Schutz auf `model_path`.

## Reife / Inbetriebnahme

Ohne trainiertes Modell liefert `detect` nur Basis-COCO-Klassen (schwach für Heim-Artikel) → die Zähl-Buchung ist
erst nach Training je Mandant sinnvoll. Bis dahin „gebaut & nutzbar für Labeling"; Training + scharfe Buchung pro
Mandant frei schaltbar. Siehe `docs/INBETRIEBNAHME.md`.

## DSGVO

Nur Regal-/Lagerfotos + Artikel-/Label-Daten — **null Personendaten** (keine Bewohner/Mitarbeiter im Bild; UI warnt).
Das Vision-MCP hält keine Mandantendaten außer den Gewichten; Fotos liegen tenant-scoped + signiert in opcare.

## Repos / Spec & Plan

- Vision-MCP: `Nileneb/vision-mcp` (eigenes Repo, whisperX-Muster).
- Spec: `docs/superpowers/specs/2026-06-07-vision-mcp-design.md` · Plan: `docs/superpowers/plans/2026-06-07-vision-mcp.md`
