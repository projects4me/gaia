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

## Phalcon 4 names on Phalcon 5 (shim)

Until the switch-over the same code boots on both images. `core/libs/compat/phalcon5.php`, required first in
`autoload.php`, defines the Phalcon 4 class names Gaia still uses when it runs on Phalcon 5 (it detects
`Phalcon\Autoload\Loader`) and does nothing on Phalcon 4:

| Phalcon 4 name | On Phalcon 5 |
|---|---|
| `Phalcon\Di`, `Phalcon\DiInterface` | `class_alias` of `Phalcon\Di\Di`, `Phalcon\Di\DiInterface` |
| `Phalcon\Config` | `class_alias` of `Phalcon\Config\Config` |
| `Phalcon\Logger` | `class_alias` of `Phalcon\Logger\Logger` (same constants and `setLogLevel`) |
| `Phalcon\Exception` | `compat/phalcon5/Exception.php`: extends `\Exception`. Phalcon 5's own exceptions don't extend it. |
| `Phalcon\Loader` | `compat/phalcon5/Loader.php`: `Phalcon\Autoload\Loader` with `registerNamespaces`, `registerClasses`, `registerDirs` calling the `set*` methods |
| `Phalcon\Text` | `compat/phalcon5/Text.php`: `camelize`, `humanize`, `lower`, `random` and the `RANDOM_*` constants, on `Phalcon\Support\Helper\Str` |

`tests/libraries/utils/Phalcon5ShimTest.php` checks the names on both stacks.

`Gaia\Libraries\Meta\Migration\PlanningConnection` (`migrate --dry-run`) implements Phalcon 4's
`AdapterInterface`, whose signatures don't compile on Phalcon 5. The loader's class map loads it on use, so Gaia
boots and `migration migrate` runs on next; `--dry-run` still fails there.

**At the switch-over:** replace the old names in the code with the Phalcon 5 ones (`use Phalcon\Di\Di;`,
`Phalcon\Autoload\Loader` with `set*`, `Phalcon\Support\Helper\Str\*`, a Gaia exception for `Phalcon\Exception`),
then delete `core/libs/compat/` with its require in `autoload.php` and `Phalcon5ShimTest.php`.

## Dependencies

`composer.json` accepts `php ~7.4.0 || ~8.5.0`. There is no lock file, so each image resolves its own versions;
both resolve to the same current releases (`bshaffer/oauth2-server-php` 1.14.2, PHPUnit 9.6.39, Guzzle 7.16.3,
PHPMailer 6.12.0, nikic/php-parser 4.19.5). Dropped: `ext-psr` (Phalcon 5 doesn't need it) and the six
`phalcon/incubator*` packages, which nothing used.

## Findings

- **phalcon/migrations (via phalcon/devtools) removed** (agaia-4687). It blocked PHP 8.5: 3.x only installs with
  2021 nette versions, and v4 is a rewrite without the static connection. `Gaia\Libraries\Meta\Migration` now
  creates missing tables with the DB adapter's `createTable()` (same columns, indexes and options as
  `morphTable` passed it) on a connection from `ConnectionFactory`. Existing Postgres tables were already synced
  by `Gaia\Db\Migration\Postgres`. MySQL loses the alteration of existing tables: migrate still creates missing
  MySQL tables but leaves existing ones as they are. Checked with the snapshot script: the baseline dump
  (`db/Dump20261118.sql`) and an empty database (with `uuid-ossp`, which the views need) give identical snapshots
  after `migration migrate` before and after the change.
- **Overrides of Phalcon methods with union return types** (agaia-4694). Phalcon 5 declares return types such
  as `Request::getJsonRawBody(): \stdClass|array|bool` that PHP 7.4 cannot parse, so a Gaia override can't match
  both stacks; a mismatched one is a fatal error when the class loads. `SubRequest` overrode `getJsonRawBody()`,
  which made `POST /bulk` die silently with an empty 200 on next. It now overrides only `getRawBody(): string`
  and inherits Phalcon's decoding. Side effect: a call without a body decodes as `null` on Phalcon 4 and `{}`
  on Phalcon 5, inside a bulk batch exactly as for a separate HTTP call. Until the switch-over, don't override
  Phalcon methods whose Phalcon 5 return type is a union.

## Deprecations and warnings report

PHP 8.x reports many upgrade problems only as `E_DEPRECATED` or `E_WARNING`. `public/index.php` normally
lowers `error_reporting` to `E_ERROR`; when `docker/php/conf.d/diagnostics.ini` is loaded (`gaia.diagnostics = 1`)
it leaves the ini settings alone instead.

- **HTTP (api-tester):** the `gaia-test` and `gaia-test-hermes-down` services mount `diagnostics.ini`
  (`E_ALL`, `log_errors=On`, `display_errors=Off`) and log to `logs/php-diagnostics.log`.
- **PHPUnit:** `tests/phpunit.xml` and `tests/TestHelper.php` log deprecations to
  `logs/php-diagnostics-phpunit.log` without failing tests; warnings and notices fail the test.
- **Lint on the next image:** `tools/upgrade/lint-next.sh [log]` runs `php -l` with `E_ALL` on every PHP file
  (outside `vendor` and the gaia-dev fixtures) in `gaia-env:next` and writes `logs/php-lint-next.log` in the
  same format.
- **Report:** `php tools/upgrade/deprecations-report.php logs/php-diagnostics.log logs/php-diagnostics-phpunit.log logs/php-lint-next.log`
  groups entries by message pattern and file:line, separates Gaia code from vendor, counts them and prints
  markdown for a CI job summary.

## CI next-stack report

Every PR runs a non-blocking `next-stack` job in `.github/workflows/ci.yml`. `gaia-env:next` is not published,
so the job builds it from `docker/env/Dockerfile` with the `build_next` args of `docker/env/build.sh` (layers
cached in the GitHub Actions cache), starts it as the `gaia-next` container on the host network and runs each
step inside it. It has its own vendor cache key. Only `api-testing` (the current stack) blocks the PR. The job
loads the baseline dump, runs `migration migrate --dry-run` and then `migrate`, takes a schema snapshot, prepares
`pr4m_test`, runs PHPUnit (JUnit), runs `tools/upgrade/lint-next.sh` in place (`IMAGE=none`), and runs the
api-tester `backend` and `acl` modes with `--record-bodies` against Apache with `diagnostics.ini` loaded. Every
check step has `continue-on-error`, so the job stays green when something fails on the next stack and the failure
shows in the summary instead; the job only fails when the image or container can't be set up. It records each
check step's outcome in `upgrade/steps.txt` and uploads everything as the `upgrade-next` artifact. `api-testing`
uploads its schema snapshot and api-tester reports (with bodies) as `upgrade-current`.

Both stacks must use the same Postgres image, `pgvector/pgvector:pg16`: the `issues` table needs the `vector`
extension (Issue search), and plain `postgres` images don't have it, so `migrate` would fail on the next stack.

The `upgrade-report` job runs `tools/upgrade/compare-stacks.sh`: it diffs the two schema snapshots, compares the
api-tester reports with `compare-reports.php --details`, runs `deprecations-report.php` on the diagnostics, lint
and PHPUnit logs, and writes the summary (`tools/upgrade/next-stack-summary.php`) to the job summary: PHPUnit
failures, dry-run statements, schema differences, api-tester failures, response differences and deprecations,
each with a count and its artifact, then the next-stack steps that failed. `n/a` means the step that writes the input failed or didn't run. The
comparison files are uploaded as `upgrade-report`.

To reproduce the summary locally from a CI run:

```sh
gh run download <run-id> -n upgrade-current -D /tmp/upgrade/current
gh run download <run-id> -n upgrade-next -D /tmp/upgrade/next
tools/upgrade/compare-stacks.sh /tmp/upgrade
```

Or from local runs: put each stack's outputs in `<folder>/current` and `<folder>/next` (file names in the header
of `compare-stacks.sh`): `schema-snapshot.sh` after `migrate`, `migrate --dry-run > dry-run.txt`,
`phpunit --log-junit phpunit-junit.xml`, the api-tester reports from `output/` run with `--record-bodies`, and
the logs from `logs/`. Run the script with PHP 7.4 or later.
