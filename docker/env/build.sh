#!/usr/bin/env bash
# Build the Gaia runtime images: gaia-env:current and gaia-env:next.
# Usage: docker/env/build.sh [current|next|all]   (default: all)
set -euo pipefail
cd "$(dirname "$0")"

build_current() {
    docker build -t gaia-env:current \
        --build-arg BASE_IMAGE=php:7.4.21-apache-buster \
        --build-arg DEBIAN_ARCHIVE=1 \
        --build-arg PHALCON_VERSION=4.1.2 \
        --build-arg PSR_VERSION=1.1.0 \
        --build-arg XDEBUG_VERSION=3.1.5 \
        --build-arg XHPROF_VERSION=2.3.7 \
        --build-arg MONGODB_VERSION=1.15.3 \
        --build-arg PHPUNIT_VERSION=9.6.21 \
        .
}

build_next() {
    docker build -t gaia-env:next \
        --build-arg BASE_IMAGE=php:8.5.12-apache \
        --build-arg DEBIAN_ARCHIVE=0 \
        --build-arg PHALCON_VERSION=5.23.0 \
        --build-arg PSR_VERSION= \
        --build-arg XDEBUG_VERSION=3.5.3 \
        --build-arg XHPROF_VERSION=2.3.10 \
        --build-arg MONGODB_VERSION= \
        --build-arg PHPUNIT_VERSION=11.5.0 \
        .
}

case "${1:-all}" in
    current) build_current ;;
    next) build_next ;;
    all) build_current; build_next ;;
    *) echo "usage: $0 [current|next|all]" >&2; exit 2 ;;
esac
