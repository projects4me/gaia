#!/usr/bin/env bash
# Install supercronic, the cron runner for containers: it keeps the container's environment (DB_*, HERMES_*),
# logs job output to its own stdout/stderr and never starts a job while the previous run is still going.
# Release: https://github.com/aptible/supercronic/releases
set -euo pipefail

VERSION=v0.2.51

case "$(dpkg --print-architecture)" in
    amd64) BINARY=supercronic-linux-amd64; SHA1SUM=1692066e350a88ff4d7d6474fe3c7a6c41ec5e89 ;;
    arm64) BINARY=supercronic-linux-arm64; SHA1SUM=9d4c2000493215a1898502b2fc2dada3e8ea8c7a ;;
    *) echo "install-supercronic: unsupported architecture $(dpkg --print-architecture)" >&2; exit 1 ;;
esac

cd /tmp
curl -fsSLO "https://github.com/aptible/supercronic/releases/download/${VERSION}/${BINARY}"
echo "${SHA1SUM}  ${BINARY}" | sha1sum -c -
chmod +x "${BINARY}"
mv "${BINARY}" "/usr/local/bin/${BINARY}"
ln -sf "/usr/local/bin/${BINARY}" /usr/local/bin/supercronic
