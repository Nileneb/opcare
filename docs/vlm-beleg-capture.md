# VLM-Beleg-Capture

Belegfoto hochladen → ein vision-fähiges Ollama-Modell (VLM) liest die Daten aus → opcare schlägt vor, **wo** der
Beleg einsortiert werden könnte → ein:e Berechtigte:r bestätigt, **erst dann** wird gebucht. Die KI-Ausgabe ist nie
autoritativ (AI-Services-Plan §2).

## Pipeline (serverseitig, Human-in-the-loop)

1. **Upload** — Belegfoto über das Finanz-Livewire; das Original liegt als Media (Collection `beleg`, Disk via
   `opcare.media_disk` → MinIO-fähig) am `BelegAnalyse`-Datensatz (Audit-Spur).
2. **VLM-Analyse** — `OllamaBelegAnalyzer` ruft Ollama `/api/generate` mit `images:[base64]`, `format:json` und
   einem **strengen Extraktions-Prompt** (Belegtyp, Datum, Betrag, Lieferant, Positionen). Härte wie beim
   `OllamaTextOptimizer`: **„ERFINDE KEINE Fakten"**, fehlende Angaben bleiben `null`. Das Roh-JSON wird persistiert.
3. **Einsortierungs-Vorschlag** — heuristisches Mapping auf einen `ZielTyp`: mit positivem Betrag →
   `buchhaltung_beleg` (buchbar), sonst `unklar` (kein geratenes Ziel). Status `vorgeschlagen` + Konfidenz.
4. **Bestätigung** — ein Mensch bestätigt (oder verwirft). Erst die Bestätigung schreibt den Zieldatensatz: für
   `buchhaltung_beleg` eine **Buchung** über die bestehende `Buchen`-Action (Betrag/Datum vorbelegt, Konten wählt
   der Mensch). Der Vorschlag verlinkt die erzeugte Buchung.

## Berechtigung

Der **Schreibvorgang ist eine Finanzbuchung** — daher rollen-gegated wie das Buchhaltungs-Livewire
(`admin`/`buchhaltung`/`super-admin`). Bewusste Abweichung vom Plan-Vorschlag (`Befugnis::darfKey`): der
`Befugnis`-Service modelliert **pflegerische** Qualifikations-Vorbehalte (§ 4 PflBG, Delegation), nicht
administrative Finanzrechte — eine Rollen-Gate ist hier korrekt und konsistent mit der bestehenden Buchhaltung.
Analysieren/Vorschläge-Sehen ist ebenfalls auf die Finanzrolle beschränkt (Belegdaten).

## „Keine stillen Schreibvorgänge"

Eine VLM-Halluzination kann nichts unbemerkt anlegen: ohne Bestätigung bleibt der Vorschlag `vorgeschlagen`, nichts
wird gebucht. Roh-JSON + Konfidenz + Original-Media bleiben zur Nachvollziehbarkeit erhalten.

## Dev/Test ohne GPU

`SPEECH_FAKE=true` bindet den `FakeBelegAnalyzer` (deterministisch, analog `FakeSisStructurer`) — die ganze
Pipeline ist ohne GPU/Modell test- und demobar.

## Datenmodell

Domäne `Capture`: `BelegAnalyse` (HasMedia, roh_json, Konfidenz) + `EinsortierungsVorschlag` (ziel_typ, ziel_felder,
status, buchung_id, entschieden_von/_am). Livewire `App\Livewire\Capture\Belegerfassung` (Route `belegerfassung`).
VLM-Modell: `config('speech.capture.model')` (`CAPTURE_VLM_MODEL`, Default `qwen2.5vl`). Plan: `docs/ai-services-plan.md` §2.
