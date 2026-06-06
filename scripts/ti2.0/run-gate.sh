#!/usr/bin/env bash
# Track C — TI 2.0 Konformitäts-Gate gegen den gematik Testhub 2.0.
# Bootet den Testhub (Docker-Compose-Mocks) und fährt eine Cucumber-Suite.
# OHNE SMC-B-Testzertifikat wird BEWUSST + sichtbar übersprungen (kein stilles Grün).
# Siehe docs/ti2.0/ti2.0-konformitaets-gate.md
set -euo pipefail

TESTHUB_REF="${TESTHUB_REF:-3.1.0}"                      # gepinnt auf gematik-Release-Tag
TESTHUB_DIR="${TESTHUB_DIR:-/tmp/ti2.0-testhub}"
SMCB_P12="${TI20_SMCB_P12_PATH:-}"                       # Pfad zur smcb_private.p12 (aus CI-Secret)
TESTHUB_TAGS="${TESTHUB_TAGS:-@smoke}"                   # Cucumber-Tags (Start: Smoke)
TESTHUB_SUITE="${TESTHUB_SUITE:-test/zeta-testsuite}"

if [[ -z "$SMCB_P12" || ! -f "$SMCB_P12" ]]; then
  echo "::warning::TI 2.0 Konformitäts-Gate ÜBERSPRUNGEN — kein SMC-B-Testzertifikat (TI20_SMCB_P12_PATH) vorhanden."
  echo "SMC-B anfordern: https://service.gematik.de/servicedesk/customer/portal/37/create/198"
  echo "Danach als CI-Secret TI20_SMCB_P12_BASE64 hinterlegen — dann wird dieses Gate scharf. Siehe docs/ti2.0/."
  exit 0
fi

echo ">> Testhub @ $TESTHUB_REF klonen"
rm -rf "$TESTHUB_DIR"
git clone --depth 1 --branch "$TESTHUB_REF" https://github.com/gematik/ti2.0-testhub.git "$TESTHUB_DIR"

mkdir -p "$TESTHUB_DIR/doc/docker/backend/zeta/smcb-private"
cp "$SMCB_P12" "$TESTHUB_DIR/doc/docker/backend/zeta/smcb-private/smcb_private.p12"

cd "$TESTHUB_DIR"
echo ">> Sim-Service-Images bauen"
./mvnw clean install -Pdocker -DskipTests

echo ">> Testhub-Stack starten"
docker compose -f ./doc/docker/compose-local.yaml --profile full up -d --remove-orphans

cleanup() { docker compose -f ./doc/docker/compose-local.yaml --profile full down -v || true; }
trap cleanup EXIT

# WHY: ZETA-Backend (PEP/PDP/Ingress) braucht einen Moment bis ready — Readiness-Wait ggf. in C1 verfeinern.
sleep 30

echo ">> Suite $TESTHUB_SUITE mit Tags '$TESTHUB_TAGS' fahren"
./mvnw -pl "$TESTHUB_SUITE" clean verify -Dskip.inttests=false -Dcucumber.filter.tags="$TESTHUB_TAGS"
