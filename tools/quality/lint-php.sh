#!/usr/bin/env bash
#
# Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
#
# Runs php -l with error_reporting=E_ALL on every PHP file (outside vendor and the gaia-dev
# fixtures) and writes the diagnostics to a log in the PHP error-log format, ready for
# tools/quality/deprecations-report.php.
#
# Usage: tools/quality/lint-php.sh [log-file]   (default logs/php-lint.log)
# IMAGE runs php in that docker image (for example gaia-env:8.5.12-phalcon5.23.0). The default,
# IMAGE=none, runs php in place: CI and a shell inside the gaia container.

set -u
cd "$(dirname "$0")/../.." || exit 1
LOG="${1:-logs/php-lint.log}"
IMAGE="${IMAGE:-none}"
mkdir -p "$(dirname "$LOG")"

LINT='
find . \( -path ./vendor -o -path ./tests/tools/gaia-dev/fixtures -o -path ./node_modules -o -path ./.git \) -prune \
  -o -name "*.php" -print | sort | while read -r f; do
  php -d error_reporting=E_ALL -d display_errors=1 -d html_errors=0 -l "$f" 2>&1 | grep -v "^No syntax errors"
done'

# The path php reports the files under, rewritten to /var/www/html like the other logs
ROOT=/app
[ "$IMAGE" = "none" ] && ROOT="$PWD"

if [ "$IMAGE" = "none" ]; then
    sh -c "$LINT"
else
    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app "$IMAGE" sh -c "$LINT"
fi | sed -E -n 's#^(PHP )?(Deprecated|Warning|Fatal error|Parse error|Notice): #PHP \2:  #p' \
   | sed -E "s# in (${ROOT}|\.)/# in /var/www/html/#" > "$LOG"

echo "wrote $(wc -l < "$LOG") entries to $LOG"
