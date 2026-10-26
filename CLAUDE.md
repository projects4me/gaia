# Gaia — Projects4Me REST API (PHP 7.x, Phalcon 4, PostgreSQL 16)

## Branching
- Work off `dev` (master is stale). Team branches are `issue-NNNN`; Claude branches are `claude-issue-NNNN` (see House rules). PR to `dev`.
- Commit/PR title: `type(scope): summary (#PR)`, e.g. `feat(rbac): ...`.

## Run
- Stack, migrations and seeds: workspace skill `p4m-stack`. Tests and api-tester conventions: skill `run-tests`.
- Migrations are declarative and DROP columns removed from metadata; run them only against your local DB.

## Architecture
- Don't read source to learn the structure: use `php tools/gaia-dev/bin/gaia-dev` (`model <Name>`, `route <path>`, `acl <resource> [profile]`, `cases <endpoint>`, `assertions`, `profiles`, `fixtures`) or the generated pages in `docs/generated/` (start at `README.md`: models, route table, ACL matrix, assertion vocabulary, one guide per change bundle). Both come from `docs/generated/graph.json`; refresh them with `graph export` and `docs generate` (CI checks both).
- Kinds of change (new resource, behaviour, live events, upload scope, ...): the guide in `docs/generated/bundles/` (catalog `docs/dev/bundles.md`).
- Why a design is the way it is: `docs/architecture/*/decisions.md` (kept in step with the code by a test).
- Entry `public/index.php`; routes `core/config/routes.php`; all CRUD in `core/mvc/controllers/RestController.php`. Model metadata `app/metadata/model/<Model>.php` is the source of truth.
- Query param `query` uses Gaia's own language, e.g. `((Issue.projectId : X))` — see `core/mvc/models/query/Clause.php`.
- Live events: `app/api/v1/controllers/components/events/support/HermesPublisher.php`; names in `EventNames.php` MUST match `hermes/src/contract/names.js`. AI planning: `core/services/LLM.php` -> Ares `POST /planissue`.

## Rules
- Never build SQL/PHQL by string concatenation; use bound parameters.
- When adding a resource, check its permissions on existing roles and add `tools/api-tester` acl cases.
- Columns are camelCase; quote them in raw Postgres SQL (`"userId"`). IDs are GUIDs; deletes are soft.
- Keep the license header and docblocks (`@author`, `@package`, `@license AGPLv3`) on new files.
- Don't commit secrets; read config via `getenv()` in `config/*.php`.

## House rules

These house rules assume the repo is checked out inside the **Pr4M** workspace (https://github.com/projects4me/Pr4M), which provides `dev-tools/` (the `gaia` MCP server, the shared Agent Odysseus identity, the `ai` workflow CLI, Talos, the `installer`) and the workspace skills and agents in `.claude/`.

### Workflow
- **Issue first.** Before starting any work, find or create its issue in Projects4Me: search with `gaia_list_issues`, create with `gaia_create_issue` (the `gaia` MCP server in `dev-tools/gaia-mcp`), or use the Projects4Me UI. Gaia work goes in project **"A Gaia"** (short code `agaia`). Use its issue number in the branch name (`claude-issue-NNNN`). Move the issue's status as work progresses (`in_progress` → `in_review` → `done`).
- **Milestone, time and notes.** Every issue goes in the project's **Claude-v1** milestone (the `gaia` MCP server does this by default). While working, log the time spent on the issue (`gaia_log_time`, with a short description of what it was spent on) and record the details in its thread (`gaia_add_comment`): what was done, findings, decisions, files changed and what's left. Do both before marking the issue `in_review`.
- **Branch, PR, merge.** Move the issue to `in_progress`, then branch `claude-issue-NNNN` off an up-to-date `dev`. Run the full test suite before opening the PR. Push the branch to the `odysseus` remote and open a PR to `dev` with `odysseus gh pr create`. **Never merge**: the user merges. After the user merges, rebase local `dev` onto `origin/dev` before starting the next issue. Leave uncommitted files you didn't create for this issue alone.
- **Identity: Agent Odysseus.** Commit, push and open PRs only as Agent Odysseus, through `dev-tools/odysseus/odysseus git …` / `odysseus gh …` (see `dev-tools/odysseus/README.md`). Push only to the `odysseus` remote, never to `origin`. Never change git or gh configuration (global or repo) and never use your personal git/GitHub credentials for agent work; if the agent identity isn't available, stop and ask.
- One issue per PR; log anything else you find in the Projects4Me tracker instead of fixing it in the same PR.
- MVP first: items in the Engineering Guide's pre-production table are out of scope unless the issue asks for them.
- Never commit, push, merge, force-push, tag or deploy without explicit approval. Never commit to `master` or `qa`.
- When docs and code disagree, the code wins; flag the doc in your summary.
- Acceptance criteria unclear? Ask instead of guessing, and write down assumptions in the PR.

### Boundaries
- Gaia owns all data and every authorization decision; no other service may bypass it.
- Contract changes (event names, Hermes envelope, Ares request/response) ship as linked PRs in both repos and merge together.
- Rename fields in three steps: add the new field, copy the data, remove the old field in a later PR. Never hand-edit the database.
- Side effects fail open: Hermes or email being down must not fail the user's write. A failed Ares call is reported as a failure, never as success.
- Run `migrate` and test-data resets only against your own local database, never a shared or QA one.

### Security
- Every new resource gets ACL metadata and api-tester `acl` cases; never weaken ACL, token checks or CORS to make something work.
- Never put secrets in compose files, logs or PR text.

### Design (Fowler's layering + SOLID)
- Follow the layering and separation of concerns from Martin Fowler's *Patterns of Enterprise Application Architecture*: presentation (controllers in `app/api/v1/controllers`, components for request side effects) → domain (models, metadata, `core/libs` services) → data source (`core/mvc/models` Model/Query, db). Dependencies point down only; no SQL or domain rules in controllers, no HTTP/request handling in models or libraries.
- Keep controllers thin: parse the request, authorize, delegate to the domain, shape the response.
- Each external system sits behind one class that owns its configuration and transport (e.g. `HermesPublisher`, `LLM`, `Mailer`), so callers hand over data and swapping the transport touches only that class.
- SOLID, especially **Single Responsibility**: one reason to change per class/method. Open/closed via metadata, behaviours and components rather than editing `RestController` for one resource. Depend on abstractions at those boundaries.
- Before adding a class or method, name its single responsibility; if it needs "and", split it.

### Quality
- "Done" means tests and lint were run and passed. Report failures as they are, and say what wasn't run.
- A bug fix starts with a test that fails.
- No new dependencies and no framework or runtime upgrades without approval.

### PR description
What · Why (issue link) · How tested · Contract impact (events, envelope, Ares format) · Migration impact · Out of scope / follow-ups.
