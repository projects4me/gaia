#!/usr/bin/env bash
# Gaia-specific harness (not part of portable black-box core).
# Loads the search fixture (seed-search.sql) into the test database prepared by prepare-test-db.sh, for the
# api-tester `search` mode. Run it after prepare-test-db.sh and the other modes, right before --mode search:
# the fixture's issues would otherwise show up in the other catalogs' lists.
# Then loads the fixture's stored embeddings (fixtures/search-embeddings.json, seed-search-embeddings.sql) for the
# meaning-search cases (--mode search-semantic); --no-embeddings skips them (generate-search-embeddings.sh does).
# Same connection settings as prepare-test-db.sh: PGHOST for TCP (CI), else docker exec into PG_CONTAINER.
set -euo pipefail

HARNESS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PG_CONTAINER="${PG_CONTAINER:-postgres-dev}"
TEST_DB="${TEST_DB:-pr4m_test}"
PG_USER="${PG_USER:-nouman}"
PGPORT="${PGPORT:-5432}"
EMBEDDINGS_FILE="$HARNESS_DIR/../fixtures/search-embeddings.json"

LOAD_EMBEDDINGS=1
if [[ "${1:-}" == "--no-embeddings" ]]; then
  LOAD_EMBEDDINGS=0
fi

run_psql() {
  if [[ -n "${PGHOST:-}" ]]; then
    PGPASSWORD="${PGPASSWORD:-}" psql -h "$PGHOST" -p "$PGPORT" -U "$PG_USER" -d "$TEST_DB" \
      -v ON_ERROR_STOP=1 -q >/dev/null
  else
    docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$TEST_DB" \
      -v ON_ERROR_STOP=1 -q >/dev/null
  fi
}

# The data file goes in as a standard string literal with its quotes doubled, so any fixture text fits
# (standard_conforming_strings is on: backslashes in the JSON stay as they are)
embeddings_sql() {
  printf '%s' "SET standard_conforming_strings = on; SELECT set_config('search.embeddings', '"
  sed "s/'/''/g" "$EMBEDDINGS_FILE"
  printf '%s\n' "', false);"
  cat "$HARNESS_DIR/seed-search-embeddings.sql"
}

echo "==> Seeding the search fixture into ${TEST_DB}"
run_psql <"$HARNESS_DIR/seed-search.sql"

if [[ "$LOAD_EMBEDDINGS" == "1" ]]; then
  echo "==> Loading the search fixture's stored embeddings"
  embeddings_sql | run_psql
fi
