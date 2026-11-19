# Gaia runtime image

One recipe (`Dockerfile`) builds the images Gaia, its CI and the test containers run on. It replaces
`rananoman/gaia:env-dev`, whose recipe was not in any repo. (`docker-images/gaia/Dockerfile` in the workspace is an
obsolete MySQL-era recipe, `mileschou/phalcon:7.4` with PHPUnit 5.7; this one supersedes it.)

| Tag | PHP | Phalcon | psr | xdebug | xhprof | Base |
|---|---|---|---|---|---|---|
| `gaia-env:current` | 7.4.21 | 4.1.2 | 1.1.0 | 3.1.5 | 2.3.7 | Debian 10 (`php:7.4.21-apache-buster`, apt from archive.debian.org) |
| `gaia-env:next` | 8.5.12 | 5.23.0 | none | 3.5.3 | 2.3.10 | `php:8.5.12-apache` |

Both install pdo_pgsql, imagick, zip, mbstring, curl, xml, exif, Apache with mod_rewrite, composer and PHPUnit.
`current` also installs mongodb 1.15.3 and PHPUnit 9.6.21 to match env-dev; `next` skips mongodb and uses PHPUnit 11.5.0.

## Build

```
docker/env/build.sh [current|next|all]   # default: all
```

Versions are build args (`PHP`/base image, `PHALCON_VERSION`, `PSR_VERSION`, `XDEBUG_VERSION`, `XHPROF_VERSION`,
`MONGODB_VERSION`, `PHPUNIT_VERSION`, `DEBIAN_ARCHIVE`); `build.sh` holds the two sets.

## Check

```
docker run --rm gaia-env:next php -v              # PHP 8.5.12
docker run --rm gaia-env:next php --ri phalcon    # Version => 5.23.0
```

## Publishing

The images are built locally. The reviewer publishes the tags to the registry; nothing here pushes.
CI builds `gaia-env:current` and `gaia-env:next` from this recipe (`.github/workflows/ci.yml`, layers in the GitHub Actions cache),
so keep the build args there equal to `build.sh`. The local Dockerfile, `docker/dev/Dockerfile`, `upgrade-report` and
`cache-dependencies.yml` still use env-dev until a published tag exists.
