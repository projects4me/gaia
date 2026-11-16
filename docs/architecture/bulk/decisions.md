# Bulk API decisions (Gaia)

Agreed decisions for running several ordinary REST calls inside one HTTP
request. The bulk resource itself (its request shape and validation) is
recorded here when it lands.

| Field | Value |
|-------|-------|
| Status | Accepted |
| Scope | In-process sub-requests for the Bulk API |
| Primary code | `core/mvc/http/` |

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
