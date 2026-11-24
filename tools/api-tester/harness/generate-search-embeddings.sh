#!/usr/bin/env bash
# Gaia-specific harness (not part of portable black-box core).
# Regenerates fixtures/search-embeddings.json, the search fixture's stored embeddings (agaia-4896), in one command:
# seeds the fixture without its embeddings, reads the fixture issues back from the test database, embeds their
# semantic text through Ares (bin/generate-search-embeddings.php) and loads the new file with seed-search.sh.
# Run it after changing seed-search.sql, or when Ares changes its embedding model. Needs Ares up (ARES_URL, default
# http://localhost:8000) and the test database prepared by prepare-test-db.sh; same connection settings as seed-search.sh.
set -euo pipefail

HARNESS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_TESTER_DIR="$(cd "$HARNESS_DIR/.." && pwd)"
PG_CONTAINER="${PG_CONTAINER:-postgres-dev}"
TEST_DB="${TEST_DB:-pr4m_test}"
PG_USER="${PG_USER:-nouman}"
PGPORT="${PGPORT:-5432}"
ARES_URL="${ARES_URL:-http://localhost:8000}"
OUT="$API_TESTER_DIR/fixtures/search-embeddings.json"

RECORDS_SQL="SELECT COALESCE(json_agg(json_build_object('id', id, 'subject', subject, 'description', description)
  ORDER BY \"issueNumber\"), '[]') FROM issues WHERE id LIKE 'api-test-search-%' AND deleted = 0"

read_records() {
  if [[ -n "${PGHOST:-}" ]]; then
    PGPASSWORD="${PGPASSWORD:-}" psql -h "$PGHOST" -p "$PGPORT" -U "$PG_USER" -d "$TEST_DB" \
      -v ON_ERROR_STOP=1 -At -c "$RECORDS_SQL"
  else
    docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$TEST_DB" \
      -v ON_ERROR_STOP=1 -At -c "$RECORDS_SQL"
  fi
}

"$HARNESS_DIR/seed-search.sh" --no-embeddings

echo "==> Embedding the search fixture through Ares at ${ARES_URL}"
read_records | php "$API_TESTER_DIR/bin/generate-search-embeddings.php" --ares-url "$ARES_URL" --out "$OUT"

"$HARNESS_DIR/seed-search.sh"
