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

# client catalog
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode client

# both
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode client,backend

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
| `use-test-db.sh` | Optional: start local `gaia-test` on `:8081` |

```bash
# 1) seed DB + fixtures (optional)
./tools/api-tester/harness/prepare-test-db.sh

# 2) start a local API somehow (compose helper, or your own process)
./tools/api-tester/harness/use-test-db.sh
# or: docker compose up -d gaia   # your choice of server

# 3) run tests against that URI
./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode backend
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
- `attributesPresent`: list of attribute keys
- `attributesEqual`: map of attribute key → expected value
- `idsIncludes`: expected resource ids

## Runtime capture (mutation lifecycle)

Successful cases can persist response values into fixtures for later cases via `store`:

```json
"store": { "runtime.milestoneId": "data.id" }
```

Supported selectors: `data.id`, `data.attributes.<field>`.

Use `{runtime.milestoneId}` in later `path` / `body` / `expects`. Order in `apis` matters (create → get → patch → delete).

Disabled entries (`enabled: false`) cover custom/auth/file/Socket.IO/ACL endpoints, missing metadata, or known broken routes (e.g. mention).
