#!/usr/bin/env bash
#
# Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
#
# Compares the outputs of the current-stack and next-stack CI jobs and prints the markdown
# summary (tools/upgrade/next-stack-summary.php). <folder> holds current/ and next/, each either
# flat or laid out as in the repo (upgrade/, output/, logs/, as the CI artifacts unpack):
#
#   current: schema.sql, api-tester-{backend,acl}-report.json
#   next:    schema.sql, dry-run.txt, phpunit-junit.xml, api-tester-{backend,acl}-report.json,
#            php-diagnostics.log, php-diagnostics-phpunit.log, php-lint-next.log,
#            steps.txt (one "<step> <outcome>" line per check step)
#
# It writes schema.diff, compare-{backend,acl}.txt and deprecations.md into <folder>. Missing
# inputs show as n/a in the summary; the script only fails on a bad folder.
#
# Usage: tools/upgrade/compare-stacks.sh <folder>
#
# @author   Hammad Hassan <gollomer@gmail.com>
# @license  http://www.gnu.org/licenses/agpl.html AGPLv3

set -u

if [ $# -ne 1 ] || [ ! -d "$1" ]; then
    echo "usage: $0 <folder>" >&2
    exit 2
fi

tools="$(cd "$(dirname "$0")/.." && pwd)"
cd "$1" || exit 2

# Flatten the artifact layout (upgrade/, output/, logs/ below each side) into the side's folder
for side in current next; do
    for sub in upgrade output logs; do
        if [ -d "$side/$sub" ]; then
            cp -r "$side/$sub/." "$side/"
        fi
    done
done

rm -f schema.diff compare-*.txt deprecations.md
if [ -f current/schema.sql ] && [ -f next/schema.sql ]; then
    diff -u current/schema.sql next/schema.sql > schema.diff
fi

for mode in backend acl; do
    php "$tools/api-tester/bin/compare-reports.php" \
        "current/api-tester-$mode-report.json" "next/api-tester-$mode-report.json" \
        --details > "compare-$mode.txt" 2>&1
done

logs=()
for log in next/php-diagnostics.log next/php-diagnostics-phpunit.log next/php-lint-next.log; do
    if [ -f "$log" ]; then
        logs+=("$log")
    fi
done
if [ ${#logs[@]} -gt 0 ]; then
    php "$tools/upgrade/deprecations-report.php" "${logs[@]}" > deprecations.md
fi

php "$tools/upgrade/next-stack-summary.php" .

# The next-stack check steps don't fail the job, so list the ones that failed here
if [ -f next/steps.txt ]; then
    failed="$(awk '$2 != "success" && $2 != "skipped"' next/steps.txt)"
    echo
    echo "### Next-stack steps"
    echo
    if [ -z "$failed" ]; then
        echo "All check steps succeeded."
    else
        echo "| Step | Outcome |"
        echo "| --- | --- |"
        echo "$failed" | awk '{ printf "| %s | %s |\n", $1, $2 }'
    fi
fi
