#!/usr/bin/env bash
# Gaia-specific harness (not part of portable black-box core).
# Loads the search fixture (seed-search.sql) into the test database prepared by prepare-test-db.sh, for the
# api-tester `search` mode. Run it after prepare-test-db.sh and the other modes, right before --mode search:
# the fixture's issues would otherwise show up in the other catalogs' lists.
# Same connection settings as prepare-test-db.sh: PGHOST for TCP (CI), else docker exec into PG_CONTAINER.
set -euo pipefail

HARNESS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PG_CONTAINER="${PG_CONTAINER:-postgres-dev}"
TEST_DB="${TEST_DB:-pr4m_test}"
PG_USER="${PG_USER:-nouman}"
PGPORT="${PGPORT:-5432}"

echo "==> Seeding the search fixture into ${TEST_DB}"
if [[ -n "${PGHOST:-}" ]]; then
  PGPASSWORD="${PGPASSWORD:-}" psql -h "$PGHOST" -p "$PGPORT" -U "$PG_USER" -d "$TEST_DB" \
    -v ON_ERROR_STOP=1 -q <"$HARNESS_DIR/seed-search.sql" >/dev/null
else
  docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$TEST_DB" \
    -v ON_ERROR_STOP=1 -q <"$HARNESS_DIR/seed-search.sql" >/dev/null
fi
