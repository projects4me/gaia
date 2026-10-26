# gaia-dev

Developer tooling over the Gaia sources. It reads the repository into one typed graph that the scaffolders,
generated docs and query commands share, so each of them stops parsing the sources on its own.

Plain PHP (7.x), no Composer or Phalcon imports, no runtime dependencies.

```bash
php tools/gaia-dev/bin/gaia-dev graph export           # write docs/generated/graph.json
php tools/gaia-dev/bin/gaia-dev graph export --check   # exit 1 when the committed graph.json is stale
```

Options: `--root=<dir>` (repository root, default this checkout), `--output=<file>` (relative to the root).

CI runs `graph export --check` in the `graph-drift` job. When you change metadata, routes, the model list or
the api-tester catalogs and fixtures, run `graph export` and commit `docs/generated/graph.json` with the change.

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
| `behaviour` | `auditBehavior` | — | metadata |
| `route` | `GET /api/v1/issue/{id}`, `ANY /translations/{locale}`, `system:not_found` | type (rest, app, system), name, version, method, path | routes.php |
| `controller` | `issue` | — | routes.php |
| `action` | `issue.get` | — | routes.php |
| `profile` | `default`, `anonymous`, `aclNoProject` | file (when declared in the fixtures) | fixtures, catalogs |
| `case` | `acl/acl-issue-denied-without-project-group` | catalog, file, method, path, resource, enabled, source, placeholders, stores | catalogs |
| `assertion` | `status`, `attributesEqual`, `hermes.expect`, `mail.subject` | — | catalogs |
| `fixture` | `projectId` | file | fixtures |
| `component` | reserved | | code extractors (follow-up) |
| `permission` | reserved | | code extractors (follow-up) |

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
| `uses_component` | controller → component | reserved for the code extractors |
| `requires` | action → permission | reserved for the code extractors |
| `guards` | permission → field | reserved for the code extractors |

### Extending

A new source is one extractor (data source) plus one mapper (domain), wired into `GraphBuilder`. New node or
edge types go in `Schema` and in the tables above; `Graph` rejects types and edge ends the schema doesn't
declare. Adding types or attributes keeps `schemaVersion`; renaming or removing them bumps it.

## Tests

`tests/tools/gaia-dev/` (PHPUnit, each file passes alone) against a small fixture repository in
`tests/tools/gaia-dev/fixtures/repo`, plus checks that every model, route and case of this repository is in the
graph and that the committed graph is current.
