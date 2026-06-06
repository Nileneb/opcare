# Dev-AI-Dienste: Ollama & whisperX-mcp

Diese Dateien betreiben die beiden AI-Upstreams des Speech-Moduls (`config/speech.php`) **lokal auf dem
GPU-Dev-Rechner**. Sie sind **nicht** Teil des prod `docker compose up` (siehe `../../docker-compose.yml`):
auf dem GPU-losen Prod-Server läuft Ollama nie als Docker-Service (CLAUDE.md-Invariante), dort zeigt die App
per `OLLAMA_URL`/`WHISPER_URL` auf den GPU-Host bzw. den whisperX-Endpoint.

## Grundsatz: erst prüfen, dann (vielleicht) bauen

In der Regel laufen Ollama (`localhost:11434`) und whisperX-mcp (`localhost:8000`) hier bereits als Prozess
oder eigener Container. Das Orchestrierungs-Skript prüft **zuerst die Erreichbarkeit** und baut Container
**nur auf ausdrückliches `up`** — und auch dann nur für die nicht erreichbaren Dienste.

```bash
scripts/ai-services.sh check          # Erreichbarkeit + Diagnose (Default, baut NICHTS)
scripts/ai-services.sh up             # nur fehlende Dienste bauen/starten
scripts/ai-services.sh up whisperx    # gezielt einen Dienst (ollama|whisperx)
scripts/ai-services.sh down           # opcare-ai-Container stoppen
scripts/ai-services.sh logs           # Container-Logs
```

`check` meldet bei Nichterreichbarkeit zuerst eine **Diagnose** (hört der Port? läuft der Prozess?) statt
sofort zu bauen — wie vorgegeben: „prüfe deinen Aufruf und die Ports, fang nicht gleich an zu bauen".

## GPU / CPU

`up` baut **mit GPU**, wenn `nvidia-container-toolkit` (Binary `nvidia-ctk`) **und** eine CDI-Spec
(`/etc/cdi/nvidia.yaml` oder `/var/run/cdi/nvidia.yaml`) vorhanden sind — über das Override
`docker-compose.ai.gpu.yml` (CDI, `nvidia.com/gpu=all`). Fehlt das Toolkit, fällt es automatisch auf den
**CPU-Build** der Basis zurück (whisperX `DEVICE=cpu`/`COMPUTE_TYPE=int8`) — kein harter Fehler.

CDI-Test: `docker run --rm --device nvidia.com/gpu=all ubuntu nvidia-smi`. Toolkit-Installation: siehe
README im `whisperx-mcp`-Repo.

## Konfiguration (env, optional)

| Variable | Default | Zweck |
|---|---|---|
| `WHISPERX_CONTEXT` | `../../../whisperx-mcp` | Build-Kontext des whisperX-mcp-Repos (Schwester-Repo) |
| `HF_TOKEN` | — | Hugging-Face-Token (pyannote-Diarisierung) |
| `WHISPER_TOKEN` | — | Bearer-Token, schützt `/mcp/` (= `API_TOKEN` im whisperX-Container) |
| `OLLAMA_PORT` / `WHISPER_PORT` | `11434` / `8000` | Host-Ports |

Die Endpoints selbst pflegt die App in `config/speech.php` (`OLLAMA_URL`, `WHISPER_URL`) — Single Source.
Modelle kommen über den ModelRouter, nicht aus `OLLAMA_MODEL`-env.
