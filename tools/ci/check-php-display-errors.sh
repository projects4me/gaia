#!/usr/bin/env bash
#
# Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
#
# Fails when the effective PHP config of an image would print errors into response bodies.
# Reads the real config with php -i, so it covers the base image's ini files too.
#
# Usage: tools/ci/check-php-display-errors.sh <image>
set -eu
image="${1:?usage: check-php-display-errors.sh <image>}"
info=$(docker run --rm --entrypoint php "$image" -i)
status=0
for key in display_errors display_startup_errors; do
  # php -i prints "key => local => master"; both must be off (the value Off, 0 or empty).
  line=$(printf '%s\n' "$info" | grep -E "^${key} =>" | head -1)
  value=$(printf '%s' "$line" | awk -F' => ' '{print tolower($2)}')
  case "$value" in
    off|0|"") echo "ok: $key is off ($line)" ;;
    *) echo "FAIL: $key is on in $image ($line)"; status=1 ;;
  esac
done
log=$(printf '%s\n' "$info" | grep -E '^log_errors =>' | head -1)
case "$(printf '%s' "$log" | awk -F' => ' '{print tolower($2)}')" in
  on|1) echo "ok: log_errors is on" ;;
  *) echo "FAIL: log_errors is not on in $image ($log)"; status=1 ;;
esac
exit $status
