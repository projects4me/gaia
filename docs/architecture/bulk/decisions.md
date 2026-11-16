# Bulk API decisions (Gaia)

Agreed decisions for running several ordinary REST calls inside one HTTP
request, and the `bulk` resource (`POST /api/v1/bulk`) that exposes them:
its contract, its limits and what it refuses.

| Field | Value |
|-------|-------|
| Status | Accepted |
| Scope | In-process sub-requests for the Bulk API |
| Primary code | `core/mvc/http/`, `app/api/v1/controllers/BulkController.php` |

---

## How to use this file

- Add a new decision whenever bulk behaviour is agreed or changed.
- Mark open questions separately so they are not treated as settled policy.

---

## Architecture decisions

### D-001 — Sub-requests run in-process, through the normal router and controllers

| | |
|---|---|
| Status | Accepted |
| Decision | `SubRequestRunner::run()` dispatches each call through the Gaia router, the dispatcher and the target controller inside the current PHP request, with its own `SubRequest` and response, and returns a `SubResult` (status, decoded body, duration) without sending anything. Gaia exceptions become their own response through `toResponse()`; Phalcon errors go through `PhalconError` exactly as `public/index.php` does; anything else is a generic 500 that is logged and never carries a stack trace. |
| Why | Every call must get the same authentication, ACL, behaviours, components and response shape as the separate HTTP call, so there is one code path to keep secure. A loopback HTTP call per item would cost a PHP worker each, could deadlock a small pool, and would need the token re-sent over the network. |
| Consequences | The runner, not the controllers, isolates the calls. `SubRequestScope` saves and restores the outer request's DI services (request, response, router, dispatcher, acl), the superglobals and the global currentUser, also when the call throws. Phalcon's getMethod(), getURI() and getHeader() are final and read `$_SERVER`, so the superglobals are overlaid while the call runs; the body cannot come from php://input, so `SubRequest::getRawBody()` returns it and `OAuthRequestFactory::fromHttpRequest()` builds the token check's request from it. |

### D-002 — Every sub-request authenticates and authorizes afresh

| | |
|---|---|
| Status | Accepted |
| Decision | Each call carries its own Authorization header and runs the target action's full token and ACL checks. The runner never skips or caches them. |
| Why | Phalcon's dispatcher keeps controllers as shared DI instances and only calls `initialize()` (where `RestController` authorizes) on a fresh one. A second call to the same controller would otherwise reuse the first call's controller, its authorized user and its ACL. |
| Consequences | The runner's dispatcher drops the controller from the DI before building it (`SubRequestScope::evictHandler()`), and the scope drops every controller it dispatched when it leaves. |

### D-003 — Sub-requests run one at a time

| | |
|---|---|
| Status | Accepted |
| Decision | Calls run sequentially in the order given. There is no parallel execution and no transaction around them. |
| Why | PHP has no threads in this stack, and the per-request state the REST code reads (superglobals, DI services, currentUser) can hold one call at a time. Sequential calls also keep the outcome deterministic: a later call sees an earlier call's writes. |
| Consequences | A slow call delays the rest; the bulk resource will limit how many calls one request may hold. A failed call does not undo earlier ones. |

### D-004 — The bulk resource runs a batch and returns one result per call

| | |
|---|---|
| Status | Accepted |
| Decision | `POST /api/v1/bulk` takes `{"requests": [{"id", "method", "url", "body"}]}` and answers 200 with `{"responses": [{"id", "status", "body", "durationMs"}]}` in request order. `id` is a client string unique within the batch; `method` is GET, POST, PATCH or DELETE; `url` is a relative `/api/v1/<resource>...` path with an optional query string; `body` is optional JSON. Each result carries the status and decoded body the separate call would have returned, failures included. Nothing is stored. |
| Why | Screens such as the Task Board and the Dashboard need several calls whose shape is known in advance; one HTTP request instead of one per call saves the round trips. A plain CRUD resource keeps the API to standard actions. |
| Consequences | `BulkController::postAction()` only parses and shapes; `BulkRequestValidator` checks the batch, `BulkExecutor` loops over `SubRequestRunner` with the caller's Authorization header. A failed call affects only its own result, and the batch still answers 200. |

### D-005 — A batch is validated as a whole before anything runs

| | |
|---|---|
| Status | Accepted |
| Decision | The batch is refused with a 400, and no call runs, when the body is over the size limit, `requests` is not a non-empty array, it holds more items than the limit, an id is missing or repeated, a method is not GET, POST, PATCH or DELETE, a body is not JSON, or a URL is not one the REST router resolves for the method. `BulkRoutePolicy` also refuses `/api/v1/bulk` itself (no nesting), resources whose controllers have unauthenticated actions (OAuth token, introspection, password reset, enrollment) and the calls that stream files (upload bytes and upload POST, user images); the download and preview routes are outside `/api/v1` and so refused too. The limits live in `config/bulk.php`: BULK_MAX_REQUESTS (default 25) and BULK_MAX_BODY_BYTES (default 1 MiB). |
| Why | Checking everything first means a malformed batch never leaves half its writes behind. Unauthenticated actions skip the token check the batch relies on, file streams are not JSON, and nesting would multiply the limit. |
| Consequences | The URL check mirrors the path shapes `Router::init()` registers (resource, id, relation), against the configured v1 routes, so a new resource is reachable from a batch without changes here. A new controller with unauthenticated actions is refused automatically (`BulkRoutePolicy::controllerIsAnonymous()`), and `tests/libraries/utils/http/BulkRoutePolicyTest.php` pins the refused set so a change to it is a deliberate one. The classes sit next to `SubRequestRunner` in `core/mvc/http/`, since they are request plumbing rather than domain logic. |

### D-006 — The batch needs bulk.create; every call is authorized on its own

| | |
|---|---|
| Status | Accepted |
| Decision | The bulk call itself needs a valid token and the `bulk.create` permission (declared in the controller's ACL map; no permission seed, the reviewer grants it). Every call in the batch then runs its own token and ACL checks with the caller's Authorization header (D-002). One log line per batch records the user, the item count, the statuses and the total time, never the bodies. |
| Why | A batch can never do more than its separate calls could. Service tokens reach bulk only if a service ACL opts it in (deny by default). |
| Consequences | Denying `bulk.create` blocks batching without touching any other permission; an item the caller may not run comes back with its own 403 while the other items return normally. |

### D-007 — No rollback, no references, no parallelism

| | |
|---|---|
| Status | Accepted |
| Decision | Calls run one at a time in the order sent (D-003). There is no transaction around the batch: a successful write stays when a later call fails. Items cannot refer to each other's results, and there is no all-or-nothing mode. |
| Why | Each call keeps exactly the behaviour of a separate call, including its live events and other side effects; wrapping them in one transaction would change that and hold locks across unrelated calls. |
| Consequences | A client that needs all-or-nothing behaviour sends the writes separately or checks each result. The whole batch runs under the one outer request's PHP time limit; if it times out, the earlier writes stay and no results return, so the item limit stays small. References between items (`$ref`), an all-or-nothing mode and parallel execution are possible later additions, recorded here when agreed. |
