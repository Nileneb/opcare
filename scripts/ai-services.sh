#!/usr/bin/env bash
#
# AI-Dienste (Ollama + whisperX-mcp) auf dem GPU-Dev-Rechner.
#
# Grundsatz (User-Vorgabe): Beide Dienste laufen hier in der Regel als lokale Prozesse/Container
# auf localhost. Dieses Skript prüft ZUERST die Erreichbarkeit. Container werden NUR auf
# ausdrückliches `up` gebaut — und auch dann nur für die NICHT erreichbaren Dienste. Ein laufender
# Dienst wird nie durch einen kollidierenden Container überbaut.
#
#   scripts/ai-services.sh check          Erreichbarkeit prüfen + Diagnose (Default, baut NICHTS)
#   scripts/ai-services.sh up [dienst…]   nur fehlende Dienste bauen/starten (GPU wenn möglich, sonst CPU)
#   scripts/ai-services.sh down           die opcare-ai-Container stoppen
#   scripts/ai-services.sh logs [dienst…] Container-Logs
#
# dienst ∈ {ollama, whisperx, vision}  (whisperx == whisperx-mcp, vision == vision-mcp)
set -euo pipefail

OLLAMA_URL="${OLLAMA_URL:-http://localhost:11434}"
WHISPER_URL="${WHISPER_URL:-http://localhost:8000}"
VISION_URL="${VISION_URL:-http://localhost:8001}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AI_DIR="$SCRIPT_DIR/../docker/ai-services"
COMPOSE_BASE="$AI_DIR/docker-compose.ai.yml"
COMPOSE_GPU="$AI_DIR/docker-compose.ai.gpu.yml"
PROJECT="opcare-ai"

port_of() { local p="${1##*:}"; printf '%s' "${p%%/*}"; }

probe_ollama()  { curl -fsS --max-time 4 "$OLLAMA_URL/api/version" >/dev/null 2>&1; }
# /health meldet ready:true, sobald der Dienst lauscht (Modell ggf. idle aus dem VRAM entladen).
probe_whisper() { curl -fsS --max-time 4 "$WHISPER_URL/health" >/dev/null 2>&1; }
probe_vision()  { curl -fsS --max-time 4 "$VISION_URL/health" >/dev/null 2>&1; }

# GPU nutzbar? nvidia-container-toolkit + eine CDI-Spec müssen vorhanden sein.
gpu_available() {
  command -v nvidia-ctk >/dev/null 2>&1 || return 1
  [ -f /etc/cdi/nvidia.yaml ] || [ -f /var/run/cdi/nvidia.yaml ] || return 1
  return 0
}

compose_files() {
  if gpu_available; then
    printf -- '-f\n%s\n-f\n%s\n' "$COMPOSE_BASE" "$COMPOSE_GPU"
  else
    printf -- '-f\n%s\n' "$COMPOSE_BASE"
  fi
}

diagnose() {
  local name="$1" url="$2" port
  port="$(port_of "$url")"
  echo "    geprüft: $url"
  if command -v ss >/dev/null 2>&1 && ss -ltnH 2>/dev/null | grep -q ":${port}\b"; then
    echo "    → Port ${port} hört, aber der Health-Endpoint antwortet nicht."
    echo "      Prüfe URL/Pfad/Token oder ob ${name} noch lädt (Modelle?). NICHT bauen."
  else
    echo "    → Auf Port ${port} hört nichts. Läuft ${name} überhaupt?"
    echo "      Erst den lokalen Prozess/Container prüfen. Wenn ${name} wirklich fehlt:"
    echo "      scripts/ai-services.sh up ${name}"
  fi
  if docker compose -p "$PROJECT" ps --status running 2>/dev/null | grep -q "$PROJECT"; then
    echo "    → opcare-ai-Container laufen bereits: 'scripts/ai-services.sh logs'."
  fi
}

cmd_check() {
  local rc=0
  if probe_ollama; then echo "✓ Ollama erreichbar       ($OLLAMA_URL)"
  else echo "✗ Ollama NICHT erreichbar ($OLLAMA_URL)"; diagnose ollama "$OLLAMA_URL"; rc=1; fi
  if probe_whisper; then echo "✓ whisperX-mcp erreichbar ($WHISPER_URL)"
  else echo "✗ whisperX-mcp NICHT erreichbar ($WHISPER_URL)"; diagnose whisperx "$WHISPER_URL"; rc=1; fi
  if probe_vision; then echo "✓ vision-mcp erreichbar   ($VISION_URL)"
  else echo "✗ vision-mcp NICHT erreichbar ($VISION_URL)"; diagnose vision "$VISION_URL"; rc=1; fi
  if [ "$rc" -ne 0 ]; then
    echo
    echo "Hinweis: erst Aufruf/Port/Prozess prüfen — nicht vorschnell Container bauen."
  fi
  return "$rc"
}

# normalisiert ein Alias auf den Compose-Service-Namen
service_name() { case "$1" in whisperx|whisperx-mcp) echo whisperx-mcp ;; ollama) echo ollama ;; vision|vision-mcp) echo vision-mcp ;; *) return 1 ;; esac; }

reachable() { case "$1" in ollama) probe_ollama ;; whisperx-mcp) probe_whisper ;; vision-mcp) probe_vision ;; esac; }

cmd_up() {
  local requested=("$@") targets=() svc
  if [ "${#requested[@]}" -eq 0 ]; then
    probe_ollama  || targets+=(ollama)
    probe_whisper || targets+=(whisperx-mcp)
    probe_vision  || targets+=(vision-mcp)
    if [ "${#targets[@]}" -eq 0 ]; then
      echo "Alle Dienste laufen bereits — nichts zu bauen."
      return 0
    fi
  else
    for arg in "${requested[@]}"; do
      svc="$(service_name "$arg")" || { echo "Unbekannter Dienst: $arg (erlaubt: ollama, whisperx)"; exit 2; }
      if reachable "$svc"; then
        echo "↷ $svc läuft bereits auf localhost — überspringe (ein Container würde auf dem Port kollidieren)."
      else
        targets+=("$svc")
      fi
    done
    [ "${#targets[@]}" -eq 0 ] && { echo "Nichts zu bauen."; return 0; }
  fi

  if gpu_available; then
    echo "GPU: nvidia-container-toolkit + CDI-Spec erkannt → GPU-Build (CDI)."
  else
    echo "GPU: kein nvidia-container-toolkit/CDI → CPU-Build (whisperX DEVICE=cpu/int8)."
  fi
  echo "Baue/starte: ${targets[*]}"

  mapfile -t cf < <(compose_files)
  docker compose -p "$PROJECT" "${cf[@]}" up -d --build "${targets[@]}"
}

cmd_down() { mapfile -t cf < <(compose_files); docker compose -p "$PROJECT" "${cf[@]}" down; }
cmd_logs() { mapfile -t cf < <(compose_files); docker compose -p "$PROJECT" "${cf[@]}" logs -f "$@"; }

case "${1:-check}" in
  check) cmd_check ;;
  up)    shift; cmd_up "$@" ;;
  down)  cmd_down ;;
  logs)  shift; cmd_logs "$@" ;;
  -h|--help|help)
    sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//' ;;
  *) echo "Unbekanntes Kommando: $1" >&2; echo "Nutzung: scripts/ai-services.sh {check|up|down|logs}" >&2; exit 2 ;;
esac
