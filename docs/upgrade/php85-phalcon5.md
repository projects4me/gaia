# Upgrade to PHP 8.5 and Phalcon 5.23

> **History (agaia-4748, 2026-11-20).** Gaia runs on PHP 8.5 / Phalcon 5.23 and the rollback window is closed.
> The `next-stack` job (PHP 7.4 / Phalcon 4.1), the 7.4 entry of the `deployed-image-config` matrix and the
> `upgrade-report` job were removed from `ci.yml`; CI runs 8.5 only, with the image tag in one place (`GAIA_ENV_TAG`).
> The CI sections below (`gaia-env:current`/`next`, `gaia-current`, "CI next-stack report", the switch-over CI notes)
> describe that earlier setup and are kept as history. `tools/upgrade/` stays for now: PHPUnit tests still load it.

Notes for the move from PHP 7.4 / Phalcon 4.1 to PHP 8.5 / Phalcon 5.23. Later issues add their findings here.

## Runtime images

Both stacks are built and published from `projects4me/docker-images` (`gaia/env`, workflow `gaia-env.yml`) as public images on `ghcr.io/projects4me/gaia-env`:

- `gaia-env:7.4.21-phalcon4.1.2` (also tagged `current`): PHP 7.4.21, Phalcon 4.1.2 (with psr), Debian 10. Same stack as `rananoman/gaia:env-dev`.
- `gaia-env:8.5.12-phalcon5.23.0` (also tagged `next`): PHP 8.5.12, Phalcon 5.23.0 (no psr), xdebug 3.5.3, xhprof 2.3.10.

CI pulls them and tags them locally as `gaia-env:current` and `gaia-env:next`. The recipe and build script live in `projects4me/docker-images/gaia/env`.

### Deploys

The deploy image (`docker/dev/Dockerfile`, `ARG ENV_IMAGE`) and the local compose image (root `Dockerfile`) are built on `ghcr.io/projects4me/gaia-env:7.4.21-phalcon4.1.2` (still PHP 7.4), no longer on `rananoman/gaia:env-dev`. The image is public, so `cd-ovh.yml` needs no GHCR login. The switch-over to PHP 8.5 now only changes this tag (to `8.5.12-phalcon5.23.0`).

Rollback: revert the PR, so the next deploy builds on `rananoman/gaia:env-dev` again. For an immediate fix, run `helm upgrade` with `--set image.tag=<previous branch tag>`.

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

## Deployed image never displays PHP errors

The next stack logs thousands of warnings and deprecations. If `display_errors` were on in the deployed image they
would land inside JSON responses and break Prometheus. `docker/dev/Dockerfile` therefore copies
`docker/php/conf.d/production.ini` (`display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`) last, so no
base image can override it. The `deployed-image-config` CI job builds the deployed image on `gaia-env:current` and
`gaia-env:next` (the Dockerfile takes `ENV_IMAGE`) and runs `tools/ci/check-php-display-errors.sh <image>`, which
reads the effective config with `php -i` and fails if `display_errors` or `display_startup_errors` is on or
`log_errors` is off. To see it fail, set `display_errors = On` in `production.ini`. The check on `next` does not
block the PR, like the rest of the next-stack work.

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

Every PR runs a non-blocking `next-stack` job in `.github/workflows/ci.yml`. The job pulls
`ghcr.io/projects4me/gaia-env:8.5.12-phalcon5.23.0` as `gaia-env:next`, starts it as the `gaia-next` container on the host network and runs each
step inside it. It has its own vendor cache key. Only `api-testing` (the current stack) blocks the PR. The job
loads the baseline dump, runs `migration migrate --dry-run` and then `migrate`, takes a schema snapshot, prepares
`pr4m_test`, runs PHPUnit (JUnit), runs `tools/upgrade/lint-next.sh` in place (`IMAGE=none`), and runs the
api-tester `client`, `backend` and `acl` modes with `--record-bodies` against Apache with `diagnostics.ini` loaded. Every
check step has `continue-on-error`, so the job stays green when something fails on the next stack and the failure
shows in the summary instead; the job only fails when the image or container can't be set up. It records each
check step's outcome in `upgrade/steps.txt` and uploads everything as the `upgrade-next` artifact. `api-testing`
uploads its schema snapshot and api-tester reports (with bodies) as `upgrade-current`.

The blocking `graph-drift` and `api-testing` jobs pull `gaia-env:current` (`7.4.21-phalcon4.1.2`) the same way, start it as the `gaia-current` container and run their steps inside it, so a stack change only
changes the image tag. `upgrade-report` and `cache-dependencies.yml` use the same `7.4.21-phalcon4.1.2` image. The vendor cache keys use the image tag.

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

This section triages the next-stack report from CI run 44529979343 (agaia-4719). That run tested PR #324
(agaia-4717: `logs/tests`, `durationMs` ignored, `pg_attver` lines dropped, client mode compared) merged into `dev`
after agaia-4718 (`migrate --dry-run` counts the extensions it plans) and agaia-4720 (PHP 8 null deprecations in
core) had merged. It replaces the first triage (run 44518423955, agaia-4706). Every entry in the report belongs to
one group below, and the group counts add up to the report's totals.

- "Both stacks" means the fix also runs on PHP 7.4 / Phalcon 4, so it can merge before the switch-over.
- **Blocks** marks the groups to fix before the switch-over: the next stack would break a user flow, or a check
  that blocks PRs would fail once it runs on the next stack. The other groups can follow it.

| Report section | Total | Groups |
|---|---|---|
| PHPUnit (1451 tests, as on current) | 2 errors, 0 failures | P2, P3 |
| api-tester `backend` / `acl` | 0 failures (220 and 248 passed, as on current) | none |
| api-tester `client` | 13 failures (9 on current) | A1 (3), A2 (1), C1 (8), C2 (1) |
| Response differences | 667 (client 184, backend 178, acl 305) | A1 (16), A2 (4), R2 (638), R3 (9) |
| Dry run | 0 errors, 12 statements planned | none (D1 fixed by agaia-4718) |
| Schema diff | 0 lines | none (S1 fixed by agaia-4717) |
| Deprecations and warnings | 1569 in the summary, plus 4235 HTTP entries it leaves out (all Gaia code, 0 vendor) | R4, W1, W3 to W9 |
| Failed next-stack steps | `phpunit`, `api-tester` | P2, P3, A1, A2, C1, C2 |

### PHPUnit

- **P1. Fixed (agaia-4717).** With `logs/tests` created, the 60 tests it hid all pass on the next stack: the
  run has 1451 tests on both stacks, and the only errors are P2 and P3. They raised no type error and no new
  deprecation group (their entries are in W4, W6 and W9).
- **P2. Fixed (agaia-4726, merged after this run). `RouterTest::testAppRouteRegistersConfiguredControllerAndAction`: 1 error.** In Phalcon 5,
  `Router::handle()` reads the `request` service and throws `RequestServiceUnavailable` when the router has no DI
  container. Phalcon 4 didn't need a container here. The app's router always has one, so only the test is
  affected. Fix: give the router a DI container before `handle()`. File: `tests/foundation/mvc/RouterTest.php:52`.
  Both stacks. Size: small. **Blocks** (the PHPUnit check).
- **P3. Fixed (agaia-4726, merged after this run). `IssuestatusTest::testMissingTextColorDefaultsToLight`: 1 error.** The test builds `Issuestatus` with
  `newInstanceWithoutConstructor()` and sets `textColor = null`. In Phalcon 5, `Model::__set()` treats a `null`
  value as a possible relation reset and calls `getRelationByAlias()` on the models manager. A model built without
  its constructor has no manager, so the call fails. Fix: build the model through its constructor with the test
  DI. File: `tests/models/IssuestatusTest.php:128`. Both stacks. Size: small. **Blocks** (the PHPUnit check).
  Real models take the same `__set(null)` path, but they have a manager, so it doesn't fail there.

### api-tester client mode

Client mode now runs on both stacks. 4 cases fail only on the next stack (A1, A2); 9 fail on both (C1, C2).

- **A1. An anonymous save throws `Error` on PHP 8: 3 failures, 16 differences.**
  `modifiedUserBehavior::getCurrentUser()` (`core/mvc/models/behaviors/modifiedUserBehavior.php:76`) sets
  `$currentUser->id = 'system'` when the global `$currentUser` has no id. On an anonymous request that global is
  `null`. PHP 7.4 creates an object from `null` with a warning ("Creating default object from empty value"), which
  `error_reporting(E_ERROR)` hides. PHP 8 throws `Error: Attempt to assign property "id" on null` (checked on both
  images). `PhalconError` turns it into an empty 500 (`internalServerError`). Every anonymous `save()` that doesn't
  go through `ActingUser` hits it:
  - `POST /api/v1/forgetpassword` (`nosmtp-forgot-password-succeeds`: 200 → 500).
    `Email::saveResetToken()` saves the user's reset token. `Email::resetPassword()` catches only `\Exception`,
    so the `Error` escapes it. On PHP 8 nobody can reset a password.
  - `POST /api/v1/serviceenrollment` with an `enrollmentToken` (`serviceenrollment-token-approves-at-once`:
    201 → 500, `serviceenrollment-token-is-single-use`: 400 → 500). `ServiceEnrollmentTokens::redeem()` saves the
    spent token. The transaction rolls back, so the token isn't spent and the second case sees the same 500.
    Requests without a token pass, because `ServiceEnrollments` saves them through `ActingUser`.

  Fix: return the system user (`User::getSystemUser()`, as `ServiceEnrollments` does) instead of writing to the
  global, and add a PHPUnit test for an anonymous save. Both stacks. Size: small. **Blocks.** Check the other
  `global $currentUser` readers (`createdUserBehavior` and the other 25 files that read it) for the same pattern while there.
- **A2. `GET /translations/%2E%2E` is answered by Apache, not Gaia: 1 failure, 4 differences.** On the next image,
  Apache decodes `%2E%2E` and resolves `/translations/..` to `/` before routing, so it serves the static
  `public/index.html` (200, `text/html`) instead of passing the request to Gaia, which answers 404 "Locale not
  supported" on the current image. The Apache log has no PHP entry for the request. Nothing leaves the document
  root, so this isn't a traversal. Fix: accept a 404 or this static page in `get-translations-dot-dot-locale-404`
  (the PHPUnit tests cover Gaia's own locale check), or set Apache to the current image's behaviour. Size: small.
  Can follow the switch-over.
- **C1. Secure settings need `SETTINGS_ENCRYPTION_KEY`: 8 failures, both stacks.** CI doesn't set the key, so
  `settings-patch-secure-value-is-masked` gets 422 "Secure settings cannot be saved: SETTINGS_ENCRYPTION_KEY is not
  configured." The SMTP password is never saved, and the 7 cases that read it or test the SMTP connection with it
  fail too (`settings-*-secure-*`, `settings-test-*`). Fix: set a test key in both jobs. CI only, not part of the
  upgrade. Can follow.
- **C2. `conversationroomcount-vote-room-tallies`: 1 failure, both stacks.** The case expects
  `lastCommentDate` `2026-01-02 12:00:00+00`, and CI returns `2026-01-02 12:00:00`. Same on both stacks, so not
  part of the upgrade: either the case or the view's date type is off. Can follow.

### Response differences

- **R1. Fixed (agaia-4717):** `durationMs` is ignored.
- **R2. The two jobs run the api-tester modes in a different order: 638 differences.** `api-testing` runs
  `--mode backend,acl` and then `client` in a second step. `next-stack` runs `--mode client,backend,acl`. All
  modes write to the same `pr4m_test`, so on the next stack `backend` and `acl` see the records `client` created,
  and on the current stack `client` sees the ones `backend` and `acl` created. Every difference outside A1, A2
  and R3 is this kind: list lengths and `meta.count` (more or fewer records), values and value types at
  `$.data[i]` and `$.included[i]` that belong to another record at that position, `issueNumber` sequences shifted
  by one, and attributes another mode wrote (an issue `description` of `acl-field-desc-written`, `dateModified`,
  `lastActivityDate`, a token's `lastUsedAt`). All 85 `POST /api/v1/bulk` differences are of this kind, inside the
  `bulk-task-board-batch` board list. Before client mode was added, `backend` and `acl` ran in the same order on
  both stacks and their bodies matched apart from R1. 155 in client, all 178 in backend, all 305 in acl. Fix: run
  the modes in the same order on both stacks (in `next-stack`, `backend,acl` and then `client` in a second step).
  File: `.github/workflows/ci.yml`. CI only. Size: small. Can follow, but fix it before relying on the comparison
  again: until then a real difference can hide among these.
- **R3. Clock and random values: 9 differences, all client.** `POST /api/v1/introspect` `$.exp` (4: the token
  expiry is issue time + lifetime) and the enrollment `userCode` (5: random per request). Fix: ignore `exp` and
  `userCode` in the comparison, as it ignores `durationMs`. File: `tools/api-tester/src/IgnoreList.php`.
  Size: small. Can follow.
- **Bulk without a body (agaia-4694).** No case sends a `/bulk` sub-request without a body: the `bulk` cases'
  sub-requests are GETs, or writes with a body. No bulk response differs between the stacks apart from R2. The
  decoding difference (a call without a body is `null` on Phalcon 4 and `{}` on Phalcon 5) is untested by the
  cases, not observed.

### Deprecations and warnings (Gaia code)

Counts are log entries, one per occurrence. On PHP 8.5 these are only logged; most become errors in PHP 9. None is
in vendor code.

- **R4. The summary leaves out the HTTP entries.** On the next stack Apache's PHP messages go to the Apache log
  (`upgrade/apache.log`, 4235 entries), not to `logs/php-diagnostics.log`, which holds only the api-tester's
  `curl_close()` entries. The file is most likely created by the api-tester CLI (root) before Apache (`www-data`)
  first writes to it, so PHP falls back to the Apache log. `compare-stacks.sh` doesn't read the Apache log, so the
  summary's 1569 entries are PHPUnit, lint and the api-tester only. The HTTP counts below come from running
  `deprecations-report.php` on the Apache log with its prefix stripped. Fix: create `logs/php-diagnostics.log`
  writable by `www-data` before the api-tester runs (or give the CLI its own log), or have `compare-stacks.sh`
  read the Apache log. CI only. Size: small. Can follow.

| Group | HTTP | Summary | Where (top locations) | Both stacks | Size |
|---|---|---|---|---|---|
| W1. `null` passed to a typed parameter | 2 | 0 | `strpos()` and `substr()` on a `null` project name, `core/mvc/models/behaviors/shortCodeBehavior.php:54,90` (a project created without `name`). The other sites were fixed by agaia-4720 (`create_guid()`, `Builder::having()`, `explode()`, `str_replace()`). | yes | small |
| W2. `null` as an array offset | 0 | 0 | Fixed by agaia-4720 (`core/mvc/router.php`). | | |
| W3. Missing array keys, and `null` read as an array or object | 2656 | 0 | `Undefined array key` for query params (`where`, `sort`, `order`, `rels`, `fields`, `meta`) in `core/mvc/models/Query.php:196,197,295`, `Model.php:195,196,199`, `query/Meta.php:241,262`, `IssuementionComponent.php:39` (1687); `foreach` over `null` in `Relationship.php:151,166,196`, `query/Meta.php:241,301`, `relationships/HasManyToMany.php:77`, `IssuementionComponent.php:42` (934); array offset on `null` in `RestController.php:868,1036,1086` and `IssuementionComponent.php:39` (32); property of `null` in `ProjectActivitiesComponent.php:228` (3) | yes | medium (default the query params once in `Model` or `Query`) |
| W4. Dynamic properties | 1441 | 408 | core: `query/Meta.php:124,150` (928), `Relationship.php:91` (500), `relationships/HasMany.php` (9), `HasManyToMany.php` (3), `HasOne.php` (1); tests: PHPUnit mocks of `ModelInterface` (332, e.g. `tests/models/behaviors/WorkflowModelStub.php:35,41,48`), `FakeTokenModel` (45), stubs in `WorkflowTriggersComponentTest` and `WorkflowRepliesComponentTest` (31) | yes: declare the properties, or `#[\AllowDynamicProperties]` on test stubs | medium |
| W5. `curl_close()` is deprecated | 0 | 906 | `tools/api-tester/src/HttpClient.php:60` (test tooling, not the API) | yes: call it only below PHP 8 | small |
| W6. Implicitly nullable parameters (`Type $x = null`) | 47 | 137 | 53 parameters in lint, mostly in `core/libs/workflows/`, `core/libs/mentions/MentionRecorder.php`, `core/libs/authorization/ServiceEnrollments.php`, the `mentions` and `searchIndex` behaviours, `query/Clause.php`. agaia-4724 (`?Type` in `core/libs/workflows/`) merged after this run. | yes: `?Type` works on PHP 7.1+ | medium (mechanical) |
| W7. Array to string conversion | 67 | 7 | `public/index.php:130` (67: the debug DB logger joins bind values, some of them arrays); `tools/gaia-dev/src/Query/ModelQuery.php:139` (7) | yes | small |
| W8. Undefined model property read through `Model::__get()` | 22 | 0 | `ideaScreensBehavior.php:85,99` (`Ideamessage::screens`, 6); `doccontentBehavior.php:101,104` (`Doccontent::state`, 4); `ProjectActivitiesComponent.php:147,226` (`Membership::relatedId`, `roleId`, 6); `RestController.php:1073` (`deleted` on `Membership`, `Vote`, `Tagged`, 5); `shortCodeBehavior.php:43` (`Project::name`, 1). Phalcon 4 raises the same notice; `error_reporting(E_ERROR)` hid it. | yes | small |
| W9. `ReflectionMethod::setAccessible()` and `ReflectionProperty::setAccessible()` are deprecated | 0 | 111 | tests only (40 method, 71 property) | yes: call them only below PHP 8.1 | small |
| W10. `fopen(logs/tests/db.log)` fails | 0 | 0 | Fixed with P1. | | |

Totals: HTTP 2 + 2656 + 1441 + 47 + 67 + 22 = 4235; summary 408 + 906 + 137 + 7 + 111 = 1569.

None of these groups blocks the switch-over: with `display_errors` off they are only logged. A1 is the one place
where a PHP 8 engine change (not a deprecation) breaks a request.

**Typed Phalcon 5 APIs passed the wrong type.** None in this run. The `Builder::having(null)` call from the first
triage is fixed (`core/mvc/models/Query.php:143` calls it only with a value), the 60 tests P1 hid raised no type
deprecation and no `TypeError`, and no `TypeError` reached the api-tester or PHPUnit results. The shim aliases
(`Phalcon\Di`, `Phalcon\Config`, ...) logged nothing.

### For Prometheus

Apart from A1 and A2, no response changes for a reason other than R2 and R3. Still to check on the UI side:

- A1: password reset and token enrollment return a 500 on the next stack until it is fixed;
- that `public/index.php` keeps `display_errors` off in production, so the W messages can't end up in JSON bodies.

### Next steps

1. Fix A1 (it blocks the switch-over; P2 and P3 were fixed by agaia-4726).
2. Fix R2, R3 and R4, then run the report again: the response differences should then be empty and the
   deprecations complete.
3. Fix C1 and C2 (CI and test data, both stacks) and A2.
4. Fix W3 and W4 (most entries), then W1, W6 to W9, and W5.

## Guards that go away at the switch-over

- `curl_close()` in `tools/api-tester/src/HttpClient.php` and `HermesObserver.php` runs only when `PHP_VERSION_ID < 80000` (it is a no-op since PHP 8.0 and deprecated in 8.5).
- `setAccessible()` in the tests goes through `gaiaTestMakeAccessible()` in `tests/TestHelper.php`, which calls it only when `PHP_VERSION_ID < 80100`.
- Both guards, and the helper, go away when PHP 7.4 is dropped.

## Switch-over

Deploy and CI move from `gaia-env:7.4.21-phalcon4.1.2` to `gaia-env:8.5.12-phalcon5.23.0`. The code still boots on
7.4 (`composer.json` keeps `~7.4.0 || ~8.5.0`, the Phalcon 4 shim in `core/libs/compat/` and the `PHP_VERSION_ID`
guards stay), so during the rollback window a rollback is only an image swap.

CI: `graph-drift`, `api-testing` and `deployed-image-config` run on 8.5; the `next-stack` job now runs the same checks on
the 7.4 image and also blocks, so new code can't break 7.4. `deployed-image-config` checks both stacks.

### Runbook (dev first, qa later)

1. Freeze merges to dev and pause Agamemnon.
2. Back up the dev database (`pg_dump`). Note the last 7.4 deploy image: `rananoman/gaia:<branch>` of the last PR merged
   before the switch, as shown in its cd-ovh run.
3. Merge. cd-ovh builds on the 8.5 image and runs `helm upgrade`.
4. Check the pod: it reaches Running; the logs show "Database migrations completed"; `php -v` shows 8.5.12; no fatal
   errors in the Apache error log.
5. Smoke test: log in, list and search issues, create and edit an issue, comment, @mention, upload, role/permission
   screens, ideas/plans, forgot password, service enrollment token, a Hermes live update, and one cron/workflow run.
6. Watch the logs for a day, then resume merges and Agamemnon. Repeat for qa by merging dev into qa.

### Rollback

- **Fast (minutes):** run the `Rollback Gaia | OVH` workflow (`.github/workflows/rollback-ovh.yml`) with the last 7.4
  deploy tag, or run `helm upgrade gaia-dev . -f values-dev.yaml --set image.tag=<last 7.4 tag>` on the host.
  `helm rollback` does not work, because `values-dev.yaml` uses the moving `dev-latest` tag with `pullPolicy: Always`.
  Keep merges frozen, because any merge redeploys 8.5.
- **Lasting:** revert the switch PR on dev. CI and cd-ovh then rebuild `dev-latest` on `gaia-env:7.4.21-phalcon4.1.2`,
  the image that has run since agaia-4745. Then run `helm upgrade` without `--set` to return to `dev-latest`, and
  resume merges.
- **Database:** no restore expected. The switch changes no metadata, every mock found 0 schema differences, and data
  written by 8.5 (bcrypt hashes, JSON, serialize, OAuth tokens) reads the same on 7.4. Restore the backup only if data
  is damaged; that loses anything written after the switch.

### `rollback-ovh.yml` inputs

| Input | Meaning |
|---|---|
| `image_tag` | The `rananoman/gaia` tag to deploy: the last 7.4 deploy tag. Added as `--set image.tag=<image_tag>`. |
| `environment` | `dev` or `qa` (default `dev`): the `gaia-<env>` release and `values-<env>.yaml`. |

It uses the same secrets as `cd-ovh.yml`.
