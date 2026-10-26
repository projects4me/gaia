# Gaia change bundles

A *bundle* is a set of files that change together for one kind of work. This catalog was built from the merged
Gaia PRs #138–#203 (file sets and review threads from the reviewer and CodeRabbit) and from the Talos agent runs on
Gaia issues (4017–4122). For each bundle it gives the files in the order you touch them, the golden reference files
to copy from, the exemplar PR, and the checks learned from reviews and from agent runs that got stuck.

The scaffolders and generated docs are built from this catalog. Templates must be **derived from the golden files
at generation time**, not kept as static copies: core files (`RestController`, `Acl`, router, the api-tester
generator) changed in about a third of the PRs, so a static template goes stale.

Paths are relative to the repo root. `T/` stands for `tools/api-tester/`.

## Ranking (frequency × cost)

Frequency counts merged PRs #138–#203. Cost combines diff size, review rounds, follow-up PRs and agent turns lost.
Work the scaffolder issues in this order.

| Rank | Bundle | PRs | Cost per occurrence | Payoff | Scaffold or checklist |
|---|---|---|---|---|---|
| 1 | [A. api-tester cases and seed](#a-api-tester-cases-and-seed) | ~25 (17 of the 24 merged PRs #178–#203) | Medium: 100–600 lines, most of the stuck agent turns (P2, P4, P5) | Highest | Scaffold the seed, fixture and case skeletons |
| 2 | [B. New resource](#b-new-resource) | 6 (#140, #159, #165, #178, #193, #196) | High: up to +2100 lines and 8 review events (#193); fixed 10-file order | High | Scaffold (generator) |
| 3 | [D. Model behaviour + unit test](#d-model-behaviour--unit-test) | 6 (#178, #193, #195, #196, #201; #161 minor) | Medium-high: the CodeRabbit concurrency and snapshot findings land here | Medium-high | Scaffold the behaviour and test pair |
| 4 | [C. Custom logic in a REST action](#c-custom-logic-in-a-rest-action) | ~9 (#152, #169, #180, #181, #190, #192, #198, #199; #183 reshaped) | Medium; design pushback when it turns into a non-REST endpoint (#183) | Medium | Checklist plus a test-case scaffold |
| 5 | [E. Live events per resource](#e-live-events-per-resource) | 3 (#173, #194, #202) | Medium: 5 files every time, easy to miss one | Medium | Scaffold |
| 6 | [U. Upload scope for an attachable entity](#u-upload-scope-for-an-attachable-entity) | 3 (#195, #199, #203) | Low-medium; binary fixture and seed problems (P4) | Low-medium | Checklist |
| 7 | [F. Notification module](#f-notification-module) | 4 (#141, #165, #197, #202) | Low per file, but spread over 5 modules | Low | Checklist |
| 8 | [M. Metadata ACL sweep across resources](#m-metadata-acl-sweep-across-resources) | 2 (#167, #175) | Medium: the same 3–4 lines in 11–12 metadata files | Low (rare) | Loop script when needed |
| — | [G. Framework or core change](#g-framework-or-core-change) | ~20 (#149, #153, #161–#176, #179, #184–#186, #201) | High; ACL work has the most follow-ups | n/a | Checklist only; never templated |
| — | [H. Schema and migration infrastructure](#h-schema-and-migration-infrastructure) | 6 (#139, #147, #160, #162, #192, #193) | High when it happens | n/a | Checklist only |

Rank is frequency × cost. A is cheap per PR but almost every PR touches it, and it causes most of the lost agent
turns. B is rarer but the largest diff and has a fixed order, so it is the best fit for a generator. G and H are
common or costly, but each change is different, so they get checklists rather than scaffolds.

## Cross-cutting checks (every bundle)

These came up in agent runs on several bundles. They apply before any bundle-specific check.

1. **Tests run against this checkout.** The shared `gaia-dev` and `gaia-test` containers may mount another
   checkout. Results from `ai test` and the api-tester mean nothing until you confirm the mount. Use
   `p4m test` / `ai test` or a throwaway container, never `docker exec` on the shared ones. Issues 4017, 4027,
   4075 and 4090 each lost 10–20 turns to this.
2. **Schema before api cases.** A new column or table needs the migration run from this checkout, then
   `T/harness/prepare-test-db.sh`. A `200` response with a non-JSON body is a PHP fatal: read `bodyPreview` for
   "column … does not exist" before changing code (4090, 4027). If the local migration can't run, report it as
   blocked early. Don't spend 100 turns on it.
3. **Write PHP and SQL with the Edit or Write tools.** Heredocs and inline Python lose backslashes in namespaces
   (4090, 4027).
4. **Line endings.** Shell scripts and fixtures must be LF (`.gitattributes` since #191). Check new files with
   `git ls-files --eol`.
5. **Repo, dependencies, existing code.** At the start, confirm the issue belongs in Gaia, its "Depends on" issues
   are merged, and the code isn't already on dev (4026, 4029, 4114, 4121, 4123).
6. **REST only.** Use standard CRUD routes. No custom endpoints, test-only actions or test hooks in the system under
   test (reviewer on #183). Express extra behaviour as a field or flag on the normal write: #190's `test` flag on
   the settings save, #198's mark-as-read through PATCH.
7. **No permission seeds.** New modules get their permissions granted manually by the reviewer. Don't ship seed SQL
   for role permissions (#193, 4069). Test-only permission rows go in the harness (bundle A).

---

## A. api-tester cases and seed

Every PR that changes API behaviour adds cases. A new or changed scope rule also needs harness seed rows and fixture
ids.

**Exemplar:** #193 (full set for 4 resources). #179 for an ACL-only rule. #198 for client cases with a new
assertion.

**Touchpoints, in order:**

1. `T/harness/prepare-test-db.sh`:
   - add the table to the reset list (`('idea'), ('ideamessage'), …` near line 538);
   - add test-role permission rows (`atp-acl-mem-<x>-get`, `-create` for `api-test-role-acl-mem`, near line 654);
   - add seed rows, one in the member's project and one in another project
     (`INSERT … ON CONFLICT (id) DO UPDATE SET deleted = FALSE`, near line 779).
2. `T/fixtures/default.json`: `<x>Id` and `otherProject<X>Id` placeholders pointing at the seed ids
   (`api-test-<x>-NNNNNN`, same width as the sibling ids).
3. `T/apis/acl.json`: role-scoped cases. Cover member allowed, non-member denied, no permission denied, and the
   field or scope rule.
4. `T/apis/client.json`: behaviour cases, positive and negative (malformed body, validation error).
5. `T/src/Asserter.php`: only when a new assertion kind is needed (#182, #190, #198, #199).
6. `T/README.md`: one line in the catalog when a new case family or assertion is added.

**Variants:**
- Mail delivery: `T/apis/mail.json`, `MailSink.php` and the SMTP sink in docker-compose (#187, #190).
- Binary file fixtures: `T/fixtures/files/` (#199).
- Live smoke: `T/apis/live.json` and the harness `hermes-wait` (#173).

**Golden references:**
- The idea blocks in `T/harness/prepare-test-db.sh`
- The idea keys in `T/fixtures/default.json`
- The `idea*` cases in `T/apis/acl.json` and `T/apis/client.json`

**Checks:**
- Every new permission or scope rule gets an `acl.json` case in the same PR (pattern in #179, #182, #185, #203; the
  missing case was a follow-up on 4075). The failed #177 → #178 resubmission and the #170 → #165 follow-up came from
  ACL changes without matching cases.
- Assert the body as well as the status. Without a shape or id expectation, `Asserter::assert` does no body
  validation. Use `idsIncludes` for list scopes (CodeRabbit on #203), and assert body and headers for file responses
  (CodeRabbit on #199).
- Match each seed value to its column type: `deleted` is boolean on ideas but smallint on issues (4069, 4075). Check
  the table before writing SQL.
- A seed row that references a user must use an id that exists in every seeded DB. The harness seeds some tables
  before the users exist (CodeRabbit on #196, `db/seeds/translations.sql`).
- Run any new validation rule against the existing fixtures and seeds (4075: new checks rejected the fixture data).
- A unique-key fatal on a re-run usually means leftover soft-deleted rows. Re-run `prepare-test-db.sh` and compare
  with the baseline before blaming the diff (4075).
- The api-tester can't send multipart. Seed upload rows and commit the file under `T/fixtures/files/` (4106, #199).
- Run `prepare-test-db.sh` from the host. A Windows temp path inside a container fails `pg_dump` (4017).

---

## B. New resource

A new model exposed as a standard REST resource.

**Exemplar:** #193 (Idea, Ideamessage, Ideaplan, Ideaplanissue). #178 (Setting, with seed data and a behaviour).
#196 (Translation, with custom read routes).

**Touchpoints, in order:**

1. `app/metadata/model/<X>.php`: fields, indexes, relationships, `behaviors`, and `acl` (`'groups' => ['Project']`
   for project-scoped data).
2. Parent metadata: add the relationship on the parent (User in #159, Issue in #140, Project in #193).
3. `app/models/<X>.php`: an empty 22-line class extending `Gaia\Core\MVC\Models\Model`.
4. `app/api/v1/controllers/<X>Controller.php`: an empty subclass of `RestController`.
5. `core/config/models.php`: one `'<X>',` line.
6. `core/config/routes.php`: `'<x>' => ['path' => '/api/:version/<x>', 'allowedMethods' => […], 'identifier' => 'id']`.
7. Run the migration from this checkout (cross-cutting check 2).
8. `T/sources/server-routes/routes.v1.json`.
9. `T/bin/generate-backend-apis.php`: add the id placeholder (`'<x>' => '{<x>Id}'`), the FK placeholder
   (`'<x>Id' => '{<x>Id}'`) and the required-field flags. Then regenerate `T/apis/backend.json`.
10. Bundle A: harness table and seed rows, fixture ids, `acl.json` and `client.json` cases.
11. Optional, only when needed:
    - Bundle D, a behaviour.
    - Bundle E, live events.
    - `db/seeds/<x>.sql` plus its harness `psql_db` line for reference data (#178, #180, #196).
    - `core/libs/security/Acl.php` when the project scope is path-bound through a parent (#193, #201).

**Golden references:**
- `app/metadata/model/Idea.php` and `Ideamessage.php` (child with a behaviour and `acl.groups`)
- `app/models/Idea.php`
- `app/api/v1/controllers/IdeaController.php`
- The `idea` entry in `core/config/routes.php` (line 107)
- For custom read routes, the `translations` and `translation` entries (line 476), which use the `controller` and
  `action` keys (these depend on #186)

**Checks:**
- Don't change shared models such as `app/models/Project.php` unless the feature needs it (reviewer on #193).
- No permission seed SQL (cross-cutting check 7).
- Catalog drift: `backend.json` must be regenerated, not hand-edited. `routes.v1.json` must match `routes.php`.
  `generate-backend-apis.php` needs the placeholder maps, or the generated cases send literal ids (#165, #188,
  #193).
- A multi-column unique index needs support in `core/libs/meta/migration.php` (added in #193). Concurrent creates
  must not depend on a read-then-write, so allocate atomically or retry on conflict (CodeRabbit on #193,
  `planVersionBehavior`).
- New resource files are not mapped by `ai test --changed` (`ProjectIdResolver`, behaviours). Run the full suite
  before shipping (4090).

---

## C. Custom logic in a REST action

Behaviour added inside an existing controller's standard action or hook (`postAction`, `getAction`, `patchAction`,
before and after hooks). It is not a new route. The issue's original wording, "custom action + route", turned out
to be the anti-pattern the reviewer rejects (#183). The accepted form reuses the REST verbs.

**Exemplar:** #180 (`IssuestatusController`: colour and sort order, block deleting a status in use). Also #198
(`SystemnotificationrecipientController` plus a model method, mark read by entity).

**Touchpoints, in order:**

1. `app/api/v1/controllers/<X>Controller.php`: override the action or hook.
2. Optional: a model method in `app/models/<X>.php` (#198), or a library class under `core/libs/…` that holds the
   logic, so the controller stays thin (#190: `TestEmailSender`, `MailerErrorMapper`).
3. Optional: metadata field changes (#180, #192).
4. Bundle A: `acl.json` and `client.json` cases, harness seed rows, and an Asserter or README line if needed.
5. A unit test for the library logic.

**Golden references:**
- `app/api/v1/controllers/IssuestatusController.php`
- `app/api/v1/controllers/SystemnotificationrecipientController.php`
- `app/api/v1/controllers/IssuetypeController.php` (computed read field, #181)

**Checks:**
- No new public endpoint or test-only action. Express it as a flag or field on the normal verb (reviewer on #183;
  #190 is the accepted shape).
- Logic that can be unit tested belongs in a class with a unit test, not only an api case (reviewer on #183).
- Put each dependency in the layer that owns it: SMTP settings are read inside the Mailer, not in the notification
  class (reviewer on #183).
- Loops over `update()` must stop on `false` and report it. Use a transaction. Don't return 200 for a partial write
  (CodeRabbit on #198).
- When overriding `getAction`, compare it with `RestController::getAction`. `Model::read()` returns
  `['baseModel' => rows]`, not a list, and #199 fixed a 404 on every upload caused by `$data[0]` (4106). Check the
  stored file is readable before returning success (CodeRabbit on #199).
- Request overrides must not reuse stored secrets. A changed host, port or user requires the password again
  (CodeRabbit on #190, `SmtpSettings`).

---

## D. Model behaviour + unit test

Model-level rules (validation, derived fields, versioning, cache invalidation) live in a Phalcon behaviour registered
in the model's metadata.

**Exemplar:** #195 (`ideaScreensBehavior`). Also #193 and #201 (`planVersionBehavior`).

**Touchpoints, in order:**

1. `core/mvc/models/behaviors/<name>Behavior.php`.
2. `app/metadata/model/<X>.php`: add the behaviour to the `behaviors` array. Order matters: `settingBehavior` must be
   first in `Setting.php`.
3. `tests/models/behaviors/<Name>BehaviorTest.php`.
4. Optional: a model override (`app/models/Ideaplan.php` saves in a transaction so the behaviour's lock holds,
   #201).
5. Bundle A cases for the API-visible effect (validation error, derived field).

**Golden references:**
- `core/mvc/models/behaviors/ideaScreensBehavior.php` with `tests/models/behaviors/IdeaScreensBehaviorTest.php`
- `core/mvc/models/behaviors/planVersionBehavior.php` with `tests/models/behaviors/PlanVersionBehaviorTest.php`
  (concurrency)

**Checks:**
- `ai test gaia --changed` and the full `ai test` don't run `tests/models`. Run the new test file directly with
  `dev-tools/installer/p4m test phpunit <file>` and report it, because the suite stays green without it (4075, 4090,
  4117).
- Mock `ModelInterface` with `addMethods` for methods it doesn't declare (`getSnapshotData`) (4090).
- Read the pre-update value with `getOldSnapshotData()`. The test mock must return different current and old
  snapshots (CodeRabbit on #196).
- Check JSON input's top-level type before decoding to an associative array, and add near-miss tests (CodeRabbit on
  #195).
- Reject empty required references, not only missing ones (CodeRabbit Major on #201, `ideaReferencesBehavior`).
- Allocation and invalidation must be atomic: use a lock or retry on unique conflict, and check the write result
  (CodeRabbit on #193 and #196).
- A rule repeated in a behaviour and in a library is a design finding. Keep one owner (4017: `SmtpSettings::overlay`
  duplicated `settingBehavior`).

---

## E. Live events per resource

Publish create, update and delete events for a resource to Hermes.

**Exemplar:** #194 (ideas, messages, plans, plan issues). The first full set was #173. #202 shows extending an
existing component's payload.

**Touchpoints, in order:**

1. `app/api/v1/controllers/components/events/<X>LiveEventsComponent.php`, extending `LiveEventsComponent`.
2. `app/api/v1/controllers/<X>Controller.php`: `public $uses = array('Events\\<X>LiveEvents');`.
3. `app/api/v1/controllers/components/events/support/EventNames.php`: the event-name constants.
4. `app/api/v1/controllers/components/events/support/ProjectIdResolver.php`: how to find the project for the
   resource (also needed by bundle U).
5. `tests/api/controllers/components/events/LiveEventsComponentsTest.php`.
6. New event names: `hermes/src/contract/names.js`, in a linked Hermes PR.

**Golden references:**
- `app/api/v1/controllers/components/events/IdeaLiveEventsComponent.php` for a plain resource
- `IdeaplanLiveEventsComponent.php` for extra payload
- `NotificationLiveEvents.php` for payload fields from related records (#202)

**Checks:**
- Event names in `EventNames.php` must match `hermes/src/contract/names.js`. The payload is a contract with Hermes
  and Prometheus, so a new name or payload change ships as linked PRs in both repos.
- Publishing is fire-and-forget. Keep the short fractional `connect_timeout` so a down Hermes doesn't block the API
  response, and call methods directly, not through `call_user_func` (reviewer on #173).
- `ProjectIdResolver` isn't mapped by `ai test --changed`. Run the full suite (4090).

---

## U. Upload scope for an attachable entity

Let uploads attach to a new entity and resolve their project for ACL.

**Exemplar:** #195. Also #199 (GET serves the file) and #203 (issue and wiki uploads).

**Touchpoints, in order:**

1. `app/metadata/model/Upload.php`: the `paths` binding for the new related type.
2. `.../events/support/ProjectIdResolver.php`: one line for the type.
3. `app/api/v1/controllers/UploadController.php`, only if read or serve behaviour changes.
4. Bundle A:
   - an upload seed row per project in `prepare-test-db.sh`;
   - a binary file in `T/fixtures/files/`;
   - `<x>UploadId` and `otherProject<X>UploadId` in `default.json`;
   - `acl.json` cases for member allowed, non-member denied, and GET by id.

**Golden references:**
- `app/metadata/model/Upload.php`
- The idea upload rows in `prepare-test-db.sh` (near line 797)
- `T/fixtures/files/idea-screen.png`

**Checks:** see the multipart and binary seed check in bundle A, and the `getAction` checks in bundle C.

---

## F. Notification module

Changes to what system notifications carry or who receives them.

**Exemplar:** #197 (related entity on system notifications).

**Touchpoints, in order:**

1. `app/api/v1/controllers/components/notifications/modules/<X>Notification.php`, for each affected module
   (Comment, Issue, Membership, Milestone, Timelog), keeping `NotificationModuleInterface`.
2. The recipient service, when recipients change (#141, #165).
3. `app/metadata/model/Systemnotification.php`, when a field is added.
4. The event payload in `NotificationLiveEvents.php` (bundle E, #202).
5. `T/apis/client.json` cases.
6. A backfill for existing rows, if the new field must be filled for old rows (#200, still open).

**Golden references:**
- `.../notifications/modules/IssueNotification.php`
- `NotificationModuleInterface.php`

**Checks:**
- Apply the change to every module the interface covers. #197 touched all five.
- There are no unit tests for the modules yet. The only coverage is the client cases. Add unit tests when the logic
  is more than a field copy.

---

## M. Metadata ACL sweep across resources

The same 3–4-line ACL declaration added to every `app/metadata/model/*.php` (#167 group authorization, #175
`project.get` scopes).

**Exemplar:** #175.

**Touchpoints, in order:**

1. `core/libs/security/Acl.php` support.
2. Every metadata file that declares the new `acl` key.
3. `docs/architecture/rbac/*`.
4. Bundle A.

**Checks:**
- Script the sweep and list the files it skipped on purpose.
- Every resource in `core/config/models.php` is either changed or explained.

---

## G. Framework or core change

Changes to `core/mvc/controllers/RestController.php`, `core/libs/security/Acl.php`, `core/mvc/router.php`,
`core/mvc/models/Query.php` or `Clause.php`, `app/models/Permission.php`, and `core/config/system.php`. This is not
templated. Each change is different, so the bundle is a checklist.

**Exemplars:**
- #179 (an ACL rule with `AclSystemRecordTest`)
- #186 (router with `tests/foundation/mvc/RouterTest.php`)
- #185 (RestController PATCH collection with an `acl.json` regression)

**Touchpoints, in order:**

1. The core file.
2. A unit test under `tests/libraries/security/` or `tests/foundation/`.
3. Bundle A regression cases (`acl.json` for ACL, `client.json` for REST behaviour).
4. `docs/architecture/rbac/{decisions,implementation-plan}.md` for RBAC decisions.
5. `.github/workflows/ci.yml` and `docker-compose.yaml`, when a new test directory or environment variable is added
   (#160–#178).

**Checks:**
- Memoize per-request permission lookups in a static keyed by user (reviewer on #164).
- Don't store a concept the team agreed to drop (reviewer on #161).
- `Query::prepareSelectQuery` applies `limit` to the joined rows. Don't combine hasMany `rels` with `limit` when
  counting children (4075; dev-tools #4124).
- `RestController` changes reach every resource. Run the full suite through the `test-runner` agent, not just
  `--changed`.

---

## H. Schema and migration infrastructure

Gaia has no migration files. The schema is derived from metadata by `core/libs/meta/migration.php` and
`core/mvc/db/migration/Postgres.php`. This bundle covers changes to that machinery or data fixes that metadata alone
can't express.

**Exemplars:**
- #192: `Postgres.php` keeps `issues.status` in step on rename.
- #193: multi-column unique indexes.
- #162: the issue-number sequence.

**Touchpoints, in order:**

1. `core/libs/meta/migration.php` or `core/mvc/db/migration/Postgres.php`.
2. The metadata that uses the new capability.
3. The harness, when the test DB needs it (`prepare-test-db.sh`).
4. `client.json` cases.

**Checks:**
- Run the migration from this checkout with the documented `p4m db migrate` path or the throwaway-container recipe.
  An ad-hoc `php cli.php migrate` gets denied, and `php` isn't on the host PATH (4027).
- A data backfill for existing rows is its own reviewed change (#200).

---

## Smaller patterns, not bundles

- **Auth and OAuth** (#146, #149, #176): `TokenController`, `OAuthServer` and `TokenControllerTest`. Three PRs, each
  different. Treat them as G.
- **Role and permission seeding classes** (#170, #171): a `core/libs/security` class with a large unit test and an
  `acl.json` case. Treat them as G.
- **Metadata-only fixes** (#154–#158): a one-file change with no tests. Add a client case when behaviour is visible.

## Follow-ups found while building the catalog

- `ai test gaia` doesn't run `tests/models`, so behaviour tests are never run by the tooling (bundle D). This is a
  dev-tools issue.
- Gaia tests depend on which checkout the shared containers mount (cross-cutting check 1). This is a dev-tools or
  installer issue.
