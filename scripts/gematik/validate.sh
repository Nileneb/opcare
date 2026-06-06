#!/usr/bin/env bash
# Validiert eine FHIR-Datei mit dem offiziellen gematik Referenzvalidator.
# WHY: Der Validator liefert IMMER Exit-Code 0 — Konformität steht nur im Output ("Valid: true|false").
# Daher wird die Ausgabe geparst statt auf den Exit-Code vertraut.
# REFVALIDATOR_JAR muss gesetzt sein; das Plugin liegt im Ordner `plugins/` neben der jar.
set -euo pipefail

JAR="${REFVALIDATOR_JAR:?REFVALIDATOR_JAR (Pfad zur referencevalidator-cli.jar) muss gesetzt sein}"
MODULE="${1:?Modul fehlt (z. B. isip1)}"
FILE="${2:?Datei fehlt}"

out="$(java -jar "$JAR" "$MODULE" "$FILE" 2>&1)"
echo "$out"

if echo "$out" | grep -q "Valid: true"; then
  echo "✓ $FILE ist konform (Modul $MODULE, gematik Referenzvalidator)"
else
  echo "::error::ISiP/gematik-Konformität fehlgeschlagen für $FILE (Modul $MODULE)"
  exit 1
fi
