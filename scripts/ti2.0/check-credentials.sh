#!/usr/bin/env bash
# Diagnose-Skript: prüft, ob TI-2.0-Credentials gesetzt und dekodierbar sind.
# Idempotent, exit 0 — kein Gate, nur sichtbare Diagnose.
# Aufruf: bash scripts/ti2.0/check-credentials.sh
set -uo pipefail

echo "=== TI 2.0 Credential-Check ==="

# --- SMC-B P12 ---
if [[ -z "${TI20_SMCB_P12_BASE64:-}" ]]; then
    echo "[FEHLT]  TI20_SMCB_P12_BASE64 ist nicht gesetzt."
    echo "         → Mock-Modus aktiv (TI20_ZETA_MOCK_AUTH=true Default)."
    echo "         → Runbook: docs/ti2.0/ti2.0-auth-inbetriebnahme.md"
    SMCB_OK=false
else
    echo "[OK]     TI20_SMCB_P12_BASE64 gesetzt (${#TI20_SMCB_P12_BASE64} Zeichen base64)."

    # Trockenlauf: base64 → openssl pkcs12 -info (kein Write, nur Parse-Check)
    if command -v openssl >/dev/null 2>&1; then
        TMPFILE=$(mktemp /tmp/smcb_check_XXXXXX.p12)
        # WHY: base64 -d ist GNU; macOS braucht base64 -D — beide probieren
        if echo "$TI20_SMCB_P12_BASE64" | base64 -d >"$TMPFILE" 2>/dev/null \
            || echo "$TI20_SMCB_P12_BASE64" | base64 -D >"$TMPFILE" 2>/dev/null; then

            PASS="${TI20_SMCB_P12_PASSWORD:-}"
            if openssl pkcs12 -info -in "$TMPFILE" -passin "pass:${PASS}" -noout 2>/dev/null; then
                echo "[OK]     P12 dekodierbar und parsebar (openssl pkcs12 -info erfolgreich)."
            else
                echo "[WARN]   P12 dekodiert, aber openssl pkcs12 -info schlug fehl."
                echo "         → Falsches Passwort (TI20_SMCB_P12_PASSWORD) oder korruptes P12?"
            fi
        else
            echo "[WARN]   base64-Dekodierung fehlgeschlagen — TI20_SMCB_P12_BASE64 korrekt base64-kodiert?"
        fi
        rm -f "$TMPFILE"
    else
        echo "[SKIP]   openssl nicht gefunden — Parse-Check übersprungen."
    fi
    SMCB_OK=true
fi

# --- Member-ID ---
if [[ -z "${TI20_MEMBER_ID:-}" ]]; then
    echo "[FEHLT]  TI20_MEMBER_ID ist nicht gesetzt."
    echo "         → Beantragen via idp-registrierung@gematik.de (~5 Werktage, kostenlos)."
else
    echo "[OK]     TI20_MEMBER_ID = ${TI20_MEMBER_ID}"
fi

# --- RU-Endpunkte ---
if [[ -z "${TI20_RU_IDP_URL:-}" ]]; then
    echo "[FEHLT]  TI20_RU_IDP_URL nicht gesetzt — RU-Auth nicht möglich."
else
    echo "[OK]     TI20_RU_IDP_URL = ${TI20_RU_IDP_URL}"
fi

if [[ -z "${TI20_RU_ZETA_GUARD_URL:-}" ]]; then
    echo "[FEHLT]  TI20_RU_ZETA_GUARD_URL nicht gesetzt — RU-Guard nicht erreichbar."
else
    echo "[OK]     TI20_RU_ZETA_GUARD_URL = ${TI20_RU_ZETA_GUARD_URL}"
fi

# --- Mock-Modus-Status ---
echo ""
if [[ "${TI20_ZETA_MOCK_AUTH:-true}" == "false" && "$SMCB_OK" == "true" ]]; then
    echo "[SCHARF] TI20_ZETA_MOCK_AUTH=false + SMC-B vorhanden → echter ZETA-Auth aktiv."
    echo "         Gate ausführen: bash scripts/ti2.0/run-gate.sh"
elif [[ "${TI20_ZETA_MOCK_AUTH:-true}" == "false" && "$SMCB_OK" == "false" ]]; then
    echo "[WARN]   TI20_ZETA_MOCK_AUTH=false, aber SMC-B fehlt → Auth schlägt fehl."
    echo "         → SMC-B beschaffen (Runbook) oder TI20_ZETA_MOCK_AUTH=true setzen."
else
    echo "[MOCK]   TI20_ZETA_MOCK_AUTH=true (Default) → kein echter Auth, nur lokale Pings."
fi

exit 0
