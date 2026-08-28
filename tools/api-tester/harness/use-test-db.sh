#!/usr/bin/env bash
# Optional local helper: start gaia-test (DB_NAME=pr4m_test) on :8081.
# Not used by api-tester itself — only for bringing up a local target.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="${TEST_SERVICE:-gaia-test}"
BASE_URI="${BASE_URI:-}"

if [[ -z "$BASE_URI" ]]; then
  case "$SERVICE" in
    gaia-test-hermes-down)
      BASE_URI="http://localhost:8082"
      ;;
    *)
      BASE_URI="http://localhost:8081"
      ;;
  esac
fi
BASE_URI="${BASE_URI%/}"

echo "==> Starting local env service: $SERVICE"
docker compose up -d "$SERVICE"

echo "==> Waiting for HTTP at $BASE_URI"
for i in $(seq 1 30); do
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 2 "$BASE_URI/api/v1/token" || true)"
  if [[ "$code" != "000" && -n "$code" ]]; then
    break
  fi
  sleep 1
done

echo "Local API ready at $BASE_URI"
if [[ "$SERVICE" == "gaia-test-hermes-down" ]]; then
  echo "Next (fail-open): ./tools/api-tester/harness/run-api-tests.sh --base-uri $BASE_URI --mode live --filter fail-open"
else
  echo "Next: ./tools/api-tester/harness/run-api-tests.sh --base-uri $BASE_URI --mode backend"
  echo "Live: ./tools/api-tester/harness/run-api-tests.sh --base-uri $BASE_URI --mode live --hermes-url http://localhost:9001"
  echo "       (start hermes-test first: cd ../hermes && docker compose up -d hermes-test)"
fi
