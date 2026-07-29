# RBAC Decisions

Agreed authorization decisions for Gaia. This file is the living source of truth for RBAC behavior. When RBAC work is complete enough, test cases will be generated from these decisions.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend authorization |
| Primary code | `core/libs/security/acl.php`, `app/models/Permission.php`, `core/mvc/controllers/RestController.php` |

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

### D-011 — Missing permission row means allow

| | |
|---|---|
| Status | Accepted (legacy) |
| Decision | If a resource has no permission entry for the user/role context, access is allowed. |
| Rationale | Existing Gaia behavior. Changing default-deny is out of scope for the current relationship work. |
| Implications | Explicit `readF = 0` is required to deny a resource. |

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
| Decision | `Acl` combines normalized flags from all applicable roles using an explicit resolution mode. |
| Permissive | Any applicable role with `1` allows access. Access is denied only when permission rows exist and none allow it. |
| Restrictive | Any applicable role with `0` denies access. Access is allowed only when permission rows exist, at least one allows it, and none deny it. |
| Default | `permissive`, preserving existing Gaia behavior. |
| Developer API | Select with the `Acl` constructor or `setPermissionResolutionMode()` using `Acl::RESOLUTION_PERMISSIVE` / `Acl::RESOLUTION_RESTRICTIVE`. |
| Future configuration | The same mode will later be read from application configuration without changing policy evaluation code. |
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

## Field access decisions

### D-030 — Field denial omits/nulls values; it does not 403 the resource

| | |
|---|---|
| Status | Accepted |
| Decision | Denied field resources (`Model.field`) return false from access checks and are filtered out of the response field set. They do not deny the parent resource. |
| Owner | `Acl::applyACLOnFields()` / `getAllowedFields()`, applied during response preparation. |

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
   - Given the related model is readable but its referenced field is denied
   - A request using that field in `query`, `sort`, `group`, or `having` → 403

---

## Open questions

Track unsettled policy here. Do not generate tests from these until accepted.

- [ ] Should missing permission rows eventually become default-deny?
- [ ] How should project-scoped permissions interact with global/system memberships for relationship filtering?
- [ ] Is row-level / ownership-based access in scope for the next RBAC phase?
- [ ] Should denied eager relationships appear as empty linkage (`data: []` / `null`) instead of being omitted entirely?
- [ ] Exact behavior for unauthorized fields inside already-authorized included records under all serializer paths

---

## Change log

| Date | Change |
|------|--------|
| 2026-07-21 | Initial decisions from relationship data-level ACL work and Acl/Permission refactor |
