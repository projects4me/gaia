# RBAC Decisions

Agreed authorization decisions for Gaia. This file is the living source of truth for RBAC behavior. When RBAC work is complete enough, test cases will be generated from these decisions.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend authorization |
| Primary code | `core/libs/security/Acl.php`, `app/models/Permission.php`, `core/mvc/controllers/RestController.php` |

---

## How to use this file

- Add a new decision whenever behavior is agreed or changed.
- Prefer concrete expected outcomes (request → response) over implementation detail.
- Mark open questions separately so they are not treated as settled policy.
- Do not invent tests from open questions.

---

## Architecture decisions

### D-001 — Split policy from persistence

| | |
|---|---|
| Status | Accepted |
| Decision | Authorization policy lives in `Gaia\Libraries\Security\Acl`. `Permission` is a persistence/query model only. |
| Rationale | Keep REST controllers thin and keep allow/deny logic out of the Active Record model. |
| Implications | RestController wires HTTP context into `Acl` and registers the request-scoped `acl` service in DI. Field filtering uses `Acl`, not `Permission`. |

### D-002 — RestController stays HTTP-focused

| | |
|---|---|
| Status | Accepted |
| Decision | `RestController::authorize()` only authenticates the request, maps the action to a permission flag, and delegates RBAC decisions to `Acl`. |
| Rationale | RBAC rules will grow; controller code should not accumulate policy branches. |
| Implications | Model access, relationship filtering, related-route access, and field ACL are owned by `Acl`. |

### D-003 — Permission model loads effective rows only

| | |
|---|---|
| Status | Accepted |
| Decision | `Permission::findEffectivePermissions($userId, $action, $projectId)` returns effective permission rows keyed by resource entity. It does not throw access exceptions or filter API responses. |
| Rationale | Data loading and policy evaluation are separate concerns. |

---

## Access evaluation decisions

### D-010 — Binary permission flags with legacy normalization

| | |
|---|---|
| Status | Accepted |
| Decision | Runtime flags are treated as allow (`1`) or deny (`0`). Legacy non-zero values and `null` normalize to allow. Empty string normalizes to deny. |
| Rationale | Preserve current stored data behavior until a migration cleans legacy values. |
| Implications | Tests must cover `0`, `1`, `null`, empty string, and legacy values such as `2`. |

### D-011 — Catalog defaults materialize at role create; missing rows follow live mode

| | |
|---|---|
| Status | Accepted |
| Decision | On role create, `RolePermissionSeeder` writes an explicit permission row for every resource in the current ACL catalog (module actions + field get/create/update triples). Seed values come from the then-current `system.acl.resolutionMode`: **permissive** → allow / field `write`; **restrictive** → deny / field `none`. Role **name** does not change the seed (privilege is capability in stored grants, D-052). After seeding, those rows are durable — flipping resolution mode later does **not** rewrite or reinterpret them. If a resource has **no** permission row for the user/role context (catalog growth, unseeded legacy roles, role-less users), access still follows live mode: allow under permissive, deny under restrictive. |
| Rationale | Administrators need each role’s birth catalog to be explicit and durable, while new catalog entries keep the same missing-row backend model until an admin sets them. |
| Implications | Permission unset/delete must become explicit deny / field `none` (D-037), not row removal that resurrects live-mode gaps for formerly seeded resources. Existing roles are not auto-backfilled. |

### D-012 — Direct resource denial returns 403

| | |
|---|---|
| Status | Accepted |
| Decision | When the user is denied the mapped action on the base resource, the API throws `Gaia\Exception\Access` (HTTP 403). |
| Examples | `GET /api/v1/issue` with `Issue.readF = 0` → 403. |

### D-013 — Action mapping for REST verbs

| | |
|---|---|
| Status | Accepted |
| Decision | REST actions map to permission flags as follows: |

| REST action | Permission flag |
|-------------|-----------------|
| `get`, `list`, `related` | `readF` |
| `post` | `createF` |
| `put`, `patch` | `updateF` |
| `delete` | `deleteF` |

### D-014 — Multiple role flags use a selectable resolution mode

| | |
|---|---|
| Status | Accepted |
| Decision | `Acl` combines normalized flags from all applicable roles using an explicit resolution mode. The same mode also seeds catalog defaults at role create (D-011) and resolves missing rows when no grant exists. |
| Permissive | Any applicable role with `1` allows access. Missing rows allow. Access is denied only when permission rows exist and none allow it. |
| Restrictive | Any applicable role with `0` denies access. Missing rows deny. Access is allowed only when permission rows exist, at least one allows it, and none deny it. |
| Default | `permissive`, preserved as `system.acl.resolutionMode`. |
| Configuration | Read from `system.acl.resolutionMode` (server-side config only; not required on the Systemsetting API for clients). Selectable at runtime via the `Acl` constructor or `setPermissionResolutionMode()`. Flipping the mode does **not** rewrite stored permission rows. |
| Developer API | `Acl::RESOLUTION_PERMISSIVE` / `Acl::RESOLUTION_RESTRICTIVE`; `Acl::resolveConfiguredResolutionMode()` reads config; `setPermissionResolutionMode()` overrides in-process. |
| Project scope | `Permission` must return permission rows for every role the user holds in the selected project; `Acl` owns their aggregation. |

---

## Relationship access decisions

### D-020 — Same related-model permission for direct and embedded access

| | |
|---|---|
| Status | Accepted |
| Decision | Relationship data is authorized against the related model's permission for the same action, not against a separate relationship resource. |
| Examples | Project `issues` is authorized with `Issue.readF`. Project `members` (hasManyToMany) is authorized with `User.readF` (secondary/API-visible model). |

### D-021 — Unauthorized eager relationships are omitted, not fatal

| | |
|---|---|
| Status | Accepted |
| Decision | If the user cannot perform the action on a related model requested through `rels` / `include`, that relationship is dropped from the authorized relationship list. The parent resource remains accessible. |
| Examples | User may read Project, may not read Issue. `GET /api/v1/project?rels=issues` → 200 with Project, without `issues` linkage or Issue records in `included`. |

### D-022 — Filter before query construction

| | |
|---|---|
| Status | Accepted |
| Decision | Unauthorized relationships are removed before model query/joins run. |
| Rationale | Avoid unnecessary joins and prevent request parameters from reintroducing denied relationship data. |
| Implications | `getAction` / `listAction` consume `$authorizedRels` from `authorize()` instead of re-parsing raw request params after authorization. |

### D-023 — Related-resource route is direct access

| | |
|---|---|
| Status | Accepted |
| Decision | `GET /api/v1/{resource}/{id}/{relation}` is authorized as direct access to the related model. Denial throws 403. |
| Rationale | That route returns the related model's own data, not an embedded parent relationship payload. |
| Examples | `GET /api/v1/project/{id}/issues` with `Issue.readF = 0` → 403. |

### D-024 — Mixed sibling relationships are filtered independently

| | |
|---|---|
| Status | Accepted |
| Decision | Each requested relationship alias is evaluated independently. Allowed aliases are kept; denied aliases are dropped. |
| Examples | `rels=issues,members` with Issue denied and User allowed → response may include `members`, must not include `issues`. |

### D-025 — Canonical relationship request parsing

| | |
|---|---|
| Status | Accepted |
| Decision | Relationship aliases are parsed once, trimmed, de-duplicated, and action-aware: |
| Details | - `get`: merge `rels` and `include` |
|  | - `list` and other non-related actions: use `rels` only |
|  | - `related`: use the route `relation` segment and authorize it as direct related-model access |

### D-026 — Current loader depth is one relationship level

| | |
|---|---|
| Status | Accepted (current capability) |
| Decision | Gaia currently loads sibling, one-level relationships only. Dotted/chained paths are not supported by the loader and remain invalid. |
| Implications | Relationship ACL authorizes every alias the loader accepts. Nested leakage through multi-level expansion is out of scope until chained loading exists. |

### D-027 — Unauthorized query criteria deny the request

| | |
|---|---|
| Status | Accepted |
| Decision | A model or field referenced in `query`, `sort`, `group`, or `having` must be authorized for the current action. Unauthorized active criteria cause HTTP 403 before query construction. |
| Rationale | Filtering, ordering, or grouping by protected data can disclose it indirectly. Silently removing a criterion can also change boolean query semantics. |
| Behavior | Passive unauthorized `rels` / `include` values are still omitted under D-021. If the same relationship is used by both an eager relationship and an active clause, active usage wins and the request is denied. |
| Error | Use a generic access-denied message that does not identify the protected model or field. |
| Validation | Unknown aliases remain the responsibility of normal query validation; ACL only evaluates the base model and relationships known through metadata. |

---

## Authorization group decisions

### D-028 — Module-level authorization groups

| | |
|---|---|
| Status | Accepted |
| Decision | A model may declare authorization groups in metadata. Access to the model requires the requested `{module}.{action}` **and** `{group}.get` for every listed group. |
| Marker | `'acl' => ['group' => true]` marks a model as an authorization group (initially `Project`, `Conversationroom`). |
| Dependencies | `'acl' => ['groups' => ['Project', ...]]` lists required groups on dependent models. |
| Evaluation | Centralized in `Acl::isModelActionAllowed()` — no record/parent-ID checks; no FK auto-discovery. |
| Examples | Issue/Wiki/Milestone (and other project child modules from Prometheus `app.project.*`) with `groups => ['Project']`: `{module}.get=1` and `project.get=0` → deny. Comment with `groups => ['Project', 'Conversationroom']`: all three of `comment.*`, `conversationroom.get`, and `project.get` must allow. |
| Relationships | Eager `rels` omit a related alias when the related model or any of its groups fails. Related routes and active query clauses deny (403) under the same rule. |

---

## Field access decisions

### D-030 — Field denial omits attributes; it does not 403 the resource

| | |
|---|---|
| Status | Accepted |
| Decision | Denied field resources use action-based identity `{module}.{field}.{action}` (e.g. `issue.subject.get`). On read with a default/full select, denied attributes are **omitted** from the JSON:API attributes object (key absent, not set to `null`). They do not deny the parent resource. |
| Owner | `Acl::filterAuthorizedFields()` (pre-query SELECT discard) and response preparation (`applyACLOnFields` / `filterFieldsByACL` as defense-in-depth). |
| Legacy | Do not keep `Model.field` (e.g. `Issue.subject`) as a long-term identity. |

### D-031 — Field identity and allowed field actions

| | |
|---|---|
| Status | Accepted |
| Decision | Field resource names are `{module}.{field}.{action}` with actions limited to `get`, `create`, and `update`. There is no field-level `delete` (module `*.delete` only). Catalog entries are **business attributes** from model metadata for ACL-allowed models. |
| Catalog exclusions | Structural linkage (D-035); fields with `'acl' => false`; `'secure' => true`; `'linkedTo'` derived/display fields. |
| Storage | Same `permissions` table (`resourceName` + `allowed` + `roleId`). |

### D-032 — Module action is a prerequisite for field access

| | |
|---|---|
| Status | Accepted |
| Decision | Field access requires the matching module action first: e.g. `issue.get` before any `issue.*.get`, `issue.create` before any `issue.*.create`, `issue.update` before any `issue.*.update`. |
| Implications | `Acl::isFieldActionAllowed` checks module then field resource. |

### D-033 — Denied fields in `fields=` are omitted (not 403)

| | |
|---|---|
| Status | Accepted |
| Decision | When the client names fields in `fields=`, each field is authorized with `get` the same as a default select: **allowed fields are kept; denied fields are dropped from the SELECT**. The request is **not** denied with HTTP 403 for field ACL. |
| Rationale | Field denial must not surface as Access Denied; silent omit is consistent for default select and explicit `fields=`. |
| Owner | `Acl::filterAuthorizedFields()` via `Query::filterFieldsByAcl()`. |
| Contrast | Unauthorized fields in `query` / `sort` / `group` / `having` still 403 (D-036) — those change result membership/order. |

### D-034 — Denied field in write body is discarded

| | |
|---|---|
| Status | Accepted |
| Decision | When a denied field attribute is present in a POST (`create`), PUT, or PATCH (`update`) body, that attribute is **removed** before assign/save. The request continues for allowed fields. HTTP 403 is reserved for module-level action denial (`issue.create` / `issue.update`), not field denial on write. |

### D-035 — id and linkage FKs bypass field ACL

| | |
|---|---|
| Status | Accepted |
| Decision | Structural linkage fields always remain selectable and are not subject to field deny (catalog omit + runtime bypass). Includes: `{Model}.id`; fields marked `identifier` / `relatedIdentifier`; belongsTo/hasOne `primaryKey` FKs; and any attribute whose name ends in `Id` (e.g. `roleId`, `projectId`, `typeId`) even when relationship metadata is incomplete. |
| Owner | `AclMapCatalog::getStructuralFieldNames()` / `isStructuralFieldName()`; `Acl::isStructuralField()` delegates to the same rules. |
| Rationale | Relationship wiring must not depend on field-ACL grants. Incomplete metadata previously leaked FKs like `permission.roleId` and `issuetype.projectId` into the permission UI. |

### D-036 — Field denial in query clauses returns 403

| | |
|---|---|
| Status | Accepted |
| Decision | A field referenced in `query`, `sort`, `group`, or `having` must be authorized for field `get` (in addition to the related module action). Unauthorized active field criteria cause HTTP 403. Criteria are never silently removed. |
| Relationships | Extends D-027 to cover field-level resources. |

### D-037 — Field access modes (None / Read Only / Write + Read)

| | |
|---|---|
| Status | Accepted |
| Decision | Field ACL is administered as one of three modes per field, not as independent get/create/update toggles. Modes expand to stored `{module}.{field}.{action}` flags: **None** → get/create/update = 0; **Read Only** → get = 1, create/update = 0; **Write + Read** → get/create/update = 1. There is no write-only mode — write always includes read. |
| API surface | Clients see/write one permission per field: `resourceName = {module}.{field}` (e.g. `issue.subject`) with `allowed` ∈ `''` \| `none` \| `read` \| `write`. Direct writes to `{module}.{field}.{get\|create\|update}` are rejected. |
| Storage | Same action triples in `permissions` (`issue.subject.get`, `…create`, `…update`). `Permission::expandFieldAccessMode` / `deriveFieldAccessMode` and `PermissionController` own collapse/expand. Unset (`allowed=''`) and DELETE coerce to **`none`** (triples = 0) — rows are never removed so live mode cannot reinterpret a cleared grant. |
| Evaluation | If create or update is **explicitly** allowed (`allowed = 1`), field `get` is also allowed (write⇒read). Missing write rows do not promote get (preserves D-011 / explicit deny). |
| Implications | After PATCH/POST, no special read-after-write path is required: a writable field is always readable under this combo. Frontend sends one request per field mode change. |

---

## Administration and bootstrap decisions

### D-050 — Retire the "Global" default role

| | |
|---|---|
| Status | Accepted |
| Decision | There is no default role automatically created or assigned on user creation. The `GlobalroleComponent` behavior (`app/api/v1/controllers/components/GlobalroleComponent.php`) is removed, along with its wiring on `UserController::$uses`. Existing `Global` role memberships, its permission rows, and the role itself are removed through data/ops cleanup (a dedicated CLI cleanup task is deferred). |
| Rationale | Role assignment is explicit (userrole API / role detail UI / optional role picker on user create). An implicit default membership is a silent, unaudited access grant that no longer matches the action-based RBAC model. |
| Implications | A newly created user has zero roles unless one is explicitly assigned. Under D-011 (missing permission row means allow), a role-less user is not automatically locked out of anything — this is unchanged legacy behavior and is tracked as an open question below, not solved by this decision. |

### D-051 — "Admin" is a bootstrap role, not a policy bypass

| | |
|---|---|
| Status | Accepted |
| Decision | A role named `Admin` is the conventional bootstrap full-catalog administration role in baseline dump / ops data. It is **not** privileged by name at create or evaluation time: `RolePermissionSeeder` seeds it like any other role from the then-current resolution mode, and `Acl` never treats a role name as an automatic allow. Admin-capable access is exactly what `permissions` rows grant (D-052). Existing Admin grants remain dump/ops managed for pre-existing installs; no automatic backfill. |
| Rationale | Keeps a single enforcement path (`Acl` + `permissions` rows). Avoids a second, implicit definition of "administrator" living in role names or outside the permission store. |
| Implications | Creating or renaming a role to `Admin` under restrictive mode does not grant power — operators must raise grants (or create under permissive) like any other role. Catalog growth gaps follow live mode (D-011) until refreshed. Renaming, editing, or deleting the bootstrap `Admin` role is permitted subject to D-052. |

### D-052 — Security-core lockout invariant (capability-based, not a role flag)

| | |
|---|---|
| Status | Accepted |
| Decision | No `isSystem` (or similar) column exists on `roles`. Instead, a role is **admin-capable** when it has no explicit deny (`allowed` normalizing to `0`, including `''`) for any of the fifteen lockout-covered resource names `{permission,role,userrole}.{get,create,update,delete}` plus `user.{create,update,delete}` (`user.get` is out of scope) — a missing row is capable under the default permissive resolution mode (mirrors `Acl::isResourceAllowed()`/D-011/D-014). The system must always have at least one non-deleted role that is admin-capable **and** has at least one **usable** `user_roles` membership (non-deleted assignment whose user is non-deleted and `accountStatus` is `Active`, case-insensitive). `Gaia\Libraries\Security\AclLockoutGuard` (`core/libs/security/AclLockoutGuard.php`) is the single implementation of this predicate. |
| Enforced at | `RoleController::deleteAction()` (role delete), `PermissionController::assertAclLockoutSafe()` (a lockout-covered resource write that would set an explicit deny), `UserroleController::deleteAction()` (removing the last usable membership of the last capable role), `UserController::patchAction()` / `deleteAction()` (deactivating or deleting the last usable member of the last capable role). Each simulates the pending change and rejects with `Gaia\Exception\Permission` (422) when no role would remain capable-with-a-usable-member. |
| Rationale | Consistent with action-based RBAC: capability lives in `permissions` rows, not in role metadata. Supports renaming/replacing/deleting the bootstrap `Admin` role, or having multiple admin-capable roles, as long as the invariant holds. Avoids a second, role-flag-based source of privilege alongside the permission store. Includes `user` write actions so the last capable role cannot lose the ability to create/update/delete users while still administering ACL. Membership must be *usable* so deactivating (`accountStatus` away from Active) or deleting the sole active admin cannot lock operators out while a dormant `user_roles` row still exists. |
| Non-goals | Granting/removing access to modules *other than* `permission`, `role`, `userrole`, and `user` write actions is never blocked by this invariant. `user.get` and field ACL on these modules are out of scope for the predicate. Only `accountStatus` Active (case-insensitive) counts as usable; other statuses (`invited`, `Inactive`, etc.) do not. This decision assumes the default `RESOLUTION_PERMISSIVE` mode; if restrictive mode is ever exposed through configuration (Phase 3 of the implementation plan), the predicate must be revisited. |
| Examples | Two roles both admin-capable with Active members → deleting one succeeds. One capable role with one Active member → deleting it, demoting its `permission.delete` or `user.delete` to `0`, removing its last `userrole` membership, or setting that member's `accountStatus` to a non-Active value are all rejected (422) with a message naming the requirement, not the specific role. Denying only `user.get` does not remove capability. Inactive/`invited` memberships never satisfy the invariant. |

### D-053 — Optional role assignment on user create

| | |
|---|---|
| Status | Accepted |
| Decision | Role assignment on user creation remains optional on both frontend (`AppUserCreateController`) and backend (no implicit role created). A user may exist with zero `user_roles` memberships. |
| Rationale | Matches retiring the Global default (D-050); administrators choose roles explicitly through the existing optional picker or later assignment via the role detail page. |
| Implications | Default-allow vs default-deny for missing permission rows (D-011) still determines what a role-less user can do; that remains an open question, not resolved here. |

### D-054 — ACL administration authorized like any other module

| | |
|---|---|
| Status | Accepted |
| Decision | `AclAdminController` and its role-name-based `checkAdminAccess()` bypass are removed. `PermissionController` extends `RestController` directly and is authorized the same way as every module: through `RestController::authorize()` resolving `permission.{get\|create\|update\|delete}` against the requesting user's effective permissions. |
| Rationale | Removes a second, disabled admin gate (`return true;`) in favor of the one enforcement path the rest of RBAC already relies on. A role only administers permissions if it actually holds `permission.*` grants (as `Admin` does via D-051). |
| Implications | Any role granted `permission.*` / `role.*` / `userrole.*` / `user.{create,update,delete}` can administer security-critical modules — by design (D-052 guards against losing every such role, not against there being more than one). |

---

## Testing posture

### D-040 — Defer comprehensive RBAC tests until RBAC surface is more complete

| | |
|---|---|
| Status | Accepted |
| Decision | Do not build the full unit/api-tester matrix yet. Continue RBAC implementation, keep this decisions file current, then generate tests from the final set of decisions. |
| Rationale | Nearby RBAC work will still change boundaries (project scope, row-level rules, more route cases). Early broad tests would churn. |

### D-041 — Future test generation source

| | |
|---|---|
| Status | Accepted |
| Decision | This markdown file is the source for later test generation. Each accepted decision with concrete request/response examples should become one or more automated cases. |

---

## Expected scenarios already agreed

These scenarios are settled and should become tests later:

1. **Direct Issue read denied**
   - Given `Issue.readF = 0`
   - `GET /api/v1/issue` → 403

2. **Project with unauthorized issues relationship**
   - Given `Project.readF = 1`, `Issue.readF = 0`
   - `GET /api/v1/project?rels=issues` → 200 Project, no `issues`, no Issue in `included`

3. **Project with authorized issues relationship**
   - Given `Project.readF = 1`, `Issue.readF = 1`
   - `GET /api/v1/project?rels=issues` → 200 Project including issues data when present

4. **Related route denied**
   - Given `Issue.readF = 0`
   - `GET /api/v1/project/{id}/issues` → 403

5. **Mixed relationships**
   - Given Issue denied, User allowed
   - `GET /api/v1/project/{id}?rels=issues,members` → members may appear; issues must not

6. **Unauthorized relationship used by a query clause**
   - Given `Issue.readF = 0`
   - A Project request filtering, sorting, grouping, or applying `having` through `issues.*` → 403

7. **Unauthorized field used by a query clause**
   - Given the related model is readable but its referenced field is denied (`{module}.{field}.get = 0`)
   - A request using that field in `query`, `sort`, `group`, or `having` → 403

8. **Denied field on default read**
   - Given `issue.subject.get = 0` and `issue.get = 1`
   - `GET /api/v1/issue/{id}` → 200; `subject` attribute key omitted

9. **Denied field in `fields=` is omitted**
   - Given `issue.subject.get = 0`, `issue.description` readable
   - `GET /api/v1/issue?fields=Issue.subject,Issue.description` → 200; `subject` omitted, `description` kept

10. **Denied field in PATCH body**
    - Given `issue.subject.update = 0` and `issue.update = 1`
    - `PATCH /api/v1/issue/{id}` with `subject` (and other attrs) in attributes → 200; `subject` discarded, other attrs saved

11. **Module prerequisite for field access**
    - Given `issue.get = 0` (module denied)
    - Field resources under `issue.*` are not usable; parent resource request → 403

12. **Missing field permission row**
    - Given no row for `issue.subject.get` and permissive resolution
    - Field is allowed (D-011)

---

## Open questions

Track unsettled policy here. Do not generate tests from these until accepted.

- [ ] Should missing permission rows eventually become default-deny? *(Partially addressed by D-011 materialization: birth catalog is explicit; missing rows still follow live mode for catalog growth / unseeded roles.)*
- [ ] How should project-scoped permissions interact with global/system memberships for relationship filtering?
- [ ] Is row-level / ownership-based access in scope for the next RBAC phase?
- [ ] Should denied eager relationships appear as empty linkage (`data: []` / `null`) instead of being omitted entirely?
- [ ] Exact behavior for unauthorized fields inside already-authorized included records under all serializer paths
- [ ] Now that Global is retired (D-050) and role assignment is optional (D-053), a user can have zero roles. Under current permissive default (D-011) this does not restrict them. Revisit if/when default-allow becomes default-deny.
- [ ] Separating the automation/system identity (`User::getSystemUser()`, currently keyed off the role name `Admin`) from the human-administered `Admin` role is deferred.
- [ ] Audit records for permission/role/userrole changes remain unimplemented (Phase 5 objective).
- [ ] Who may grant permissions they do not themselves hold is still unrestricted; only the ACL lockout invariant (D-052 / `AclLockoutGuard`) is enforced today.

---

## Change log

| Date | Change |
|------|--------|
| 2026-08-11 | D-011 revised: full-catalog materialization at role create via `RolePermissionSeeder`, seeded from resolution mode only (no role-name force-allow; privilege stays capability-based, D-052); missing rows still follow live resolution mode (catalog growth). D-014: `system.acl.resolutionMode` is create-time seed + aggregation + missing-row policy; flips do not rewrite rows. D-037: unset/DELETE → explicit `none`/deny (no row delete) |
| 2026-08-10 | D-052: usable membership = Active `accountStatus` (case-insensitive); enforced on user deactivate/delete via `UserController`; `roleMemberCount` joins users and ignores non-Active/deleted members |
| 2026-08-10 | Removed deferred `AclTask` CLI (ensureAdmin / cleanupGlobalRole); D-050/D-051 now describe dump/ops seeding instead of that task |
| 2026-08-10 | D-052: renamed implementation to `AclLockoutGuard` (`getLockoutResources`, `findLockoutPermissionRows`, `isAdminCapableRole`, `systemRetainsAdminPath`); capability also requires `user.{create,update,delete}` (`user.get` out of scope); resource set is fifteen |
| 2026-08-09 | D-050–D-054: retire Global default role; Admin is a bootstrap full-catalog role (no Acl bypass); capability-based security-core lockout invariant (no `isSystem` flag); optional userrole on create; `AclAdminController` bypass removed, `PermissionController` authorized like any other module |
| 2026-08-04 | D-037: Permission API collapses/expands field modes; clients use `{module}.{field}` + none\|read\|write only |
| 2026-08-04 | D-033 revised: denied `fields=` entries are omitted (not 403); clause field denial still 403 |
| 2026-08-03 | D-037: field ACL administered as None / Read Only / Write + Read; write implies read |
| 2026-08-03 | D-034 revised: denied write fields are discarded; 403 reserved for module action ACL |
| 2026-08-03 | Field ACL: action-based identity, omit (not null), full metadata catalog, explicit fields=/body/clause 403, module prerequisite, id/FK bypass (D-030–D-036) |
| 2026-07-21 | Initial decisions from relationship data-level ACL work and Acl/Permission refactor |
