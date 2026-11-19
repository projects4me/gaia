# Upgrade to PHP 8.5 and Phalcon 5.23

Notes for the move from PHP 7.4 / Phalcon 4.1 to PHP 8.5 / Phalcon 5.23. Later issues add their findings here.

## Runtime images

Both stacks are built from one recipe, `docker/env/Dockerfile` (see `docker/env/README.md`):

- `gaia-env:current`: PHP 7.4.21, Phalcon 4.1.2 (with psr), Debian 10. Same stack as `rananoman/gaia:env-dev`.
- `gaia-env:next`: PHP 8.5.12, Phalcon 5.23.0 (no psr), xdebug 3.5.3, xhprof 2.3.10.

Build with `docker/env/build.sh`. Switching Dockerfiles, compose and CI to these images is a separate step.

## Schema snapshots

`tools/upgrade/schema-snapshot.sh <db> [output-file]` prints `pg_dump --schema-only` without owners, privileges,
the `-- Name: ...; Owner: ...` comment headers, the `SET` preamble and the `\restrict` lines, so two snapshots
can be diffed. `COMMENT ON` statements stay (the search column signatures live in them). It connects with the
libpq variables (`PGHOST`, `PGUSER`, ...); `PG_DUMP` overrides the command, for example to run it in the
Postgres container:

```sh
PG_DUMP="docker exec postgres-dev pg_dump -U <user>" tools/upgrade/schema-snapshot.sh <db> before.sql
# change the code, migrate the same starting database again into another <db>
PG_DUMP="docker exec postgres-dev pg_dump -U <user>" tools/upgrade/schema-snapshot.sh <db2> after.sql
diff before.sql after.sql
```

## Findings

- **phalcon/migrations (via phalcon/devtools) removed** (agaia-4687). It blocked PHP 8.5: 3.x only installs with
  2021 nette versions, and v4 is a rewrite without the static connection. `Gaia\Libraries\Meta\Migration` now
  creates missing tables with the DB adapter's `createTable()` (same columns, indexes and options as
  `morphTable` passed it) on a connection from `ConnectionFactory`. Existing Postgres tables were already synced
  by `Gaia\Db\Migration\Postgres`. MySQL loses the alteration of existing tables: migrate still creates missing
  MySQL tables but leaves existing ones as they are. Checked with the snapshot script: the baseline dump
  (`db/Dump20261118.sql`) and an empty database (with `uuid-ossp`, which the views need) give identical snapshots
  after `migration migrate` before and after the change.

## Deprecations and warnings report

PHP 8.x reports many upgrade problems only as `E_DEPRECATED` or `E_WARNING`. `public/index.php` normally
lowers `error_reporting` to `E_ERROR`; when `docker/php/conf.d/diagnostics.ini` is loaded (`gaia.diagnostics = 1`)
it leaves the ini settings alone instead.

- **HTTP (api-tester):** the `gaia-test` and `gaia-test-hermes-down` services mount `diagnostics.ini`
  (`E_ALL`, `log_errors=On`, `display_errors=Off`) and log to `logs/php-diagnostics.log`.
- **PHPUnit:** `tests/phpunit.xml` and `tests/TestHelper.php` write warnings and deprecations to
  `logs/php-diagnostics-phpunit.log` without failing tests.
- **Lint on the next image:** `tools/upgrade/lint-next.sh [log]` runs `php -l` with `E_ALL` on every PHP file
  (outside `vendor` and the gaia-dev fixtures) in `gaia-env:next` and writes `logs/php-lint-next.log` in the
  same format.
- **Report:** `php tools/upgrade/deprecations-report.php logs/php-diagnostics.log logs/php-diagnostics-phpunit.log logs/php-lint-next.log`
  groups entries by message pattern and file:line, separates Gaia code from vendor, counts them and prints
  markdown for a CI job summary.
