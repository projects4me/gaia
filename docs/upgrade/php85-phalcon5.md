# Upgrade to PHP 8.5 and Phalcon 5.23

Notes for the move from PHP 7.4 / Phalcon 4.1 to PHP 8.5 / Phalcon 5.23. Later issues add their findings here.

## Runtime images

Both stacks are built from one recipe, `docker/env/Dockerfile` (see `docker/env/README.md`):

- `gaia-env:current`: PHP 7.4.21, Phalcon 4.1.2 (with psr), Debian 10. Same stack as `rananoman/gaia:env-dev`; CI builds it from the repo (see below).
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

The migration layer (`Migration`, its driver and the migration handlers) runs on Gaia's own
`Gaia\Db\Migration\SchemaConnection`, not Phalcon's `AdapterInterface`, whose signatures differ between Phalcon 4
and 5. `AdapterConnection` runs the calls on the Phalcon adapter, passing only arguments both versions accept, and
`PlanningConnection` (`migrate --dry-run`) plans the writes; so `migration migrate` and `--dry-run` run on both
stacks (agaia-4705).

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
api-tester `client`, `backend` and `acl` modes with `--record-bodies` against Apache with `diagnostics.ini` loaded. Every
check step has `continue-on-error`, so the job stays green when something fails on the next stack and the failure
shows in the summary instead; the job only fails when the image or container can't be set up. It records each
check step's outcome in `upgrade/steps.txt` and uploads everything as the `upgrade-next` artifact. `api-testing`
uploads its schema snapshot and api-tester reports (with bodies) as `upgrade-current`.

The blocking `graph-drift` and `api-testing` jobs build `gaia-env:current` the same way (the `build_current` args, layer cache scope
`gaia-env-current`), start it as the `gaia-current` container and run their steps inside it, so a stack change only
changes build args. `upgrade-report` and `cache-dependencies.yml` still use `rananoman/gaia:env-dev` (php only).

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

## Remaining work

This section triages the next-stack report from CI run 44518423955 (agaia-4706). That run tested PR #322 (agaia-4705,
`migrate --dry-run` on Phalcon 5) merged into `dev`, after next-ci-pgvector, compare-noise, phpunit-strict and
validation-422 had merged. Every entry in the report belongs to one group below, and the group counts add up to
the report's totals. "Both stacks" means the fix also runs on PHP 7.4 / Phalcon 4, so it can merge before the
switch-over.

| Report section | Total | Groups |
|---|---|---|
| PHPUnit (1436 tests, 147 skipped) | 62 errors, 0 failures | P1 (60), P2 (1), P3 (1) |
| api-tester `backend` / `acl` | 0 failures (220 and 248 passed, as on current) | none |
| Response differences | 4 | R1 (4) |
| Dry run | 1 error | D1 |
| Schema diff | 867 lines differ, 0 real differences | S1 |
| Deprecations and warnings | 12323 (all Gaia code, 0 vendor) | W1 to W10 |
| Failed next-stack steps | `dry-run`, `phpunit` | D1, P1 to P3 |

### PHPUnit

- **P1. `logs/tests/` is missing in the next-stack job: 60 errors.** `tests/TestHelper.php:93` logs every query
  to `logs/tests/db.log`. `api-testing` creates the folder (`mkdir -p logs/tests`), but `next-stack` only creates
  `logs` (`.github/workflows/ci.yml:340`), so the first query of each test fails. 47 fail as a `fopen()` warning
  (a failure since phpunit-strict) and 13 as Phalcon's `Logger\Adapter\Exceptions\FileOpenFailed`. They span 19
  classes, for example `SearchServiceTest` (15), `PostgresSearchMigrationTest` (10),
  `RolePermissionSeederTest` (11), `PreviewControllerTest` (6), `TokenControllerTest` and `AgentLifecycleTest`.
  Fix: create `logs/tests` in that step. Size: one line, CI only. **These errors hide the real results of the 60
  tests.** After the fix, run the report again and triage what those tests show. The fix also removes the 13
  matching `fopen()` warnings in the deprecations report (W10).
- **P2. `RouterTest::testAppRouteRegistersConfiguredControllerAndAction`: 1 error.** In Phalcon 5,
  `Router::handle()` reads the `request` service and throws `RequestServiceUnavailable` when the router has no DI
  container. Phalcon 4 didn't need a container here. The app's router always has one, so only the test is
  affected. Fix: give the router a DI container before `handle()`. File: `tests/foundation/mvc/RouterTest.php:52`.
  Both stacks. Size: small.
- **P3. `IssuestatusTest::testMissingTextColorDefaultsToLight`: 1 error.** The test builds `Issuestatus` with
  `newInstanceWithoutConstructor()` and sets `textColor = null`. In Phalcon 5, `Model::__set()` treats a `null`
  value as a possible relation reset and calls `getRelationByAlias()` on the models manager. A model built without
  its constructor has no manager, so the call fails. Fix: build the model through its constructor with the test
  DI. File: `tests/models/IssuestatusTest.php:128`. Both stacks. Size: small. Real models take the same
  `__set(null)` path, but they have a manager, so it doesn't fail there.

### api-tester and response differences

- The api-tester `backend` and `acl` modes have no failures on the next stack. The next-stack job doesn't run
  `client` mode, so those endpoints (user, password and assignment flows, agent creation) aren't covered yet.
- **R1. Timing noise: 4 differences.** In `POST /api/v1/bulk` (`acl-bulk-item-denied-others-allowed`),
  `$.responses[*].durationMs` changes on every run, on both stacks. Fix: make the comparison ignore `durationMs`,
  as it ignores the runtime-generated IDs (agaia-4704). File: `tools/api-tester/src/IgnoreList.php`. Size: small.
  There are no type changes, missing keys, status changes or empty bodies, so **nothing in this report changes
  what Prometheus receives**.

### Dry run and schema

- **D1. `migrate --dry-run` fails on a database without `vector`.** In a dry run, `Postgres::createExtensions()`
  only plans `CREATE EXTENSION vector`. `checkSearchSchema()` then queries the real database (`pg_extension`) and
  throws "Table issues needs the PostgreSQL extension vector, which is not installed". The baseline dump
  (`db/Dump20261118.sql`) has `pg_trgm` and `uuid-ossp` but not `vector`. The bug is in planning and affects both
  stacks. Only next-stack shows it because the current-stack job doesn't run a dry run. The real `migrate`
  succeeds. Fix: in planning mode, treat the extensions that `createExtensions()` planned as installed, for example
  by having the planning connection remember them. Files: `core/mvc/db/migration/Postgres.php`
  (`createExtensions`, `checkSearchSchema`, `hasExtension`) and
  `core/libs/meta/migration/planningconnection.php`. Both stacks. Size: small to medium.
- **S1. Schema snapshot noise: 867 lines, no real difference.** The current-stack snapshot has an
  `ALTER TABLE ONLY ... ALTER COLUMN ... SET (pg_attver=...)` line for every column. The next-stack snapshot has
  none. With those lines removed, the two snapshots are identical. The two jobs install `postgresql-client-16`
  from different apt sources: current uses the buster archive (`apt-archive.postgresql.org`), next uses
  `apt.postgresql.org` for its own release. The lines come from the archive's `pg_dump`. Fix: drop the `pg_attver`
  lines in `tools/upgrade/schema-snapshot.sh`, or use the same client in both jobs. Both stacks. Size: one line.

### Deprecations and warnings (Gaia code)

Counts are log entries, one per occurrence. On PHP 8.5 these are only logged; most become errors in PHP 9. None is
in vendor code. Source: the Apache log of the api-tester run (HTTP), the PHPUnit log, and `php -l` (lint).

| Group | Count | Where (top locations) | Both stacks | Size |
|---|---|---|---|---|
| W1. `null` passed to a typed internal or Phalcon parameter | 5499 | `uniqid(NULL, TRUE)` in `create_guid()`, `core/libs/utils/utility_functions.php:22` (4965); **`Phalcon\Mvc\Model\Query\Builder::having(null)`**, `core/mvc/models/Query.php:143` (262); `explode(null)`, `core/mvc/models/query/Meta.php:325` (241); `str_replace(null)`, `core/mvc/db/dialect/Postgres.php:398` (8), `tools/gaia-dev/src/Docs/Markdown.php:86` and `tests/models/behaviors/IdeaReferencesBehaviorTest.php:196` (22); `\Exception` code `null`, `core/exceptions/Permission.php:37` (1) | yes | small (5 sites) |
| W2. `null` as an array offset | 3888 | `core/mvc/router.php:62,66,103` (3880: `$moduleRoutes->identifier` is null for modules that don't set one); `tests/libraries/tools/ToolCatalogRulesTest.php:143` (8) | yes | small |
| W3. Missing array keys, and `null` read as an array or object (notices on 7.4) | 1087 | `Undefined array key "where"` and other query params, in `core/mvc/models/Query.php:194,195,293`, `core/mvc/models/Model.php:195,196,199` and `query/Meta.php:241,262` (695); `foreach` over `null` in `Relationship.php:151,166,196`, `query/Meta.php:241,301`, `relationships/HasManyToMany.php:77` and `IssuementionComponent.php:42` (385); array offset on `null` in `RestController.php:868,1036,1086` and `IssuementionComponent.php:39` (6); property of `null` in `ProjectActivitiesComponent.php:228` (1) | yes | medium (default the query params once in `Model` or `Query`) |
| W4. Dynamic properties | 1162 | core: `query/Meta.php:124,150` (484), `Relationship.php:91` (263), `relationships/HasMany.php:28` (5), `relationships/HasManyToMany.php:35` (2); tests: PHPUnit mocks of `ModelInterface` (332, e.g. `tests/models/behaviors/WorkflowModelStub.php`), `FakeTokenModel` (45), stubs in `WorkflowTriggersComponentTest`, `WorkflowRepliesComponentTest` and `MentionsBehaviorTest` (31) | yes: declare the properties, or `#[\AllowDynamicProperties]` on test stubs | medium |
| W5. `curl_close()` is deprecated | 480 | `tools/api-tester/src/HttpClient.php:60` (test tooling, not the API) | yes: call it only below PHP 8 | small |
| W6. Implicitly nullable parameters (`Type $x = null`) | 145 | 53 parameters in 25 files (lint), mostly constructors in `core/libs/workflows/` (`WorkflowRunner`, `WorkflowTestRun`, `TestRunPayload`, `TriggerDispatcher`, ...), `core/libs/mentions/MentionRecorder.php`, `core/libs/authorization/ServiceEnrollments.php`, the `mentions`, `searchIndex`, `setting` and `translationCache` behaviours, and `query/Clause.php` | yes: `?Type` works on PHP 7.1+ | medium (mechanical) |
| W7. Array to string conversion | 31 | `public/index.php:130` (24: the debug DB logger joins bind values, some of them arrays); `tools/gaia-dev/src/Query/ModelQuery.php:139` (7) | yes | small |
| W8. Undefined model property read through `Model::__get()` | 11 | `RestController.php:1073` reads `deleted` on `Membership`, `Tagged` and `Vote` (3); `ProjectActivitiesComponent.php:147,226` (`Membership::relatedId`, `roleId`); `doccontentBehavior.php:101,104`, `planVersionBehavior.php:53` and `PlanVersionBehaviorTest` (`Doccontent::state`); `ideaScreensBehavior.php:85,99` (`Ideamessage::screens`). Phalcon 4 raises the same notice; `error_reporting(E_ERROR)` hid it. | yes | small |
| W9. `ReflectionMethod::setAccessible()` is deprecated | 7 | tests: `SystemFieldTest`, `MigrationDriverErrorTest`, `PostgresIssueTableDefinitionTest`, `SearchBlockTest`, `TranslationCacheBehaviorTest` | yes: call it only below PHP 8.1 | small |
| W10. `fopen(logs/tests/db.log)` fails | 13 | `tests/TestHelper.php:93`; same cause as P1 | yes | fixed by P1 |

**Typed Phalcon 5 APIs passed the wrong type.** The report shows one call. `Query.php:143` passes
`$this->clause->having` to `Builder::having(string $conditions)`, and that value is `null` when the request has no
`having`. Today it is a deprecation; in PHP 9 it becomes a `TypeError`. Fix: call `having()` only when the value is
set. No other Phalcon call logged a type deprecation, and no `TypeError` reached the api-tester or PHPUnit results.
The 60 tests hidden by P1 haven't run on next yet, so check for this again when the report is rerun.

### For Prometheus

Nothing in this report changes a response: apart from R1, the `backend` and `acl` bodies are identical. Still to
check on the UI side:

- the `client` endpoints, which don't run on next yet;
- bulk body decoding (agaia-4694): a call without a body decodes as `null` on Phalcon 4 and `{}` on Phalcon 5;
- that `public/index.php` keeps `display_errors` off in production, so the W1 to W8 messages can't end up in JSON
  bodies.

### Next steps

1. Fix P1 (which also clears W10), run the report again, and triage the 60 tests it hides.
2. Fix D1, R1 and S1, so the report shows only real problems.
3. Fix W1 and W2 (most entries, few sites, and the typed `having()` call), then W3, W4, W6 to W9, and W5.
4. Fix P2 and P3 (test-only).
