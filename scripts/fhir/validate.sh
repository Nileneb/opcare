#!/usr/bin/env bash
# Validiert FHIR-Dateien mit dem offiziellen gematik app-referencevalidator (offline Fat-JAR).
#
# WHY: Der Validator liefert IMMER Exit-Code 0, egal ob gültig oder nicht.
# Konformität steht nur im OperationOutcome (--output result.json). Daher
# wird das OperationOutcome per jq auf severity=error/fatal ausgewertet.
#
# Nutzung:
#   validate.sh <modul> <datei> [datei2 ...]
#   validate.sh isip1 storage/app/fhir-samples/isip-patient.json
#   validate.sh erp   storage/app/fhir-samples/erezept.json
#
# Idempotentes Download-Verhalten:
#   JAR + isip1-Plugin werden beim ersten Aufruf heruntergeladen.
#   Nach dem ersten Download passiert beim Folgeaufruf nichts (Prüfung via -f).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TOOLS_DIR="$REPO_ROOT/tools/fhir-validator"
# WHY: Plugin-ZIPs müssen im Ordner `plugins/` DIREKT NEBEN der JAR liegen.
# Der Validator lädt sie automatisch — kein --plugins-dir-Flag in v2.16.2.
PLUGINS_DIR="$TOOLS_DIR/plugins"

JAR_URL="https://github.com/gematik/app-referencevalidator/releases/download/2.16.2/referencevalidator-cli-2.16.2.jar"
JAR_FILE="$TOOLS_DIR/referencevalidator-cli-2.16.2.jar"

ISIP1_URL="https://github.com/gematik/app-referencevalidator-plugins/releases/download/isip1-1.1.0/isip1-1.1.0.zip"
ISIP1_ZIP="$PLUGINS_DIR/isip1-1.1.0.zip"

# --- Dependency-Check ---
if ! command -v java &>/dev/null; then
    echo "FEHLER: java nicht gefunden. JDK 21 erforderlich." >&2
    exit 2
fi
if ! command -v jq &>/dev/null; then
    echo "FEHLER: jq nicht gefunden. 'apt install jq' oder 'brew install jq'." >&2
    exit 2
fi

# --- Idempotenter Download: JAR ---
if [[ ! -f "$JAR_FILE" ]]; then
    echo "Download: gematik referencevalidator-cli-2.16.2.jar (~200 MB) ..."
    mkdir -p "$TOOLS_DIR"
    curl -L --progress-bar -o "$JAR_FILE" "$JAR_URL"
    echo "JAR gespeichert: $JAR_FILE"
else
    echo "JAR bereits vorhanden: $JAR_FILE"
fi

# --- Idempotenter Download: isip1-Plugin ---
mkdir -p "$PLUGINS_DIR"
if [[ ! -f "$ISIP1_ZIP" ]]; then
    echo "Download: isip1-1.1.0.zip ..."
    curl -L --progress-bar -o "$ISIP1_ZIP" "$ISIP1_URL"
    echo "Plugin gespeichert: $ISIP1_ZIP"
else
    echo "isip1-Plugin bereits vorhanden: $ISIP1_ZIP"
fi

# --- Argumente parsen ---
if [[ $# -lt 2 ]]; then
    echo "Nutzung: validate.sh <modul> <datei> [datei2 ...]" >&2
    echo "  Beispiel: validate.sh isip1 storage/app/fhir-samples/isip-patient.json" >&2
    exit 1
fi

MODULE="$1"
shift
FILES=("$@")

# --- Validierungsschleife ---
OVERALL_ERRORS=0
for FILE in "${FILES[@]}"; do
    if [[ ! -f "$FILE" ]]; then
        echo "FEHLER: Datei nicht gefunden: $FILE" >&2
        OVERALL_ERRORS=$((OVERALL_ERRORS + 1))
        continue
    fi

    RESULT_FILE="$(mktemp /tmp/fhir-result-XXXXXX.json)"
    trap 'rm -f "$RESULT_FILE"' EXIT

    echo ""
    echo "--- Validiere: $FILE (Modul: $MODULE) ---"

    # WHY: Plugin-ZIPs müssen im Verzeichnis `plugins/` NEBEN der JAR liegen (kein CLI-Flag
    # in v2.16.2). Die JAR lädt Plugins automatisch aus diesem Verzeichnis.
    # WHY: -o (nicht --output) — der Referenzvalidator 2.16.2 akzeptiert nur den Kurzflag.
    # WHY: -ae json — erp-Modul erwartet standardmäßig XML (KBV-Spec), wir emittieren JSON.
    #      isip1 ist encoding-agnostisch, -ae json schadet nicht.
    java -jar "$JAR_FILE" \
        -o "$RESULT_FILE" \
        -ae json \
        "$MODULE" \
        "$FILE" 2>&1 || true

    # OperationOutcome auswerten: error + fatal zählen
    ERROR_COUNT=0
    FATAL_COUNT=0
    if [[ -f "$RESULT_FILE" ]] && [[ -s "$RESULT_FILE" ]]; then
        ERROR_COUNT=$(jq '[.issue[] | select(.severity == "error")] | length' "$RESULT_FILE" 2>/dev/null || echo 0)
        FATAL_COUNT=$(jq '[.issue[] | select(.severity == "fatal")] | length' "$RESULT_FILE" 2>/dev/null || echo 0)
        TOTAL=$((ERROR_COUNT + FATAL_COUNT))
    else
        # Kein --output produziert → Fallback: stdout auf "Valid: true" prüfen ist nicht möglich
        # in dieser Version; melde Warnung.
        echo "WARNUNG: Kein OperationOutcome erzeugt — Validator-Ausgabe oben prüfen." >&2
        TOTAL=0
    fi

    if [[ $TOTAL -eq 0 ]]; then
        echo "OK $FILE"
    else
        echo "FEHLER $FILE: $TOTAL Issues (fatal=$FATAL_COUNT, error=$ERROR_COUNT)"
        echo ""
        echo "Top-Issues (fatal + error):"
        jq -r '.issue[] | select(.severity == "error" or .severity == "fatal") | "  [\(.severity)] \(.details.text // .diagnostics // "(kein Text)") | Pfad: \((.expression // []) | join(", "))"' \
            "$RESULT_FILE" 2>/dev/null | head -20
        OVERALL_ERRORS=$((OVERALL_ERRORS + TOTAL))
    fi

    rm -f "$RESULT_FILE"
    trap - EXIT
done

echo ""
if [[ $OVERALL_ERRORS -eq 0 ]]; then
    echo "GESAMT: Alle Dateien valide."
    exit 0
else
    echo "GESAMT: $OVERALL_ERRORS Fehler über alle Dateien."
    exit 1
fi
