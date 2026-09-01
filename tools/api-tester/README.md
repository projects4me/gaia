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
| `use-test-db.sh` | Optional: start local `gaia-test` on `:8081` (or `TEST_SERVICE=gaia-test-hermes-down` on `:8082`) |
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

## Sources

- `sources/client-contract/` — client-derived inputs for `generate-client-apis.php`
- `sources/server-routes/routes.v1.json` — REST v1 route snapshot; rewritten by `generate-backend-apis.php` from `core/config/routes.php`

## Moving this tool out of Gaia

Copy/move `tools/api-tester/` and keep:

- `bin/api-tester`
- `src/*`
- `apis/` examples
- this README

Keep `harness/prepare-test-db.sh` / `use-test-db.sh` in Gaia (env-specific). The runner only needs `--base-uri`.

## Assertions

Each API entry can declare `expects`:

- `status`: number or array
- `shape`: `json` | `jsonapi.collection` | `jsonapi.resource` | `oauth.token` | `error`
- `errorEqual`: exact `error` string (used by ACL 403 cases)
- `includedTypesAbsent`: list of JSON:API `included[].type` values that must not appear
- `includedTypesPresent`: list of JSON:API `included[].type` values that must appear
- `resourceNamesAbsent` / `resourceNamesPresent`: `attributes.resourceName` values across a permission (or similar) collection
- `attributesPresent` / `attributesAbsent`: attribute keys on resource (or first collection item)
- `attributesEqual`: map of attribute key → expected value
- `idsIncludes`: expected resource ids

## Auth profiles

Default auth comes from `fixtures.auth` (or `API_TEST_*` env vars).

Cases may set `"auth": "<profileName>"` to use `fixtures.authProfiles.<profileName>`
(merged over default auth). Tokens are cached per profile within a suite run.

ACL mode uses:

- `aclNoProject` — `issue.get`/`comment.get`/`conversationroom.get` allowed, `project.get` denied
- `aclProjectOnly` — `project.get` allowed, `issue.get` denied
- `aclNoConversation` — `project.get`/`comment.get` allowed, `conversationroom.get` denied
- `aclFields` — field ACL matrix on Issue (see `apis/acl.json` description above)

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
