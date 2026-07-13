#!/usr/bin/env bash
# Run portable api-tester against any already-running API.
# Does NOT start containers or prepare databases — pass --base-uri.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"

MODES=()
FILTER="${FILTER:-}"
BASE_URI="${BASE_URI:-}"
FIXTURES="${FIXTURES:-$ROOT_DIR/tools/api-tester/fixtures/default.json}"
REPORT=""

usage() {
  cat <<'HELP'
Usage:
  ./tools/api-tester/harness/run-api-tests.sh --base-uri <url> [options]

Required:
  --base-uri <url>                    Running API base URI (local, staging, or production)

Options:
  --mode <client|backend>[,<mode>...]
                                      API catalog(s) to run (repeatable; default: backend)
  --filter <text|a|b>                 Optional filter (supports OR with |)
  --report <file>                     Report output path (single --mode only)
  --fixtures <file>                   Fixtures file path
  --help                              Show this help

This script only runs api-tester. It does not start gaia/gaia-test or prepare DBs.

Optional local env helpers (separate):
  ./tools/api-tester/harness/prepare-test-db.sh   # seed pr4m_test + write fixtures
  ./tools/api-tester/harness/use-test-db.sh       # start local gaia-test on :8081

Examples:
  ./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode backend
  ./tools/api-tester/harness/run-api-tests.sh --base-uri https://api.staging.example.com --mode client
  ./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode client,backend
HELP
}

append_modes() {
  local value="${1:-}"
  local part
  IFS=',' read -ra parts <<< "$value"
  for part in "${parts[@]}"; do
    part="${part// /}"
    [[ -n "$part" ]] && MODES+=("$part")
  done
}

dedupe_modes() {
  local seen=()
  local unique=()
  local mode seen_mode

  for mode in "${MODES[@]}"; do
    for seen_mode in "${seen[@]:-}"; do
      if [[ "$seen_mode" == "$mode" ]]; then
        continue 2
      fi
    done
    seen+=("$mode")
    unique+=("$mode")
  done

  MODES=("${unique[@]}")
}

resolve_mode_paths() {
  local mode="$1"
  case "$mode" in
    client|frontend)
      echo "$ROOT_DIR/tools/api-tester/apis/client.json|$ROOT_DIR/output/api-tester-client-report.json"
      ;;
    backend)
      echo "$ROOT_DIR/tools/api-tester/apis/backend.json|$ROOT_DIR/output/api-tester-backend-report.json"
      ;;
    *)
      echo "Invalid --mode: $mode (expected client|backend)" >&2
      return 1
      ;;
  esac
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode)
      append_modes "${2:-}"
      shift 2
      ;;
    --filter)
      FILTER="${2:-}"
      shift 2
      ;;
    --report)
      REPORT="${2:-}"
      shift 2
      ;;
    --base-uri)
      BASE_URI="${2:-}"
      shift 2
      ;;
    --fixtures)
      FIXTURES="${2:-}"
      shift 2
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$BASE_URI" ]]; then
  echo "Missing required --base-uri <url>" >&2
  echo "Example: --base-uri http://localhost:8081" >&2
  usage
  exit 1
fi

# Normalize trailing slash
BASE_URI="${BASE_URI%/}"

if [[ ${#MODES[@]} -eq 0 ]]; then
  MODES=(backend)
fi

dedupe_modes

if [[ ${#MODES[@]} -gt 1 && -n "$REPORT" ]]; then
  echo "--report can only be used with a single --mode" >&2
  exit 1
fi

if [[ ! -f "$FIXTURES" ]]; then
  echo "Fixtures file not found: $FIXTURES" >&2
  exit 1
fi

echo "==> api-tester"
echo "    modes: ${MODES[*]}"
echo "    base-uri: $BASE_URI"
echo "    fixtures: $FIXTURES"
[[ -n "$FILTER" ]] && echo "    filter: $FILTER"

OVERALL_STATUS=0
for mode in "${MODES[@]}"; do
  paths="$(resolve_mode_paths "$mode")"
  APIS="${paths%%|*}"
  DEFAULT_REPORT="${paths#*|}"
  MODE_REPORT="${REPORT:-$DEFAULT_REPORT}"

  if [[ ! -f "$APIS" ]]; then
    echo "APIs file not found for mode '$mode': $APIS" >&2
    exit 1
  fi

  echo
  echo "==> running mode: $mode"
  echo "    apis: $APIS"
  echo "    report: $MODE_REPORT"

  ARGS=(
    run
    --base-uri "$BASE_URI"
    --apis "$APIS"
    --fixtures "$FIXTURES"
    --report "$MODE_REPORT"
  )
  if [[ -n "$FILTER" ]]; then
    ARGS+=(--filter "$FILTER")
  fi

  set +e
  php "$ROOT_DIR/tools/api-tester/bin/api-tester" "${ARGS[@]}"
  STATUS=$?
  set -e

  if [[ "$STATUS" -ne 0 ]]; then
    OVERALL_STATUS=1
  fi
done

exit "$OVERALL_STATUS"
