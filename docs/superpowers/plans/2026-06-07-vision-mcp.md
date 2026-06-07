# Vision-MCP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. TDD je Task.

**Goal:** Gestripptes Vision-MCP (`detect`/`auto_annotate`/`train`/`train_status`) als eigenes Repo wie whisperx-mcp + opcare-Anbindung (Foto→Labeling→Train→Zählung→`Inventurposition.ist_menge`).

**Architecture:** Repo `~/Desktop/WebDev/vision-mcp` (Python, MCP-SDK+Starlette:8000, kopiert ORM-freie stockpilot-Module). opcare = System of Record (Client + ProductLabel-Mapping + Loop-UI). Zustandsarm: nur `.pt`-Gewichte je Mandant.

**Spec:** `docs/superpowers/specs/2026-06-07-vision-mcp-design.md`. Quellen: `~/Desktop/WebDev/stockpilot/apps/{vision/inference.py,training/suggestions.py,training/tasks.py}`, Muster `~/Desktop/WebDev/whisperx-mcp/{app/server.py,Dockerfile,config.py}`.

**Push:** `vision-mcp` (Nileneb/vision-mcp, freigegeben) am Ende pushen; opcare `--no-ff` merge. GH-Repo existiert bereits (private).

---

## Task 1 (vision-mcp): Repo-Skelett + detect-Tool

**Files (in `~/Desktop/WebDev/vision-mcp`):** `app/server.py`, `app/config.py`, `vision/inference.py` (kopiert aus stockpilot, `_resolve_path` raus), `vision/detect.py` (neu), `requirements.txt`, `pytest.ini`, `tests/test_detect.py`.

**Contract:** Spiegele `whisperx-mcp/app/server.py` (MCP low-level `Server` + Starlette `Mount('/mcp', StreamableHTTPSessionManager(stateless=True))` + `BearerAuthMiddleware` (`API_TOKEN`-Env, schützt nur `/mcp*`) + `GET /health`). `config.py`: `API_TOKEN`, `MODEL_DIR=/models`, `DEFAULT_DETECT_MODEL`. Aus stockpilot `apps/vision/inference.py` übernehmen: `DetectionResult`, `StubBackend`, `UltralyticsBackend.detect()`, `aggregate_by_label` — `_resolve_path()` ENTFERNEN, `detect()` nimmt `model_path` direkt. `vision/detect.py`: `detect_image(image_bytes, model_path, conf=0.25) -> list[DetectionResult]` (Backend per Env: `StubBackend` wenn `VISION_FAKE=1`, sonst Ultralytics). **Tool `detect`** im server: `{image_base64, model_path, confidence?, filename?}` → `{detections, counts, model_used, processing_time_seconds}`; `anyio.to_thread.run_sync` + GPU-Lock; **Path-Traversal-Schutz** (`model_path` muss unter `MODEL_DIR` liegen — sonst Error).

**Tests:** `VISION_FAKE=1`; `detect` über den Tool-Handler (oder `detect_image` direkt) → deterministische StubBackend-Detektionen + korrekte `counts`-Aggregation; Path-Traversal (`model_path=/etc/passwd`) → Error; `/health` 200; `/mcp` ohne Bearer → 401.

**Commit:** `feat: MCP-Skelett + detect-Tool (StubBackend, Bearer, Health)`.

---

## Task 2 (vision-mcp): auto_annotate + train + Dockerfile

**Files:** `training/suggest.py` (kopiert aus stockpilot `apps/training/suggestions.py`, `settings`→`os.environ`), `training/train.py` (neu, aus `tasks.py` extrahiert), `app/server.py` (3 Tools ergänzen), `Dockerfile`, `README.md`, `tests/test_suggest.py`, `tests/test_train.py`.

**Contract:**
- Aus `suggestions.py` übernehmen: `Suggestion`, `_iou`, `merge`, `run_yolo`, `run_sam`, `generate_for_image_path` (Env statt settings). **Tool `auto_annotate`**: `{image_base64, use_sam?, yolo_model?, sam_model?}` → `{suggestions, processing_time_seconds}`.
- `train.py` (aus `tasks.py` ohne Celery/ORM/django-tenants): `materialize_dataset(annotations, root)`, `train_sync(dataset_dir, tenant_id, base_model, epochs, batch, imgsz) -> dict(model_path, class_names, metrics)`, `register_weights(best_pt, tenant_id) -> path` (`shutil.copy2` nach `MODEL_DIR/{tenant_id}/job{n}_v{m}.pt` + `MODEL_DIR/{tenant_id}/active` schreiben), `_extract_metrics`, `_class_names_from_yaml`. **Tools `train`** (`{dataset_zip_base64, tenant_id, base_model?, epochs?, batch_size?, image_size?}` → sofort `{job_id, status:'running'}`; läuft im Threadpool, schreibt Job-State in ein in-memory Dict) + **`train_status`** (`{job_id}` → `{status, model_path?, class_names?, metrics?, error?}`).
- Dockerfile: Base `nvidia/cuda:12.4.1-cudnn-runtime-ubuntu22.04`, `opencv-python-headless`, ultralytics (cu124-Torch deterministisch), `CMD uvicorn app.server:app --host 0.0.0.0 --port 8000`. `requirements.txt` minimal (mcp, starlette, uvicorn, ultralytics, anyio, opencv-headless).

**Tests:** `auto_annotate` (StubBackend/gemockt → Suggestions, IoU-Dedup); `train` mit Mini-Dataset ODER gemocktem `train_sync` → job_id, dann `train_status` → completed + model_path geschrieben.

**Commit:** `feat: auto_annotate + train(async)/train_status + Dockerfile`.

---

## Task 3 (opcare): Client + Domäne + Dienst-Orchestrierung

**Files:** `app/Domains/Vision/Contracts/VisionClient.php` + `Services/HttpVisionClient.php` + `Testing/FakeVisionClient.php`; `config/vision.php` (`url`, `token`, `fake`); `app/Domains/Vision/Models/{YoloModell,ProductLabel,RegalAufnahme,RegalDetection}.php` + Migrations; `docker/ai-services/docker-compose.ai.yml`(+`.gpu.yml`) + `scripts/ai-services.sh` (vision-mcp ergänzen, Port 8001); `MediaDownloadController` (RegalAufnahme-Owner). Tests `tests/Feature/Vision/`.

**Contract:** `VisionClient`: `detect(string $imageB64, string $modelPath, float $conf=0.25): array`, `autoAnnotate(string $imageB64, bool $useSam=true): array`, `train(string $zipB64, string $tenantRef, array $opts=[]): string`, `trainStatus(string $jobId): array`. `HttpVisionClient` → `Http::withToken(config('vision.token'))->post(config('vision.url').'/mcp/', ['jsonrpc'=>'2.0','method'=>'tools/call','params'=>['name'=>..., 'arguments'=>...],'id'=>1])` und parst das Ergebnis. `FakeVisionClient` deterministisch. Binding `config('vision.fake')`-gegated. Modelle: `YoloModell` (tenant_id, model_path, version, aktiv, class_names json, metrics json), `ProductLabel` (tenant_id, yolo_label, artikel_id FK, multiplier decimal default 1), `RegalAufnahme` (BaseModel HasMedia `foto`), `RegalDetection` (BaseModel: aufnahme_id, label, confidence, artikel_id nullable, menge_vorschlag). ide-helper.

**Tests:** `FakeVisionClient` liefert Detektionen; `ProductLabel`-Mapping (yolo_label→artikel×multiplier) rechnet Mengenvorschlag; Media-Download 200/403; tenant-scope.

**Commit:** `feat(vision): VisionMcpClient + Domäne (YoloModell/ProductLabel/RegalAufnahme) + ai-services-Eintrag`.

---

## Task 4 (opcare): Loop-UI + Inventur-Buchung

**Files:** `app/Livewire/Vision/Regalzaehlung.php` + View; `routes/web.php` (`/regalzaehlung`); `layouts/app.blade.php` (Nav); `docs/INBETRIEBNAHME.md` (Schalter). Test `tests/Feature/Vision/RegalzaehlungTest.php`.

**Contract:** Gate admin/buchhaltung/pflege. Vier HITL-Schritte in einer Seite (Tabs/Abschnitte): (1) **Labeling** — Foto hoch → `autoAnnotate` → Box-Vorschläge bestätigen → `LabelDataset`-Sammlung; (2) **Train** — ab N Bildern ZIP → `train` → `trainStatus`-Poll → neues `YoloModell` aktiv; (3) **Zählen** — Regalfoto → `detect(aktivesModell.model_path)` → über `ProductLabel` auf Artikel×Multiplier → Mengenvorschlag je Artikel; (4) **Buchen** — berechtigte Person bestätigt → schreibt `Inventurposition.ist_menge` in eine offene Inventur (oder Korrektur-Wareneingang). DSGVO-Hinweis „nur Regalfotos". Schritt 4 hinter Inbetriebnahme-Schalter wenn kein trainiertes Modell.

**Tests:** Gate 403; `FakeVisionClient`: autoAnnotate→Dataset, detect→Mengenvorschlag, Bestätigung→`Inventurposition.ist_menge` gesetzt (offene Inventur vorab via InventurStarten); tenant-scope; DSGVO-Hinweis sichtbar.

**Commit:** `feat(vision): Regalzählung-Loop-UI (Foto→Zählung→Inventur-ist_menge)`.

---

## Abschluss
- vision-mcp: `git init` + Commits + (nach lokalem Test) Push nach Nileneb/vision-mcp. README + lokaler `/health`-Smoke.
- opcare: DemoSeeder (ProductLabel + eine RegalAufnahme mit Detektionen), `migrate:fresh --seed`, Screenshot, README-Zähler, `docs/vision-regalzaehlung.md`, `docs/INBETRIEBNAHME.md`, Wiki, Memory.
- Opus-Final-Review (beide Repos: Path-Traversal/Bearer im MCP; tenant-scope/DSGVO/Outcome-Anker in opcare), Fixes, `--no-ff` Merge opcare + vision-mcp Push.
