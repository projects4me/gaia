#!/usr/bin/env bash
#
# Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
#
# Runs php -l with error_reporting=E_ALL on every PHP file (outside vendor and the gaia-dev
# fixtures) inside gaia-env:next and writes the diagnostics to a log in the PHP error-log
# format, ready for tools/upgrade/deprecations-report.php.
#
# Usage: tools/upgrade/lint-next.sh [log-file]   (default logs/php-lint-next.log)
# IMAGE overrides the image (default gaia-env:next).

set -u
cd "$(dirname "$0")/../.." || exit 1
LOG="${1:-logs/php-lint-next.log}"
IMAGE="${IMAGE:-gaia-env:next}"
mkdir -p "$(dirname "$LOG")"

MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app "$IMAGE" sh -c '
find . \( -path ./vendor -o -path ./tests/tools/gaia-dev/fixtures -o -path ./node_modules -o -path ./.git \) -prune \
  -o -name "*.php" -print | sort | while read -r f; do
  php -d error_reporting=E_ALL -d display_errors=1 -d html_errors=0 -l "$f" 2>&1 | grep -v "^No syntax errors"
done' | sed -E -n 's#^(PHP )?(Deprecated|Warning|Fatal error|Parse error|Notice): #PHP \2:  #p' | sed -E 's# in /app/# in /var/www/html/#' > "$LOG"

echo "wrote $(wc -l < "$LOG") entries to $LOG"
