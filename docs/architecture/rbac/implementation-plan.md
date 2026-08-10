# RBAC Architecture and Implementation Plan

This document describes how Gaia's backend RBAC architecture is implemented and how the remaining work should proceed.

Behavioral policy belongs in [decisions.md](./decisions.md). This document covers architecture, implementation order, migration, and completion criteria.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend RBAC |
| Policy source | `docs/architecture/rbac/decisions.md` |
| Primary code | `core/libs/security/acl.php`, `core/libs/security/aclMapCatalog.php`, `app/models/Permission.php`, `core/mvc/controllers/RestController.php`, `app/api/v1/controllers/PermissionController.php` |

---

## 1. Goals

- Authorize by **module action** (`issue.create`, `project.get`), not by model + CRUD flag columns.
- Keep authentication, authorization policy, persistence, and response serialization separated.
- Apply authorization before protected data is queried where practical.
- Support relationship and query-criteria authorization consistently with direct access.
- Keep permissions role-based (users inherit access through memberships).
- Publish the action catalog from controller `$aclMap` definitions for the permission UI.
- Establish a stable policy boundary from which automated tests can be generated.

## 2. Non-goals

- RuBAC or rule compilation.
- Adding chained relationship loading that Gaia does not currently support.
- Field-level ACL as part of the current action-based revamp (deferred).
- Project-scoped permission evaluation as a first-class runtime rule (deferred; roles are treated as global for checks for now).
- Defining row-level or ownership rules before their policy is agreed.

---

## 3. Target architecture

```text
HTTP Request
    |
    v
RestController
    |  OAuth + resolve dispatcher action
    |  aclMap[dispatcherAction].action  →  e.g. create
    |  resourceName = module.action     →  e.g. issue.create
    v
Acl (request-scoped policy)
    |  loads effective permission rows
    v
Permission (persistence/query model)
    |  resourceName + allowed + roleId
    v
Database (via Membership.roleId)

AclMapCatalog  ←── scans controller $aclMap
    |
    v
system.php moduleActions  →  Permission UI / Systemsetting
```

### 3.1 RestController

`RestController` is responsible for:

- OAuth request verification.
- Resolving the current user, dispatcher action, base model, route ID, and requested relationships.
- Declaring the default `$aclMap` that maps dispatcher methods to ACL actions.
- Passing request context to `Acl`.
- Passing only authorized relationships into model reads.
- Using the request-scoped `Acl` instance during response preparation.

Default `$aclMap` (modern action names):

| Dispatcher key | ACL action | Controller method |
|----------------|------------|-------------------|
| `get` | `get` | `getAction` |
| `list` | `get` | `listAction` |
| `related` | `get` | `relatedAction` |
| `post` | `create` | `postAction` |
| `put` | `update` | `putAction` |
| `patch` | `update` | `patchAction` |
| `delete` | `delete` | `deleteAction` |

Module controllers may redefine `$aclMap` entirely. There is no `$aclMapReplace` flag and no catalog-side merge: the effective map is whatever the controller declares, or the inherited RestController defaults.

### 3.2 Acl

`Gaia\Libraries\Security\Acl` is the request-scoped RBAC policy layer.

It is responsible for:

- Loading effective permissions through `Permission`.
- Holding permission and field-access state for one request.
- Authorizing action resources (`authorizeAction` for `module.action`), including authorization groups.
- Combining allow rows from multiple applicable roles using permissive or restrictive mode.
- Filtering unauthorized eager relationships (related module + its groups).
- Authorizing relationship-only routes.
- Denying unauthorized active query criteria (`query`, `sort`, `group`, `having`).
- Applying field-level filtering where still used by response preparation.

`Acl` must not persist permission records or format HTTP responses.

Resolution modes:

- `RESOLUTION_PERMISSIVE` (default): any allow wins.
- `RESOLUTION_RESTRICTIVE`: any deny wins.
- Selectable via constructor / `setPermissionResolutionMode()`; later via application config.

### 3.3 Permission

`Gaia\MVC\Models\Permission` is a persistence/query model.

Current permission identity:

| Column | Purpose |
|--------|---------|
| `resourceName` | Action identity, e.g. `issue.create` |
| `allowed` | Binary allow (`1`) / deny (`0`) |
| `roleId` | Role that receives the grant |

Permissions are always applied to **roles**, never directly to users. Users receive access through memberships.

`findEffectivePermissions($userId)` returns rows keyed by `resourceName`. It does not throw access exceptions or filter API responses.

Legacy model-centric columns (`resourceId`, `readF`, `createF`, `updateF`, `deleteF`, `importF`, `exportF`) are removed from the Permission metadata model.

### 3.4 AclMapCatalog

`Gaia\Libraries\Security\AclMapCatalog` builds the module/action catalog by:

1. Reading v1 REST routes.
2. Resolving each module’s controller class.
3. Skipping controllers with `$authorization = false` or `$aclAllowed = false`.
4. Reading the controller’s effective `$aclMap`.
5. Emitting `{ module, actions: [{ action, resourceName }] }`.

Published through `core/config/system.php` as `system.acl.moduleActions` for the permission UI.

### 3.5 PermissionController

Permission administration:

- Lists default actions from `moduleActions`.
- Loads applied permissions by `roleId` only.
- Creates/updates permissions using `resourceName` + `allowed` + `roleId`.
- Validates that `resourceName` exists in the action catalog.

### 3.6 Metadata manager

Still used for:

- Relationship alias → API-visible model resolution (`relatedModel` / `secondaryModel`).
- Authorization groups: `getGroups()` (models with `acl.group`) and `getModelGroups()` (`acl.groups` list).

---

## 4. Request authorization flow

### 4.1 Base resource / action

1. Verify OAuth credentials.
2. Resolve the current user and dispatcher action.
3. Resolve ACL action from `$aclMap[dispatcherAction].action`.
4. Build `resourceName` as `{module}.{action}` (e.g. `issue.create`).
5. Load effective permissions through `Permission`.
6. Authorize the action resource through `Acl`, including every authorization group declared on the model (`{group}.get`).
7. Throw HTTP 403 for explicit denial (subject to resolution mode).

### 4.2 Embedded relationships

1. Parse, trim, merge where supported, and de-duplicate requested relationship aliases.
2. Resolve each alias to its API-visible related model.
3. Evaluate related-model access for the current request action **and** that model's authorization groups.
4. Preserve allowed aliases.
5. Drop denied aliases without denying the parent resource.
6. Pass only allowed aliases into query construction and serialization.

### 4.3 Related-resource route

For `GET /api/v1/{resource}/{id}/{relation}`:

1. Authorize the parent/base request.
2. Resolve the route relationship to its API-visible model.
3. Authorize direct access to that related model/action, including its authorization groups.
4. Throw HTTP 403 on denial.

### 4.4 Active query criteria

For `query`, `sort`, `group`, and `having`:

1. Extract qualified model/field references without interpreting criterion values as fields.
2. Resolve known relationship aliases through metadata.
3. Check the related API-visible model (action + authorization groups).
4. Throw HTTP 403 with a generic message when either is denied.
5. Complete this check before model query construction.
6. Leave unknown aliases to normal query validation.

Active query usage is stricter than passive eager loading: an unauthorized eager relationship is omitted, while an unauthorized criterion denies the request.

### 4.5 Authorization groups

Models declare groups in metadata:

- `acl.group => true` marks a model as an authorization group.
- `acl.groups => [...]` lists required group models for a dependent.

`Acl::isModelActionAllowed()` enforces module-level cascading only (no record-level parent checks).

### 4.6 Field filtering

Field ACL remains available in response preparation but is **deferred** for the action-based revamp. Model/action authorization is the primary gate.

---

## 5. Current implementation status

### Completed

- [x] Separate authorization policy (`Acl`) from permission persistence (`Permission`).
- [x] Register request-scoped `Acl` in DI.
- [x] Modern `$aclMap` on `RestController` using `get` / `create` / `update` / `delete`.
- [x] Module controllers may fully redefine `$aclMap` (no `$aclMapReplace`, no catalog merge).
- [x] `AclMapCatalog` builds `moduleActions` from controller `$aclMap`.
- [x] Publish `moduleActions` through `core/config/system.php`.
- [x] Permission metadata uses `resourceName` + `allowed` + `roleId`.
- [x] `Permission::findEffectivePermissions($userId)` loads role grants via memberships.
- [x] Permission admin lists/applies permissions by `roleId` only (not `userId`).
- [x] Selectable permissive/restrictive role aggregation in `Acl`.
- [x] Filter unauthorized eager relationships before query construction.
- [x] Preserve parent access when one relationship is denied.
- [x] Enforce related-model access on relationship-only routes.
- [x] Canonicalize `rels` / `include` parsing for supported actions.
- [x] Deny unauthorized model/field use in `query`, `sort`, `group`, and `having`.
- [x] Record accepted behavior in `decisions.md`.
- [x] Module-level authorization groups (`acl.group` / `acl.groups`) enforced in `Acl`.

### In progress / transitional

- [ ] Fully wire `RestController::authorize()` to action-based `authorizeAction('{module}.{action}')` end-to-end (catalog and permission storage are already action-based; runtime path still needs completion/cleanup of leftover model-flag assumptions).
- [ ] Align relationship and clause checks with action resource names where applicable.
- [ ] Remove remaining transitional helpers once runtime is fully action-based.

### Deferred

- [ ] Field-level ACL redesign for action-based permissions.
- [ ] Project-scoped vs system-role precedence for runtime checks.
- [ ] Record-level / parent-instance group authorization.
- [x] Group-based ACL HTTP scenarios via api-tester (`--mode acl`).
- [ ] End-to-end API RBAC test fixtures beyond the ACL catalog.

Tests remain deferred until the remaining RBAC policy surface is stable. Accepted decisions are the eventual source for test generation.

---

## 6. Remaining implementation phases

### Phase 1 — Finish action-based runtime authorization

Objectives:

- [x] Define modern `$aclMap` action names.
- [x] Build action catalog from controllers.
- [x] Store permissions as `resourceName` + `allowed` on roles.
- [ ] Make `authorize()` resolve and check `{module}.{action}` for every REST request.
- [ ] Ensure custom controller actions declared in `$aclMap` are authorized the same way.
- [ ] Remove leftover model-flag authorization assumptions from the runtime path.

### Phase 2 — Stabilize relationship and clause authorization under actions

Objectives:

- [x] Prevent unauthorized relationship aliases and fields from being used by filters, sort, grouping, or having.
- [ ] Re-express relationship authorization in terms of related-module actions (e.g. related Issue access uses `issue.get`).
- [ ] Prevent unauthorized relationship aliases from reappearing through requested fields, export, or custom controller parameters.
- [ ] Define the response contract for omitted relationships versus empty linkage.

### Phase 3 — Role resolution and scope

Objectives:

- [x] Define permissive/restrictive combination rules for multiple role grants.
- [ ] Decide default-allow vs default-deny when no permission row exists for an action.
- [ ] Define precedence between project and system memberships, if project scope returns.
- [ ] Stop deriving security scope from loosely parsed request query strings where a trustworthy resource context can be resolved.

### Phase 4 — Field ACL

In progress. Action-based field identity `{module}.{field}.{action}` on the existing permission store.

Objectives:

- [x] Catalog: emit `moduleFields` from metadata (`acl !== false`) with `get` / `create` / `update` only; no field `delete`.
- [x] Admin: `PermissionController` default permissions include field resources.
- [x] Read: pre-query `filterAuthorizedFields`; denied attributes omitted for default select and explicit `fields=` (no field-ACL 403 on `fields=`).
- [x] Clauses: field `.get` checks in `authorizeClauseUsage` → 403 on deny.
- [x] Write: `authorizeWritableFields` on POST/PUT/PATCH discards denied attributes (403 only for module action).
- [x] Response: omit denied attribute keys (not null); rewrite legacy `Model.field` checks.
- [x] Frontend: 3-segment ACL contexts; role UI nested field rows; Mirage fixtures.

### Phase 5 — Administration and configuration security

Objectives:

- [x] Secure permission, role, and ACL administration endpoints — authorized like any other module via `permission.*`/`role.*`/`userrole.*` (D-054).
- [x] Remove temporary bypasses in ACL administration controllers — `AclAdminController` (hardcoded `return true;`) removed; `PermissionController` extends `RestController` directly.
- [x] Retire the "Global" default role and its auto-assignment on user create (D-050).
- [x] Bootstrap a full-catalog "Admin" role without an `Acl` bypass (D-051; seeded via baseline/ops — CLI ensure task deferred).
- [x] Prevent ACL admin lockout: capability-based invariant (no `isSystem` flag) enforced on role delete, permission demotion, last usable-membership removal, and last Active admin deactivate/delete (D-052, `AclLockoutGuard`; modules `permission`/`role`/`userrole` plus `user.{create,update,delete}`; usable = Active `accountStatus`).
- [ ] Define who may grant permissions and whether grantors may grant access they do not possess.
- [ ] Expose resolution mode through application config.
- [ ] Add audit records for permission and role changes.

### Phase 6 — Observability and failure handling

Objectives:

- Log authorization denials without exposing sensitive details to clients.
- Distinguish authentication failures (401) from authorization failures (403).
- Add request/module/action context to security logs.
- Avoid logging secrets, tokens, or protected response data.

### Phase 7 — Generate and implement tests

Once policy decisions are stable:

1. Generate an RBAC test matrix from accepted entries in `decisions.md`.
2. Add focused PHPUnit tests for `Acl` and `AclMapCatalog`.
3. Add query-model tests for `Permission::findEffectivePermissions`.
4. Add restricted-role fixtures for API tests.
5. Cover direct actions, customs, relationships, related routes, and clause denial.
6. Run the existing API catalog to detect regressions for authorized users.

---

## 7. Security invariants

These invariants must remain true throughout implementation:

1. Permissions authorize **actions**, identified by `resourceName` (`module.action`).
2. Permissions are granted to **roles**; users inherit them through memberships.
3. Direct and relationship access to related data must not bypass related-module authorization.
4. Denial of an embedded relationship does not deny an otherwise authorized parent.
5. Relationship-only routes do not bypass related-model authorization.
6. Unauthorized relationships are removed before their joins or secondary queries execute.
7. Unauthorized active query criteria deny the request before query construction.
8. Permission persistence models do not make request authorization decisions.
9. Only the request-scoped `Acl` instance holds effective access state for a request.
10. Authentication failure and authorization denial remain distinct.
11. The action catalog comes from controller `$aclMap`, not from a `resources` table.

---

## 8. Migration guidelines

- Preserve current external behavior unless a decision explicitly changes it.
- Make one policy change at a time and record it in `decisions.md`.
- Prefer completing the action-based runtime path before expanding field or project-scope rules.
- Keep temporary compatibility behavior clearly documented and short-lived.
- Remove dead legacy ACL methods and flag-based permission columns rather than maintaining two enforcement paths.
- Do not introduce RuBAC concepts into RBAC classes, documents, or tests.

---

## 9. Definition of done

RBAC is ready for comprehensive test generation when:

- Runtime authorization is fully action-based for all standard and custom `$aclMap` actions.
- Relationship and clause authorization behave according to accepted decisions.
- Role aggregation mode is deterministic and configurable.
- Default-allow vs default-deny is explicitly decided.
- Field ACL is either redesigned or explicitly deferred.
- ACL administration endpoints are protected.
- No known request parameter can bypass authorization.
- `Acl` remains the single policy boundary and `Permission` remains persistence-only.
- Accepted decisions contain enough expected outcomes to generate unit and API tests.

RBAC implementation is complete when the generated test matrix passes and existing authorized API behavior has no unintended regressions.

---

## 10. Document maintenance

- Update `decisions.md` first when policy changes.
- Update this plan when architecture, sequencing, or completion status changes.
- Completed implementation tasks may remain checked for historical context.
- Open questions belong in `decisions.md`; implementation tasks belong here.
- `rbac-action-based-plan.md` may be treated as historical design discussion; this file is the living implementation plan.

## Change log

| Date | Change |
|------|--------|
| 2026-07-21 | Initial RBAC architecture and implementation plan |
| 2026-07-27 | Updated for action-based RBAC: modern `$aclMap`, `AclMapCatalog`, `resourceName`/`allowed` permissions, role-only grants, removed `$aclMapReplace` |
| 2026-08-10 | D-052 usable-member lockout: Active `accountStatus` required; user deactivate/delete guarded in `UserController` |
| 2026-08-09 | Phase 5: retired Global default role, bootstrapped full-catalog Admin role, removed `AclAdminController` bypass, added capability-based `AclLockoutGuard` invariant (see decisions D-050–D-054) |
