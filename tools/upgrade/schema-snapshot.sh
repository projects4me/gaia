#!/usr/bin/env bash
#
# Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
#
# Prints a schema-only snapshot of a Postgres database that can be diffed against
# another one: pg_dump --schema-only without owners, privileges, the "-- Name: ...;
# Owner: ..." comment headers, the SET/set_config preamble and the \restrict lines
# (whose key changes on every dump) and the
# ALTER TABLE ONLY ... SET (pg_attver=...) lines that an older pg_dump adds. COMMENT ON statements stay: Gaia stores the
# search column signatures in them.
#
# Usage: tools/upgrade/schema-snapshot.sh <db> [output-file]
#
# Connection settings come from the usual libpq variables (PGHOST, PGPORT, PGUSER,
# PGPASSWORD). PG_DUMP overrides the pg_dump command, e.g. to run it in the
# Postgres container:
#   PG_DUMP="docker exec postgres-dev pg_dump -U postgres" tools/upgrade/schema-snapshot.sh pr4m before.sql
#
# @author   Hammad Hassan <gollomer@gmail.com>
# @license  http://www.gnu.org/licenses/agpl.html AGPLv3

set -euo pipefail

if [ $# -lt 1 ] || [ $# -gt 2 ]; then
    echo "usage: $0 <db> [output-file]" >&2
    exit 2
fi

db="$1"
out="${2:-}"
pg_dump_cmd="${PG_DUMP:-pg_dump}"

snapshot() {
    # shellcheck disable=SC2086 # PG_DUMP may hold a command with arguments
    $pg_dump_cmd --schema-only --no-owner --no-privileges "$db" \
        | tr -d '\r' \
        | grep -v -E '^--' \
        | grep -v -E '^SET [a-z_]+ = ' \
        | grep -v -E '^SELECT pg_catalog\.set_config\(' \
        | grep -v -E '^\\(un)?restrict ' \
        | grep -v -E '^ALTER TABLE ONLY .* SET \(pg_attver=' \
        | cat -s
}

if [ -n "$out" ]; then
    snapshot > "$out"
else
    snapshot
fi
