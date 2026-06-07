# Vision-MCP (Regal-Zählung aus stockpilot) — Design

**Datum:** 2026-06-07
**Status:** Design — vom User approved (2026-06-07).

## Programm-Kontext

Dritte Komponente des KI-WaWi-Programms. **opcare bleibt System of Record** (Artikel/Bestand/Inventur, HITL,
Buchung); das **Vision-MCP** ist ein zustandsarmer Optik-Dienst (YOLO-Regalzählung), gestrippt aus `stockpilot`
([[opcare-stockpilot-vision-mcp]]), verpackt wie `whisperx-mcp` (MCP + Docker, `scripts/ai-services.sh`, GPU-CDI).

## Entscheidungen (User, 2026-06-07)

- **Repo: neues Schwester-Repo `vision-mcp`** (eigenes Repo wie whisperx-mcp, schlanke Deps, ohne Django).
  **NICHT in der Auto-Push-Freigabe** → Repo-Anlegen (`gh repo create`) und jeder Push erfolgen **erst nach
  expliziter User-Bestätigung**. stockpilot bleibt als Referenz-Django-App unberührt (Module werden kopiert).
- **Scope Inkrement 1: voller Loop** — `detect` + `auto_annotate` + `train` (+ `train_status`) im MCP UND die
  opcare-Anbindung (Foto → Labeling-Hilfe → Bestätigung → Training-Trigger → Zählung → Inventur-`ist_menge`).

## Teil A — Repo `vision-mcp` (Python, eigenständig)

**Stack (whisperx-mcp gespiegelt):** MCP low-level SDK (`mcp>=1.2.0`) + Starlette + uvicorn:8000. `app/server.py`
mit `@server.list_tools()`/`@server.call_tool()`. `GET /health` (offen: status/ready/gpu/model_dir),
`Mount /mcp` (StreamableHTTP, `stateless=True`), Bearer-Auth (`API_TOKEN`-Env, `BearerAuthMiddleware`, schützt nur
`/mcp*`). Dockerfile-Base `nvidia/cuda:12.4.1-cudnn-runtime-ubuntu22.04`, `opencv-headless` statt ffmpeg,
ultralytics-Wheel deterministisch (cu124). On-demand Modell-Load (kein eager im Build). CMD `uvicorn app.server:app`.

**Struktur:**
```
vision-mcp/
  app/server.py        # MCP-Server + 4 Tools (neu)
  app/config.py        # Env: API_TOKEN, MODEL_DIR=/models, DEFAULT_DETECT_MODEL, SUGGEST_YOLO/SAM (neu)
  vision/inference.py  # aus stockpilot apps/vision/inference.py — DetectionResult, StubBackend,
                       #   UltralyticsBackend.detect(), aggregate_by_label; _resolve_path → model_path-Param
  vision/detect.py     # neu: detect_image(image_bytes, model_path, conf) -> list[DetectionResult] (pure, ORM-frei)
  training/suggest.py  # aus stockpilot apps/training/suggestions.py — Suggestion, _iou, merge, run_yolo,
                       #   run_sam, generate_for_image_path (settings → os.environ)
  training/train.py    # neu (aus tasks.py extrahiert): materialize_dataset, train_sync, register_weights,
                       #   _extract_metrics, _class_names_from_yaml — ohne Celery/ORM/django-tenants
  Dockerfile  requirements.txt  README.md  tests/  pyproject/pytest.ini
```

**Tool-Surface** (Input/Output JSON über `call_tool`):
- **`detect`**: `{image_base64, model_path, confidence?=0.25, filename?}` → `{detections:[{label,confidence,bbox}],
  counts:{label:n}, model_used, processing_time_seconds}`. Blockierend (schnell), `anyio.to_thread.run_sync` +
  GPU-Lock. **Path-Traversal-Schutz:** `model_path` muss unter `MODEL_DIR` liegen.
- **`auto_annotate`**: `{image_base64, use_sam?=true, yolo_model?, sam_model?}` → `{suggestions:[{label,confidence,
  source,x_center,y_center,width,height}], processing_time_seconds}` (YOLO11x + SAM2-tiny, IoU-Dedup).
- **`train`**: `{dataset_zip_base64, tenant_id, base_model?='yolo11n.pt', epochs?=50, batch_size?=4,
  image_size?=640}` → **sofort** `{job_id, status:'running'}` (Training dauert lange → **async**). Ergebnis wird unter
  `MODEL_DIR/{tenant_id}/job{n}_v{m}.pt` abgelegt + `MODEL_DIR/{tenant_id}/active` aktualisiert.
- **`train_status`**: `{job_id}` → `{status:'running'|'completed'|'failed', model_path?, class_names?, metrics?,
  error?}`. (Job-State in-memory Dict + Threadpool; ein Volume hält die Gewichte.)

**Zustand:** nur das Volume `MODEL_DIR=/models/{tenant_id}/*.pt` (+ `active`-Textdatei). **Keine DB.** Mandant/Modell
kommen pro Call (detect: expliziter `model_path` von opcare; train: `tenant_id` → schreibt `active`).

**Tests:** pytest mit `StubBackend` (deterministisch, COCO-Labels) für detect/auto_annotate ohne GPU;
`train`-Smoke mit Mini-Dataset (oder gemockt); Path-Traversal-Abwehr; Bearer-Auth (401 ohne Token).

## Teil B — opcare-Anbindung

**Dienst-Orchestrierung:** `vision-mcp` in `docker/ai-services/docker-compose.ai.yml` (+ `.gpu.yml`,
`build.context: ${VISION_MCP_CONTEXT:-../../../vision-mcp}`, Port z. B. 8001) + `scripts/ai-services.sh` (Health-Probe
`/health`, GPU/CPU-Zweig, „nur fehlende bauen" — bestehendes Muster [[opcare-ai-services-dev-orchestrierung]]).

**Client:** `app/Domains/Vision/Services/VisionMcpClient.php` (Contract `VisionClient` + `HttpVisionClient` +
`FakeVisionClient`): `detect(string $imageB64, string $modelPath, float $conf): array`, `autoAnnotate(...)`,
`train(string $zipB64, string $tenantRef, ...): string` (job_id), `trainStatus(string $jobId): array`. HTTP via
`Http::withToken(config('vision.token'))->post(config('vision.url').'/mcp/', [jsonrpc tools/call …])`. Binding
`config('vision.fake')`-gegated (wie Capture).

**Domäne** `app/Domains/Vision`:
- `YoloModell` (BaseModel): `tenant_id`, `model_path` (im MCP-Volume), `version`, `aktiv`, `class_names` (json),
  `metrics` (json). opcare hält den aktiven Modellpfad je Mandant (das, was im MCP zustandslos ist).
- `ProductLabel` (BaseModel): `yolo_label` (string) → `artikel_id` (FK) × `multiplier` (decimal, „1 Kiste = 12") —
  das Mapping generischer Detektions-Labels auf den Artikel-Katalog. (Übernimmt stockpilots `ProductLabel`-Idee.)
- `RegalAufnahme` (BaseModel, HasMedia `foto`) + `RegalDetection` (Append): persistiert Foto + Detektionen +
  vorgeschlagene Zählung je Artikel (HITL-Vorschlag, spiegelt das Capture-Muster).

**Loop-UI:** Livewire `app/Livewire/Vision/Regalzaehlung.php` (Route `/regalzaehlung`, Gate admin/buchhaltung/pflege),
DSGVO-Hinweis „nur Regalfotos, keine Bewohner". Vier Schritte (HITL):
1. **Labeling-Hilfe:** Foto hoch → `autoAnnotate` → Box-Vorschläge; Mensch korrigiert/bestätigt → sammelt in einem
   `LabelDataset` (Bild + bestätigte YOLO-Annotations). Outcome: wachsender Mandanten-Datensatz.
2. **Training:** ab N gelabelten Bildern → ZIP bauen → `train(zip, tenantRef)` → Job; `trainStatus`-Poll; bei
   `completed` neues `YoloModell` (aktiv) mit dem zurückgegebenen `model_path`.
3. **Zählen:** Regalfoto → `detect(foto, aktivesModell.model_path)` → Detektionen → über `ProductLabel` auf Artikel
   + Multiplier gemappt → **vorgeschlagene Stückzahl je Artikel**.
4. **Buchen:** berechtigte Person bestätigt die Zählung → schreibt als **`Inventurposition.ist_menge`** in eine
   offene Inventur (oder als Korrektur-Wareneingang). **Der Outcome-Anker** — die Zählung landet echt in der WaWi.

**Inbetriebnahme:** Solange kein trainiertes Modell existiert, liefert `detect` nur Basis-COCO-Klassen (schwach für
Heim-Artikel) → die Zähl-Buchung (Schritt 4) ist erst sinnvoll nach Training; bis dahin „gebaut & nutzbar für
Labeling/Training", Buchung pro Mandant frei schaltbar. Eintrag in `docs/INBETRIEBNAHME.md`.

## DSGVO

Nur Regal-/Lagerfotos + Artikel-/Label-Daten — **null Personendaten** (keine Bewohner/Mitarbeiter im Bild; UI-Hinweis).
Das Vision-MCP hält keine Mandantendaten außer den Gewichten; Fotos liegen tenant-scoped + signiert in opcare.

## Verifikation

- **vision-mcp:** pytest (StubBackend detect/auto_annotate, train-Smoke, Path-Traversal, Bearer-401). Lokaler
  `/health`-Check + ein `detect`-Call gegen ein Demo-Modell.
- **opcare:** `FakeVisionClient` (deterministische Detektionen) → Feature-Tests des Loops: autoAnnotate→Dataset,
  train→YoloModell, detect→ProductLabel-Mapping→Mengenvorschlag, Bestätigung→`Inventurposition.ist_menge`. Gate-403,
  tenant-scope, DSGVO-Hinweis. Volle Suite/PHPStan/Pint, Screenshot, Doku/Wiki/Memory.
- **Push-Gate:** `vision-mcp`-Repo-Anlegen + Pushes NUR nach User-OK (nicht auto-push).

## Folge-Inkremente

Reale GPU-Inbetriebnahme + Multi-Haus-Datensatz-Aggregation; Feinschliff Density-Counting für gestapelte Gleichteile.
