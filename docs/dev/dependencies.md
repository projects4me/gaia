# Updating dependencies

`composer.lock` is committed. CI, `cache-dependencies.yml` and the Docker builds run `composer install`, which
installs exactly the locked versions, so CI, local and the deployed image match. The CI vendor cache key is
`vendor-cache-gaia-env-<image tag>-<hash of composer.lock>`, so it changes only when the lock or the image does.

`composer.json` sets `config.platform.php` to `8.5.12`, so the lock resolves the same on every machine.

## Update a package

Run composer in the 8.5 image (`ghcr.io/projects4me/gaia-env:8.5.12-phalcon5.23.0`), never on the host PHP:

```
composer update <vendor/package>
```

Commit `composer.json` (if the constraint changed) and `composer.lock` together. Use a bare `composer update`
only for a deliberate refresh of all packages. When the image's PHP version changes, update `config.platform.php`
and regenerate the lock.
