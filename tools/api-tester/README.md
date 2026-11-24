# api-tester (portable, black-box)

Framework-agnostic HTTP API verification tool.

It never imports Gaia/Phalcon code and **never starts containers**. Inputs are only:

1. `--base-uri` — **required** URL of an already-running HTTP API
2. `--apis` — list of endpoints + expected assertions
3. `--fixtures` — auth + placeholder values

```text
[apis.json] + [fixtures.json] + [--base-uri]
                 │
                 ▼
            api-tester
                 │
                 ▼
         live HTTP responses
                 │
                 ▼
         pass/fail report JSON (exit 1 if any enabled case fails)
```

## Portable usage (any environment)

```bash
php tools/api-tester/bin/api-tester run \
  --base-uri https://api.example.com \
  --apis /path/to/apis.json \
  --fixtures /path/to/fixtures.json \
  --report ./output/api-tester-report.json
```

Works the same for local, CI, staging, or production — only `--base-uri` and fixtures change.

Optional: `--filter project` (OR terms with `|`, e.g. `milestone|timelog`)

## Recording bodies and comparing two runs

A case can still pass while its response has changed (for example `POST /bulk` returning an empty 200 on a new stack). To catch that, record the responses of the same catalog on two stacks and compare them.

`--record-bodies` (opt-in, also accepted by `harness/run-api-tests.sh`) adds to each case in the report:

- `endpoint`: method and catalog path (`GET /api/v1/issue/{issueId}`), used to group differences
- `response.status`, `response.contentType`
- `response.bodyFormat`: `json` when the body parses as JSON, `base64` when it is not valid UTF-8 (binary), else `text`
- `response.body`: the parsed JSON, the base64 of a binary body, or the raw text (an empty body is `""` with format `text`)

The report also records the run's `runId` (the `{runId}` fixture value).

```bash
php tools/api-tester/bin/api-tester run --base-uri http://old-stack --apis tools/api-tester/apis/client.json \
  --record-bodies --report output/old.json
php tools/api-tester/bin/api-tester run --base-uri http://new-stack --apis tools/api-tester/apis/client.json \
  --record-bodies --report output/new.json
php tools/api-tester/bin/compare-reports.php output/old.json output/new.json [--details]
```

`compare-reports.php` matches cases by `id` and prints the number of differences per kind and per endpoint (`--details` lists each one with its JSON path, e.g. `$.data[0].count`). It exits `0` when the reports match, `1` when they differ and `2` on bad arguments or an unreadable report.

Kinds of difference:

| Kind | Meaning |
|---|---|
| `missing case` | the case is in one report only |
| `outcome` | passed in one run, failed or skipped in the other |
| `not recorded` | one report has no body for the case (run without `--record-bodies`) |
| `status` | HTTP status differs |
| `content type` | `Content-Type` differs (case and spaces ignored) |
| `empty body` | one body is empty, the other is not |
| `body format` | JSON in one, text in the other |
| `key` | an object key is in one body only |
| `list length` | a JSON list has a different length (common items are still compared) |
| `value type` | same key, different JSON type: `1` vs `"1"`, `{}` vs `[]`, `null` vs `"x"` |
| `value` | same type, different value |

Ignore list (`src/IgnoreList.php`): values that change between two runs of the same stack.

- GUIDs and date-times (`2026-11-19 04:44:10`, `2026-11-19T04:44:10+00:00`) are masked inside every string and object key, so `/api/v1/issue/<guid>` still compares as a path. Date-only values are not masked: fixtures set them.
- Each report's own `runId` is masked in strings and keys.
- Values under keys matching `/token/i` are not compared; their type still is.

A report recorded with `--record-bodies` holds every response body as returned, including access and refresh tokens from login responses. Treat it as sensitive: don't commit it or publish it as a CI artifact.

Both runs should start from a freshly prepared database (`harness/prepare-test-db.sh`), as the harness does, so list lengths and counters match. The comparison logic is `src/ReportComparer.php` (unit tests in `tests/tools/api-tester/`); the bin script only parses arguments and prints.

## Mode helper (catalog picker)

`--base-uri` is required:

```bash
# backend catalog
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode backend

# ACL / group-authorization catalog
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode acl

# client catalog
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode client

# both
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode client,backend,acl

# live (Gaia → Hermes) — requires hermes-test (:9001) + --hermes-url
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode live --hermes-url http://localhost:9001

# mail (Gaia → SMTP sink) — requires the mailpit sink (:8025) + --mail-url
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode mail --mail-url http://localhost:8025

# search — needs the search fixture, loaded after prepare-test-db.sh and the other modes
./tools/api-tester/harness/seed-search.sh
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode search

# search-semantic (opt-in, local only) — the search fixture and a test API that reaches Ares (see Meaning-search mode)
TEST_SERVICE=gaia-test-ares ./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8083 --mode search-semantic

# remote
./tools/api-tester/harness/run-api-tests.sh --base-uri https://api.staging.example.com --mode backend
```

## API catalogs

- `tools/api-tester/apis/client.json`
  - tuned **client/frontend** contract (hand-maintained; optional baseline from `generate-client-apis.php`)
- `tools/api-tester/apis/backend.json`
  - **backend-only** catalog (no frontend overlay):
    1. sync `sources/server-routes/routes.v1.json` from `core/config/routes.php`
    2. for each REST resource with `app/metadata/model/*.php`, build JSON:API bodies from required writable fields
    3. emit create → get → patch → delete lifecycles with `store` for runtime IDs
    4. skip custom/auth/file/Socket.IO/ACL endpoints that are not metadata CRUD
    5. omit PUT (not implemented in `RestController`)
- `tools/api-tester/apis/acl.json`
  - **group-based ACL** and **field ACL** scenarios (hand-maintained)
  - uses fixture `authProfiles`:
    - `aclNoProject` — child modules allowed, `project.get` denied
    - `aclProjectOnly` — `project.get` allowed, `issue.get` denied
    - `aclMembers` — `project.get=2` (members); member of project-001 only
    - `aclNoConversation` — project+comment allowed, `conversationroom.get` denied
    - `aclFields` — issue+project allowed with field matrix:
      - `subject` None (0/0/0)
      - `description` write⇒read (`get=0`, create/update=1)
      - `priority` Read Only (`get=1`, create/update=0)
      - `project.name.get=0`
  - covers: group cascade deny (project / conversation); mixed rels; clause 403 (query/sort/group/having bare + related); structural FK bypass; `fields=` / default omit; write body discard; write⇒read; permission create (module + field-mode write→read→none→clear); catalog excludes structural FKs; list collapses field modes (no triples)
- `tools/api-tester/apis/live.json`
  - **Gaia → Hermes** publish smoke (opt-in; not part of portable Gaia-only runs)
  - Happy path: REST write + Socket.IO `domain:event` via live Hermes (`--hermes-url`)
  - Fail-open: REST still succeeds when Gaia cannot reach Hermes (`requireUp: false`)

- `tools/api-tester/apis/mail.json`
  - **Gaia → SMTP** delivery check (opt-in; needs `--mail-url`, not run by default or in CI)
  - Saves Settings > Email pointing at the Mailpit sink, creates a user (welcome password email), calls `POST /api/v1/forgetpassword` (reset email), asserts recipient, sender, subject and link/content on the captured messages, then blanks the email settings again

- `tools/api-tester/apis/search.json`
  - **search quality**: `GET /api/v1/search` cases following the search test catalog (looking up an issue, topics, mistakes, a pasted error, input that should return nothing, list behaviour); case ids carry the catalog id (`search-a1-…`, `search-b8-1-…`)
  - runs on its own fixture: `harness/seed-search.sh` loads `harness/seed-search.sql` (two projects, `SRA` and `SRB`, issues numbered 8917 and 9101-9172 with made-up words) into the test database. Load it after the other modes have run: its issues would show up in their lists
  - the test API has no Ares, so meaning search is `unavailable`; meaning cases are in `search-semantic.json`
  - cases that wait for a ranking rule are `"enabled": false` with the issue that brings the rule in `skipReason`; that issue turns them on
  - permission cases for search (other project, unreadable fields) are in `acl.json`

- `tools/api-tester/apis/search-semantic.json`
  - **meaning search** (opt-in, local only; not in CI or `ai test`): the catalog's meaning cases D1 (a symptom instead of the title), D2 (synonyms), D3 (a full sentence) and D5 (a question), with loose checks: the expected issue within the top 3
  - needs the search fixture with its stored embeddings and a test API that reaches Ares; see Meaning-search mode

Generate catalogs:

```bash
# optional naive client baseline (overwrites hand-tuned client.json)
php tools/api-tester/bin/generate-client-apis.php

# backend truth (routes.php + app/metadata/model → routes.v1.json + backend.json)
php tools/api-tester/bin/generate-backend-apis.php
```

## Optional local environment helpers (Gaia only)

These are **not** part of api-tester. Use them only when you need a local seeded API to point `--base-uri` at.

| Script | Purpose |
|---|---|
| `prepare-test-db.sh` | Create `pr4m_test`, clone schema from `pr4m`, seed fixtures, write fixtures JSON |
| `use-test-db.sh` | Optional: start local `gaia-test` on `:8081` (or `TEST_SERVICE=gaia-test-hermes-down` on `:8082`, `TEST_SERVICE=gaia-test-ares` on `:8083`) |
| `seed-search.sh` | Load the search fixture and its stored embeddings (`--no-embeddings`: the fixture alone) |
| `generate-search-embeddings.sh` | Regenerate the search fixture's stored embeddings through Ares |
| `hermes-wait/` | Node Socket.IO observer used by `--mode live` happy-path cases |

```bash
# 1) seed DB + fixtures (optional)
./tools/api-tester/harness/prepare-test-db.sh

# 2) start a local API somehow (compose helper, or your own process)
./tools/api-tester/harness/use-test-db.sh
# or: docker compose up -d gaia   # your choice of server

# 3) run tests against that URI
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode backend
```

## Live mode (Gaia → Hermes)

Opt-in catalog `apis/live.json`. Default `backend` / `client` / `acl` modes never assert Hermes.

Local compose uses **two** Hermes services (sibling `hermes` repo):

| Hermes service | Port | `GAIA_URL` | Used by |
|---|---|---|---|
| `hermes` | `:9000` | OG Gaia `:8080` | Prometheus / daily dev |
| `hermes-test` | `:9001` | `gaia-test` `:8081` | api-tester `--mode live` |

Do **not** retarget the main Hermes `GAIA_URL` for live tests.

**Happy path** (hermes-test up — REST success **and** matching `domain:event`):

1. In the hermes repo: `docker compose up -d hermes-test` (`GAIA_URL=…:8081`, port `9001`, same `HERMES_SECRET`).
2. Start `gaia-test` (`HERMES_URL=http://host.docker.internal:9001` is set in compose).
3. Requires Node (for `harness/hermes-wait`; resolves `socket.io-client` from local `node_modules` or sibling `hermes/node_modules`).

```bash
# hermes repo
docker compose up -d hermes-test

# gaia repo
./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh \
  --base-uri http://localhost:8081 \
  --mode live \
  --hermes-url http://localhost:9001
```

Cases with `hermes.expect` subscribe via Socket.IO **before** the Gaia write, then match envelopes by `eventName` / `projectId` / resource.

**Fail-open** (Hermes unreachable — user still gets REST success):

Run against `gaia-test-hermes-down` (`HERMES_URL=http://127.0.0.1:9`). Covers issue create/status patch, conversation room create, and conversation comment create — all with `"hermes": { "requireUp": false }` (no Socket.IO).

```bash
TEST_SERVICE=gaia-test-hermes-down ./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh \
  --base-uri http://localhost:8082 \
  --mode live \
  --filter fail-open
```

## Meaning-search mode (Gaia → Ares)

`--mode search-semantic` runs `apis/search-semantic.json`: meaning search with the real embedding model. It is opt-in
and local only, like live and mail. It needs:

- Ares up, with its embedding model loaded (`POST /embeddings` answers);
- the search fixture with its stored embeddings, loaded by `harness/seed-search.sh` (as for `--mode search`);
- a test API that reaches Ares: `gaia-test-ares` (`:8083`) is `gaia-test` with `LLM_SERVICE_URL` set
  (`TEST_ARES_URL`, default `http://host.docker.internal:8000`). `gaia-test` itself keeps no Ares, so its
  "Ares unavailable" behaviour stays as it is. `TEST_DB` picks the database (default `pr4m_test`).

```bash
./tools/api-tester/harness/seed-search.sh
TEST_SERVICE=gaia-test-ares ./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8083 --mode search-semantic
```

The first case checks that `meta.semantic` is `ok` and has `stopOnFailure`: without Ares the run stops there instead
of failing every case.

**Stored embeddings.** The fixture issues are not embedded during the run: `fixtures/search-embeddings.json` holds,
for each one, its semantic text (Gaia's `SemanticText` over the Issue search block), the text's hash, its embedding
and the model that made it. `seed-search.sh` writes them with the hash as both the desired and the actual hash, so a
reindex finds nothing to re-embed; only the queries are embedded live. The seed stops when a fixture issue's subject or
description no longer matches the file. Regenerate the file in one command (Ares up, test database prepared), after
changing `seed-search.sql` or when Ares changes its embedding model:

```bash
./tools/api-tester/harness/generate-search-embeddings.sh   # ARES_URL defaults to http://localhost:8000
```

## Mail mode (Gaia → SMTP sink)

Proves a message is really sent through the settings-configured SMTP. The sink is
[Mailpit](https://mailpit.axllent.org) (`mailpit` service in `docker-compose.yaml`, on the `backend`
network): it captures messages and delivers nothing. The suite uses only public flows (settings API,
`POST /api/v1/user`, `POST /api/v1/forgetpassword`) and the sink's HTTP API; Gaia has no test-only code for it.

SMTP is configured only in Settings > Email; no environment variables or tracked files carry a host or credentials.
With no host saved (the default in `gaia-test` and `gaia-test-hermes-down`), Mailer sends nothing and logs a warning,
so default runs never reach a mail server and the user's write still succeeds. Only this suite sets a host, by saving
the sink host in Settings > Email.

```bash
docker compose up -d mailpit gaia-test        # saving the SMTP password needs SETTINGS_ENCRYPTION_KEY in the environment
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode mail --mail-url http://localhost:8025
```

Without PHP on the host, run it inside the container (the sink is `http://mailpit:8025` on the network):

```bash
docker exec -w /var/www/html gaia-test php tools/api-tester/bin/api-tester run --base-uri http://localhost:80   --apis tools/api-tester/apis/mail.json --fixtures tools/api-tester/fixtures/default.json --mail-url http://mailpit:8025
```

Cases may declare a `mail` block (`to`, `subject`, `fromEmail`, `fromName`, `bodyContains`, `timeoutMs`); the runner
expects exactly one matching message for `to` + `subject` and asserts the rest. A case body can be form-encoded
with `"form": {...}` instead of `"body"` (used by `forgetpassword`).

The suite restores Settings > Email by blanking the values it set (blank means unset, so mail is off again). It does not run in CI: CI has no sink; enable it by adding the `mailpit` service there and a `--mode mail` step.

## Sources

- `sources/client-contract/` — client-derived inputs for `generate-client-apis.php`
- `sources/server-routes/routes.v1.json` — REST v1 route snapshot; rewritten by `generate-backend-apis.php` from `core/config/routes.php`

## Moving this tool out of Gaia

Copy/move `tools/api-tester/` and keep:

- `bin/api-tester`
- `bin/compare-reports.php`
- `src/*`
- `apis/` examples
- this README

Keep `harness/prepare-test-db.sh` / `use-test-db.sh` in Gaia (env-specific). The runner only needs `--base-uri`.

## Assertions

Each API entry can declare `expects`:

- `status`: number or array
- `shape`: `json` | `jsonapi.collection` | `jsonapi.resource` | `oauth.token` | `error`
- `errorEqual`: exact `error` string (used by ACL 403 cases)
- `jsonEqual`: map of body key (top-level, or a dotted path such as `meta.unreadCount`) → expected scalar value, for non-JSON:API bodies (e.g. `{"testResult": "TEST_EMAIL_SENT"}`)
- `jsonPathAbsent`: list of dotted body paths that must not exist
- `includedTypesAbsent`: list of JSON:API `included[].type` values that must not appear
- `includedTypesPresent`: list of JSON:API `included[].type` values that must appear
- `resourceNamesAbsent` / `resourceNamesPresent`: `attributes.resourceName` values across a permission (or similar) collection
- `attributesPresent` / `attributesAbsent`: attribute keys on resource (or first collection item)
- `attributesEqual`: map of attribute key → expected value
- `idsIncludes`: expected resource ids
- `idsExcludes`: resource ids that must not appear
- `idsEqual`: the collection's ids, in order, and nothing else (`[]` = no results)
- `idsWithin`: `{ "rank": N, "ids": [...] }`, every id in the first N results (`rank: 1` = first)
- `idsBefore`: `{ "ids": [...], "before": [...] }`, every listed id is returned and ranks above every returned `before` id
- `idsSameAs`: `{ "case": "<earlier case id>", "top": N, "ordered": true }`, the same ids as that case returned (as a set unless `ordered`; only the first N when `top` is given)
- `scoresWithin`: `[min, max]`, every item's `attributes.score` in the range
- `scoresFalling`: `true`, no item's `attributes.score` is above the one before it

An entry with `"stopOnFailure": true` ends the run when it fails: the remaining cases are not run and the report's
`stoppedAt` names it. Use it for a precondition case (e.g. the service a catalog needs is up).

## Auth profiles

Default auth comes from `fixtures.auth` (or `API_TEST_*` env vars).

Cases may set `"auth": "<profileName>"` to use `fixtures.authProfiles.<profileName>`
(merged over default auth). Tokens are cached per profile within a suite run.

ACL mode uses:

- `aclNoProject` — `issue.get`/`comment.get`/`conversationroom.get` allowed, `project.get` denied
- `aclProjectOnly` — `project.get` allowed, `issue.get` denied
- `aclMembers` — `project.get=2` (members); member of `api-test-project-001` only
- `aclNoConversation` — `project.get`/`comment.get` allowed, `conversationroom.get` denied
- `aclFields` — field ACL matrix on Issue (see `apis/acl.json` description above)
- `aclNoSetting` — `issuestatus.*`/`issuetype.*`/`project.get` allowed, `setting.get`/`setting.update` denied (system status/type writes and status issue counts are refused)

## Runtime capture (mutation lifecycle)

Successful cases can persist response values into fixtures for later cases via `store`:

```json
"store": { "runtime.milestoneId": "data.id" }
```

Supported selectors: `data.id`, `data.attributes.<field>`.

Use `{runtime.milestoneId}` in later `path` / `body` / `expects`. Order in `apis` matters (create → get → patch → delete).

Disabled entries (`enabled: false`) cover custom/auth/file/Socket.IO/ACL endpoints, missing metadata, or known broken routes (e.g. mention).

## Hermes live assertions

Cases may declare a `hermes` block (used by `apis/live.json`):

```json
"hermes": {
  "expect": [
    { "eventName": "issue.created", "projectId": "{projectId}", "resourceType": "issue" }
  ],
  "timeoutMs": 5000
}
```

- Requires `--hermes-url`. Runner arms `harness/hermes-wait` before the HTTP request.
- Fail-open cases use `"hermes": { "requireUp": false }` and never touch Socket.IO.
- `observer` (optional): the auth profile of the listening socket when it is not the writer, e.g.
  `"observer": "serviceRuntime"` to listen on the `runtimes` scope, which only runtime service tokens may join.
- `absent` (optional): match objects that must **not** arrive. The observer then listens for the whole
  `timeoutMs` and fails the case if one arrives. A case may have `absent` without `expect`.

```json
"hermes": {
  "observer": "serviceRuntime",
  "absent": [
    { "eventName": "workflow.agent.run.requested", "projectId": "runtimes", "resourceType": "workflowRun" }
  ],
  "timeoutMs": 3000
}
```
