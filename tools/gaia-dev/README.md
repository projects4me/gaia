# gaia-dev

Developer tooling over the Gaia sources. It reads the repository into one typed graph that the scaffolders,
generated docs and query commands share, so each of them stops parsing the sources on its own.

Plain PHP (7.x), no Phalcon imports. The one Composer package it uses is `nikic/php-parser` (a `require-dev`
dependency, already installed by `composer install`), to read the PHP sources: they are parsed, never included.

```bash
php tools/gaia-dev/bin/gaia-dev graph export           # write docs/generated/graph.json
php tools/gaia-dev/bin/gaia-dev graph export --check   # exit 1 when the committed graph.json is stale
php tools/gaia-dev/bin/gaia-dev docs generate          # write the pages under docs/generated from graph.json
php tools/gaia-dev/bin/gaia-dev docs generate --check  # exit 1 when a committed page is stale, missing or orphaned
```

```bash
php tools/gaia-dev/bin/gaia-dev scaffold api-cases GET /api/v1/idea --profile=aclMembers --catalog=acl   # append cases
php tools/gaia-dev/bin/gaia-dev scaffold api-cases POST comment '--assert=attributesPresent=["comment"]'  # client happy path
php tools/gaia-dev/bin/gaia-dev scaffold api-cases --new-profile=aclReports --role="ACL Reports" --permissions=issue.get=1,project.get=2
php tools/gaia-dev/bin/gaia-dev scaffold api-cases --cases-file=cases.json --catalog=client             # append hand-written cases
php tools/gaia-dev/bin/gaia-dev check api-cases [<endpoint>]   # exit 1 on inconsistent catalogs
php tools/gaia-dev/bin/gaia-dev scaffold resource Report --parent=Project --fields=title:varchar:100:required,body:text --acl=aclMembers:members
php tools/gaia-dev/bin/gaia-dev check resource Report            # exit 1 when a touchpoint is missing or inconsistent
php tools/gaia-dev/bin/gaia-dev scaffold behavior reportTotals --model=Report --events=beforeSave   # class, failing test, metadata entry
php tools/gaia-dev/bin/gaia-dev check behavior [--skip-tests]   # exit 1 on a missing class or test, or a test failing alone
php tools/gaia-dev/bin/gaia-dev scaffold live-events Report --events=created,updated --fields=title,status [--parent=reportId:Report]   # component, EventNames, controller $uses, test cases; prints the Hermes names to add
php tools/gaia-dev/bin/gaia-dev check live-events   # exit 1 on an event name with no component or test, or a component in no $uses
php tools/gaia-dev/bin/gaia-dev scaffold action Tag patch [--domain=TagRules]   # thin override of a standard action (no new route) + its acl and client cases; names the domain class to write
php tools/gaia-dev/bin/gaia-dev check action   # exit 1 when a scaffolded action lacks its route, permission rule or acl/client cases
```

`scaffold api-cases` reads `docs/generated/graph.json` (run `graph export` first when you changed sources) and appends to the catalog in that file's own style (`acl.json` keeps `query`, `expects` and `store` on one line; the rest is two-space JSON), so the diff holds only the new cases. It refuses a case id that already exists and `backend.json` (generated). `--dry-run` prints what it would write. Edit the asserted values afterwards, then run `graph export`, `docs generate` and `check api-cases`.

- `client` (default): the happy path as `--profile` (default: the default profile); a POST stores `runtime.<resource>Id`. Path ids become the `<segment>Id` fixture when the seed has one, else `runtime.<segment>Id`.
- `acl`: per profile with a rule on the endpoint's permission (or only `--profile`): a denial (`403 Access Denied`) for a deny, the allowed read or write for a grant, and for a members-only grant the allowed case plus a non-member denial using `otherProject<Resource>Id`. Notes list the seed rows or fixtures still missing.
- `--assert=name=value,...` adds `expects` entries; names must be assertions the Asserter implements (`gaia-dev assertions`). A bare name asserts `null`, to be edited.
- `--new-profile` writes the user, role, user_role and permission rows into `prepare-test-db.sh` and the `authProfiles` entry into `fixtures/default.json` (and the script's copy of it) in one go; nothing is written when any of them cannot be.

`scaffold resource <Name>` writes every touchpoint of a new REST resource (bundle B): metadata, model, controller, the models list, the route, the placeholder maps of `generate-backend-apis.php`, the harness (admin module, seed rows, `--acl` permission rows) and both fixture files. The files are rendered from the Idea reference files (`app/metadata/model/Idea.php`, `app/models/Idea.php`, `IdeaController.php`) with the name substituted, so a change to their shape fails the golden test instead of drifting. `--fields=name:type[:length][:required][:default=x]`, `--parent=Project` adds the foreign key, index, relationship and ACL group, `--behaviors=` replaces the reference's list, `--acl=profile:allow|members|deny` gives that profile's role `<resource>.get` and appends its acl cases. It then regenerates `backend.json`, `routes.v1.json`, the graph and the docs, and prints what is left by hand (relationship on the parent, behaviour logic, live events, migration, asserted values). It refuses a resource that already exists. `check resource <Name>` checks metadata, model, models list, route, controller, the route catalog and the api-cases for the resource; a model without a route is internal and is checked for metadata, class and list entry only.

`check api-cases` fails when a profile is restricted (deny or members-only) on a resource with no `acl` case, when an enabled hand-written case uses a placeholder that is no fixture, stored runtime id or runner value (`runId`), when a case asserts a name the Asserter does not implement, or when a fresh run of `generate-backend-apis.php` (in a scratch copy) differs from the committed `backend.json` and route snapshot. The generated `backend` catalog is exempt from the placeholder rule. CI runs it in the `graph-drift` job.

Options: `--root=<dir>` (repository root, default this checkout), `--output=<file>` (relative to the root).

CI runs `graph export --check` and `docs generate --check` in the `graph-drift` job. When you change metadata, routes, the model list or
the api-tester catalogs and fixtures, run `graph export` and commit `docs/generated/graph.json` with the change; then run `docs generate` and commit `docs/generated/` too. Do not edit the pages by hand.

`docs generate` writes one page per model (`models/<Model>.md`), `routes.md`, `acl-matrix.md`, `assertions.md`, `profiles.md` and one guide per bundle of `docs/dev/bundles.md` (`bundles/<letter>.md`, with the exemplar PRs and golden files linked), plus a `README.md` index. Every section starts with an `<a id="..."></a>` anchor, has its own heading and is at most 40 lines, so one section can be served alone. The pages are built from the same queries as the `gaia-dev` commands (`Docs/*Pages`, `Docs/*Page`, `Docs/BundleGuides`); `Command/DocsCommand` writes or checks them.

## Layout

Layered like the app (data source → domain → presentation):

| Layer | Classes | Responsibility |
|---|---|---|
| Data source | `src/Extractor/*Extractor` | One class per source; reads it into plain records. `SourceFile` includes PHP arrays and decodes JSON. The included files (`routes.php`, `models.php`, metadata models) must stay plain arrays with no class, constant or app references; a failing include is reported with its path. |
| Domain | `src/Graph/Graph`, `Schema` | Typed nodes and edges, checked against the schema. |
| Domain | `src/Graph/Mapper/*Mapper` | Turn one extractor's records into nodes and edges. |
| Domain | `src/Graph/GraphBuilder`, `RouteMatcher` | Run extractors and mappers in order; match a case path to its route. |
| Presentation | `src/Cli`, `src/Command/GraphExportCommand`, `src/Graph/GraphJson` | Parse the command line, write or check the JSON. |

| Extractor | Source | Record |
|---|---|---|
| `MetadataModelExtractor` | `app/metadata/model/*.php` | name, file, tableName, isView, fields (attributes incl. `acl`, `secure`), relationships (kind, related and secondary model), behaviors, model `acl` |
| `ModelListExtractor` | `core/config/models.php` | registered model names |
| `RouteExtractor` | `core/config/routes.php` | type, name, version, method, path, controller, action — REST resources expanded the way `Gaia\MVC\Router` registers them |
| `CatalogExtractor` | `tools/api-tester/apis/*.json` | per catalog: endpoints and cases (method, path, resource, profile, enabled, source, assertions, placeholders, stores) |
| `FixtureExtractor` | `tools/api-tester/fixtures/default.json` | profile names and value keys; credentials are left out |
| `ControllerExtractor` | `app/api/v1/controllers/*Controller.php` | name, class, file, parent, `$uses`, actions (public `<name>Action` methods) |
| `ComponentExtractor` | `app/api/v1/controllers/components/**` | key (as in `$uses`), kind (component, event, notification, support), class, file, parent, interfaces, hooks (public methods) |
| `BehaviourExtractor` | `core/mvc/models/behaviors/*.php` | name, class, file, hooks |
| `AclExtractor` | `core/libs/security/Acl.php`, `core/mvc/controllers/RestController.php` | record scopes, resolution modes, default scoped resources, public API; the REST action to permission action map (`post` needs `create`) |
| `AssertionExtractor` | `tools/api-tester/src/Asserter.php`, `Runner.php` | `expects[...]` keys, `mail.*` and `hermes.expect`: where checked, failure messages, `shape` values |
| `ProfileExtractor` | `tools/api-tester/harness/prepare-test-db.sh` | per fixture profile: user, role, permissions (`resource => 0 deny, 1 allow, 2 members`); reads the seed SQL with patterns, so keep its layout (credentials are never read) |

## Graph schema (version 1)

`docs/generated/graph.json`:

```json
{
  "schemaVersion": 1,
  "generator": "tools/gaia-dev/bin/gaia-dev graph export",
  "nodes": [{"id": "model:Issue", "type": "model", "key": "Issue", "attributes": {}}],
  "edges": [{"from": "model:Issue", "type": "has_field", "to": "field:Issue.subject"}]
}
```

Node ids are `<type>:<key>`; nodes are sorted by id and edges by from, type and to. There are no timestamps, so
the same sources always give the same file. Line endings inside source strings are read as LF, so a Windows
checkout gives the same file as CI.

### Nodes

| Type | Key | Attributes | Source |
|---|---|---|---|
| `model` | `Issue` | file, tableName, isView, registered (in models.php), hasMetadata, acl (the model's `acl` block) | metadata, models.php |
| `field` | `Issue.subject` | the field's metadata (type, length, null, label, identifier, linkedTo, `acl`, `secure`, …) | metadata |
| `relationship` | `Issue.project` | kind (hasOne, hasMany, belongsTo, hasManyToMany) and its keys/conditions | metadata |
| `behaviour` | `auditBehavior` | class, file, hooks | metadata, behaviours |
| `route` | `GET /api/v1/issue/{id}`, `ANY /translations/{locale}`, `system:not_found` | type (rest, app, system), name, version, method, path | routes.php |
| `controller` | `issue` | rest (REST resource), class, file, extends | routes.php, controllers |
| `action` | `issue.get` | — | routes.php, controllers |
| `profile` | `default`, `anonymous`, `aclNoProject` | file (when declared in the fixtures), userId, email, roleId, role, roleDescription | fixtures, catalogs, harness |
| `case` | `acl/acl-issue-denied-without-project-group` | catalog, file, method, path, resource, enabled, source, placeholders, stores | catalogs |
| `assertion` | `status`, `attributesEqual`, `hermes.expect`, `mail.subject` | source, line, messages (what its failure says), values (`shape`), example (first enabled case using it) | catalogs, api-tester code |
| `fixture` | `projectId` | file | fixtures |
| `component` | `Events\IssueLiveEvents` (as in `$uses`) | kind, class, file, extends, implements, hooks | components |
| `permission` | `issue.get`, `issue.subject.get` | kind (action, field), scoped, scopes (none 0, all 1, members 2) | profiles, ACL, REST routes |

`default` is the fixture's main `auth` user (`"auth": true`); `anonymous` is `"auth": false`.

### Edges

| Edge | From → to | Meaning |
|---|---|---|
| `has_field` | model → field | |
| `linked_to` | field → field | display field filled from another (`linkedTo`) |
| `has_relationship` | model → relationship | |
| `targets` | relationship → model | related model; for hasManyToMany the secondary (far) model |
| `via` | relationship → model | hasManyToMany join model |
| `uses_behaviour` | model → behaviour | |
| `has_action` | controller → action | |
| `handled_by` | route → action | |
| `serves` | controller → model | REST controller whose name is the model's lowercase name |
| `covers` | case → controller | the case's `resource` |
| `exercises` | case → route | route the case path matches (none for paths with no route, e.g. a 405 check) |
| `runs_as` | case → profile | |
| `asserts` | case → assertion | keys under `expects`, plus `hermes.expect` and `mail.*` |
| `uses_fixture` | case → fixture | placeholder resolved from the fixtures |
| `depends_on` | case → case | the case reads a `runtime.*` value an earlier case in the same catalog stores |
| `uses_component` | controller → component | the component is listed in the controller's `$uses` |
| `requires` | action → permission | a REST action needs `<controller>.<permission action>` (`issue.post` requires `issue.create`) |
| `guards` | permission → field | `<model>.<field>.<action>` protects that field |
| `grants` | profile → permission | the profile's role allows it (1) |
| `grants_members` | profile → permission | allowed for member projects only (2, scoped resources such as `project.get`) |
| `denies` | profile → permission | the profile's role denies it (0) |

Questions the graph answers without reading source: which profile may do what on which resource (`profile`
-> `grants`/`grants_members`/`denies` -> `permission` <- `requires` <- `action`), which component fires on which
controller (`controller` -> `uses_component`), and what assertion X means (`assertion` attributes `messages`,
`values`, `example`).

### Extending

A new source is one extractor (data source) plus one mapper (domain), wired into `GraphBuilder`. New node or
edge types go in `Schema` and in the tables above; `Graph` rejects types and edge ends the schema doesn't
declare. Adding types or attributes keeps `schemaVersion`; renaming or removing them bumps it.

## Queries

Terse answers (at most about 40 lines) read from the committed `docs/generated/graph.json`, so they replace reading
`core/` and the api-tester sources. Add `--json` for the structured answer. An unknown name prints a one-line hint
with the nearest names on stderr and exits 1. Re-run `graph export` first when you changed metadata, routes or cases.

```bash
php tools/gaia-dev/bin/gaia-dev model Idea                 # table, fields, relationships, behaviours, ACL, routes, components, cases
php tools/gaia-dev/bin/gaia-dev route idea                 # all routes of a resource; or "POST /api/v1/idea" / a path for one
php tools/gaia-dev/bin/gaia-dev acl idea aclNoProject      # per profile allow / members / deny, or one profile's full row
php tools/gaia-dev/bin/gaia-dev cases idea --method=POST   # also --profile=<name>, --all; or a case id
php tools/gaia-dev/bin/gaia-dev assertions                 # vocabulary with a failure message and a real example case
php tools/gaia-dev/bin/gaia-dev profiles                   # role, allow/members/deny counts, case counts
php tools/gaia-dev/bin/gaia-dev fixtures                   # placeholder names, cases using them, source file
```

`acl`, `assertions`, `profiles` and `fixtures` also take one name to narrow the answer to a single row; `--method`,
`--profile` and `--all` belong to `cases` only and are rejected elsewhere.

`route` prints the hook sequence from a fixed map of REST action to the `rest:` events `RestController` fires
(`post` fires `beforeCreate` and `afterCreate`, `get`/`list` `afterRead`, updates `afterUpdate`, `delete`
`afterDelete`); update `RouteQuery::EVENTS` when `RestController` fires a new one.

Layers: `src/Query/GraphReader` (read the graph), one `Query` class per command (domain: the answer as an array),
`Renderer` (text or JSON) and `src/Command/QueryCommand` (load, run, print).

## Tests

`tests/tools/gaia-dev/` (PHPUnit, each file passes alone) against a small fixture repository in
`tests/tools/gaia-dev/fixtures/repo`, plus checks that every model, route and case of this repository is in the
graph and that the committed graph is current.

## Decisions docs

The hand-written "why" docs stay in `docs/architecture/*/decisions.md`. `tests/tools/gaia-dev/DecisionsCitationsTest`
runs `Docs/CitationChecker` over them: every file path, `Class::member`, class name, call and verb-first method name in
backticks must exist in the sources (paths into hermes/, prometheus/ and ares/ are not checked). To mention code that
was removed, write it without backticks (for example in italics).
