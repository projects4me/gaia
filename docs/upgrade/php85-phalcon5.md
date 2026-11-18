# Upgrade to PHP 8.5 and Phalcon 5.23

Notes for the move from PHP 7.4 / Phalcon 4.1 to PHP 8.5 / Phalcon 5.23. Later issues add their findings here.

## Runtime images

Both stacks are built from one recipe, `docker/env/Dockerfile` (see `docker/env/README.md`):

- `gaia-env:current`: PHP 7.4.21, Phalcon 4.1.2 (with psr), Debian 10. Same stack as `rananoman/gaia:env-dev`.
- `gaia-env:next`: PHP 8.5.12, Phalcon 5.23.0 (no psr), xdebug 3.5.3, xhprof 2.3.10.

Build with `docker/env/build.sh`. Switching Dockerfiles, compose and CI to these images is a separate step.

## Findings

_None yet._
