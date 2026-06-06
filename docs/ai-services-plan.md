# AI-Services-Plan: Ollama, whisperX-mcp & VLM-Beleg-Capture (Spezifikation)

**Stand 2026-06-06 — Planung, noch nicht implementiert.** Umsetzung in eigener Session.
Querbezug: [OFFENE-PUNKTE.md](OFFENE-PUNKTE.md), [INBETRIEBNAHME.md](INBETRIEBNAHME.md),
[ideen-backlog-2026-06.md](ideen-backlog-2026-06.md).

opcare nutzt bereits AI im **Speech-Modul** (`app/Domains/Speech/`): `WhisperMcpTranscriber`
(whisperx-mcp via MCP-Streamable-HTTP) + `OllamaTextOptimizer`/`OllamaStructurer` (Ollama `/api/generate`).
Dieser Plan (a) macht die beiden Dienste **prod-deploybar als Container**, (b) ergänzt **VLM-Beleg-Capture
als Backend-Logik** und (c) skizziert **Budget-Setzungen** für die Taschengeldkasse.

---

## 1. Ollama & whisperX-mcp als Dockerfiles mit Port-Healthcheck

### 1.1 Architektur-Invariante (CLAUDE.md, zwingend)

> **Ollama läuft NIE als GPU-Docker-Service auf dem GPU-losen Server (u-server).**
> Prod (`mcp.linn.games`) routet **intern** auf den GPU-Host im selben WLAN → `http://192.168.178.11:11434`
> (LAN ~1.6 ms). Dev/Laptop → `localhost:11434`. Modelle kommen aus dem `ModelRouter`
> (`config/model_routes.yaml`), **nicht** aus `OLLAMA_MODEL`-env (kein env-bleed).

Daraus folgt der Container-Charakter: **kein GPU-Workload im Image.** Die Dockerfiles sind **dünne
Client-/Gateway-Container**, deren erster `RUN`-Schritt (genauer: ein Build-ARG-gesteuerter Pre-Flight)
**einen Healthcheck gegen den Standard-Port des bereits laufenden Upstream-Dienstes** macht und den Build
abbricht, wenn der Upstream nicht erreichbar ist. So wird verhindert, dass ein Image gebaut/ausgerollt wird,
das gegen einen toten Backend-Endpoint zeigt.

### 1.2 Standard-Ports (Dev)

| Dienst | Dev-Port | Prod-Upstream | Quelle |
|---|---|---|---|
| **Ollama** | `localhost:11434` | `192.168.178.11:11434` (LAN-GPU-Host) | CLAUDE.md, `config/speech.php` `OLLAMA_URL` |
| **whisperX-mcp** | `localhost:8099` | eigener whisperx-mcp-Host (Bearer-Token) | User-Vorgabe |

> ⚠️ **Port-Kollision beachten:** Der Web-Container im `docker-compose.yml` nutzt `APP_PORT:-8099`.
> whisperX-Dev-Port 8099 kollidiert damit. Bei Umsetzung **einen von beiden umlegen** (z. B. App auf 8080-Bereich
> oder whisperX-Dev auf 8099 nur wenn die App woanders lauscht). Außerdem: `config/speech.php` `WHISPER_URL`
> hat heute Default `http://localhost:8000` — auf den vereinbarten Standard-Port angleichen.

### 1.3 Dockerfile-Muster (Healthcheck-im-Build)

Beide Dienste bekommen je ein Dockerfile unter `docker/ollama/Dockerfile` bzw. `docker/whisperx/Dockerfile`.
Gemeinsames Muster — der **erste effektive Build-Schritt prüft den Standard-Port**:

```dockerfile
# docker/ollama/Dockerfile  (Client-/Gateway-Container, KEIN GPU-Workload)
FROM curlimages/curl:latest AS preflight
# Erster RUN = Healthcheck gegen den Standard-Port des laufenden Upstream-Ollama.
# UPSTREAM kommt aus dem Compose-Build-ARG: Dev=host.docker.internal:11434,
# Prod=192.168.178.11:11434. Build bricht ab, wenn der Endpoint nicht antwortet.
ARG OLLAMA_UPSTREAM=http://host.docker.internal:11434
RUN curl -fsS --max-time 5 "${OLLAMA_UPSTREAM}/api/version" \
      || (echo "FEHLER: Ollama unter ${OLLAMA_UPSTREAM} nicht erreichbar" && exit 1)
# ... danach: schlanke Gateway-/Proxy-Schicht, Modellliste-Warmup o. Ä. (kein `ollama serve`)
```

```dockerfile
# docker/whisperx/Dockerfile
FROM curlimages/curl:latest AS preflight
ARG WHISPERX_UPSTREAM=http://host.docker.internal:8099
# whisperx-mcp ist ein MCP-Streamable-HTTP-Dienst (POST /mcp/). Healthcheck gegen /mcp/ bzw. /health.
RUN curl -fsS --max-time 5 "${WHISPERX_UPSTREAM}/health" \
      || curl -fsS --max-time 5 -X POST "${WHISPERX_UPSTREAM}/mcp/" \
      || (echo "FEHLER: whisperX unter ${WHISPERX_UPSTREAM} nicht erreichbar" && exit 1)
```

**Wichtig:** Das ist ein **Build-Time-Pre-Flight** (der vom User gewünschte „erste RUN-Befehl = Healthcheck").
Zusätzlich gehört in beide Images eine **Laufzeit-`HEALTHCHECK`**-Instruktion, damit Compose/Orchestrator
den Dienst als (un)gesund führt. Auf dem GPU-losen Server bleiben diese Container reine **Reachability-Gateways**;
das eigentliche Modell läuft auf dem GPU-Host. `host.docker.internal` per
`extra_hosts: ["host.docker.internal:host-gateway"]` im Compose verfügbar machen (Dev).

### 1.4 Konfigurations-Bezug & Cleanup

- `config/speech.php` `OLLAMA_URL`/`WHISPER_URL` bleiben die Single Source der Endpoints (env-getrieben).
- **`OLLAMA_MODEL`-env widerspricht der CLAUDE.md-Invariante** („Modelle aus `ModelRouter`, nicht env").
  Bei Umsetzung: Modellwahl über eine `config/model_routes.yaml`-Entsprechung (Task→Modell) statt fixer env —
  als eigener kleiner Cleanup im Speech-Modul. Bis dahin als Inbetriebnahme-Schalter festhalten.
- Beide Dienste sind **extern-gegatet** (Upstream muss laufen) → Eintrag in [INBETRIEBNAHME.md](INBETRIEBNAHME.md) §5/§1.

---

## 2. VLM-Beleg-Capture — nur Backend-Logik (kein Frontend)

**Ziel (User):** Die VLM-Funktionalität aus dem `beleg-capture`-Repo über ein **Ollama-VLM** (vision-fähiges
Modell, z. B. ein qwen-vl/llava-Derivat über `/api/generate` mit `images`) einbauen. **Nur die Logik:**

```
Foto hochladen → AI analysiert → AI schlägt vor, WO in der DB die Infos einsortiert werden könnten
              → Mensch bestätigt (nur, wenn die User-Rolle die Berechtigung hat)
```

### 2.1 Pipeline (rein serverseitig)

1. **Upload** über den vorhandenen `Masterdata\Services\AttachmentService` (Strang A — spatie-medialibrary,
   Disk via `opcare.media_disk` → MinIO, signierte Routen, Aufbewahrungsfrist). Kein neuer Upload-Pfad.
2. **VLM-Analyse**: neuer `App\Domains\Capture\Services\BelegAnalyzer` ruft Ollama-VLM (`/api/generate`,
   `images: [base64]`, `format: json`, `think:false`) mit einem **strengen Extraktions-Prompt**: liefere
   ausschließlich strukturiertes JSON (Belegtyp, Datum, Betrag, Lieferant, Positionen …). **Keine erfundenen
   Felder** (gleiche Härte wie `OllamaTextOptimizer`-Prompt: „ERFINDE KEINE Fakten").
3. **Mapping-Vorschlag**: ein `EinsortierungsVorschlag` ordnet die Extraktion einem **Ziel-Slot** in der DB zu
   (z. B. Buchhaltung-Beleg, Bewohner-Dokumentkategorie, Hilfsmittel-Stammdatum, Taschengeld-Transaktion).
   Der Vorschlag wird **persistiert mit `status = vorgeschlagen`** + Konfidenz + Roh-JSON (Nachvollziehbarkeit).
4. **Human-in-the-loop-Bestätigung**: ein Mensch bestätigt/korrigiert/verwirft. **Erst die Bestätigung schreibt
   den Zieldatensatz.** Die VLM-Ausgabe ist nie autoritativ → kein „stiller" Schreibvorgang (Projektregel:
   keine stummen Fehler, AI-Halluzination darf nichts unbemerkt anlegen).

### 2.2 Berechtigung (zwingend über `Befugnis`)

> „Human bestätigt (wenn Berechtigungen vorhanden in Userrole natürlich nur …)"

Die Bestätigung wird über den bestehenden `Personnel\Support\Befugnis`-Service gegated — analog zu SIS/Medikation/BtM:
- Neue Tätigkeit im `TaetigkeitDefaults`-Katalog, z. B. `beleg_einsortieren` (bzw. je Ziel-Slot differenziert,
  z. B. `taschengeld_buchen` braucht zusätzlich die Treuhand-Berechtigung).
- `Befugnis::darfKey($user, 'beleg_einsortieren')` als `abort_unless`-Guard im Bestätigungs-Endpunkt.
- VLM darf **vorschlagen** ohne Berechtigung (read/analyze); **schreiben** nur mit Berechtigung.

### 2.3 Datenmodell (Skizze, neue Domäne `Capture`)

- `BelegAnalyse` (media_id, roh_json, modell, konfidenz, erstellt_von, tenant via `BelongsToTenant`).
- `EinsortierungsVorschlag` (analyse_id, ziel_typ, ziel_felder json, status [vorgeschlagen|bestaetigt|verworfen],
  bestaetigt_von, bestaetigt_am). Append-only-Charakter für die Audit-Spur (LogsActivity).
- Schreibt bei Bestätigung in den jeweiligen Ziel-Datensatz (Buchhaltung/Taschengeld/Bewohner-Doku).

### 2.4 Wiederverwendung
Nutzt die vorhandenen Bau-Muster: **Dokument-mit-Upload** (Strang A), **Genehmigungs-Workflow**
(Vorschlag→Bestätigung) und **Befugnis-Gate**. Kein Frontend in dieser Iteration — UI später.

---

## 3. Budget-Setzungen (u. a. Taschengeldkasse)

**Idee (User):** „budgetsetzungen könnten z. B. auch bei der Taschengeldkasse sinnvoll sein."

Bezug: die noch nicht gebaute **Taschengeld-/Barbetragsverwaltung (§ 27b SGB XII, Treuhand)** —
[ideen-backlog-2026-06.md #3](ideen-backlog-2026-06.md) / Audit-Lücke #6.

- **Budget = editierbarer Wert je Bewohner-Treuhandkonto** (Kategorie + Zeitraum, z. B. „Friseur 30 €/Monat",
  „Gesamt-Auszahlung 100 €/Monat"). Datengetriebener Katalog (Norm-als-Daten-Muster), je Einrichtung/Bewohner.
- **Warn-/Sperr-Ampel**: bei Überschreitung des gesetzten Budgets warnen (weich) bzw. Auszahlung sperren (hart),
  konfigurierbar. Greift im Transaktionsjournal der Taschengeldkasse.
- **Bezug VLM-Capture**: ein per Foto erfasster Friseur-Beleg → Vorschlag „Taschengeld-Auszahlung Bewohner X" →
  Budget-Prüfung → berechtigte Bestätigung bucht. Verzahnt §2 und §3.
- **Recht/Trennung**: Treuhand getrennt vom Einrichtungsvermögen, Einzelbelegpflicht, prüfbar durch Heimaufsicht
  (GoB) — Budget-Setzung ist ein **internes Steuerungs-/Schutzinstrument**, ersetzt keine gesetzliche Pflicht.

Budget-Setzungen sind **generisch** gedacht (wie Delegation/Beauftragte): dasselbe Muster ist später auch für
Sachkosten/Wirtschaftsbudgets der Buchhaltung wiederverwendbar.

---

## 4. Reihenfolge (Vorschlag für die Umsetzungs-Session)

1. **Dienste-Container** (§1): Dockerfiles + Build-Pre-Flight-Healthcheck, Compose-Verdrahtung (`extra_hosts`),
   INBETRIEBNAHME-Einträge, `WHISPER_URL`/Port-Kollision/`OLLAMA_MODEL`→ModelRouter-Cleanup.
2. **VLM-Capture-Backend** (§2): `Capture`-Domäne, `BelegAnalyzer` (Ollama-VLM), Vorschlag-Modell,
   Befugnis-Tätigkeit + Guard, Tests (Fake-VLM-Adapter analog `SPEECH_FAKE`, damit dev/test ohne GPU grün).
3. **Taschengeld + Budget** (§3): Treuhandkonto + Journal (Audit-Lücke #6) **mit** Budget-Setzungen, dann
   Capture→Taschengeld-Verzahnung.

Jede Stufe: Doku + Wiki + Screenshot, Pint + PHPStan + Pest grün vor Push (Projektregeln).
