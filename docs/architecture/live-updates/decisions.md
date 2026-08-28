# Live-updates decisions (Gaia)

Agreed publishing decisions for Gaia. Hermes routing and Prometheus handlers
are out of scope here.

| Field | Value |
|-------|-------|
| Status | Accepted |
| Scope | Gaia → Hermes ingest after persist |
| Primary code | `app/api/v1/controllers/components/events/` |

---

## How to use this file

- Add a new decision whenever publishing behavior is agreed or changed.
- Record Gaia-owned choices only (what to emit, when, and how). Relay rooms
  and Ember screens belong in Hermes / Prometheus docs.
- Mark open questions separately so they are not treated as settled policy.

---

## Architecture decisions

### D-001 — Gaia publishes facts, never rooms

| | |
|---|---|
| Status | Accepted |
| Decision | After a successful persist, Gaia POSTs a domain-event envelope to Hermes. It does not name Socket.IO rooms, keep socket sessions, or target browsers. |
| Rationale | Persist and authorization stay in Gaia. Fan-out is Hermes. UI application is Prometheus. |
| Implications | Controllers and models call `HermesPublisher`. They never emit Socket.IO events. |

### D-002 — Ingest is fail-open

| | |
|---|---|
| Status | Accepted |
| Decision | Hermes HTTP failures, unknown event names, and missing project ids are logged and ignored. The REST (or model) save still succeeds. |
| Rationale | Live updates are a best-effort side effect. A down relay must not roll back or 500 a write the user already made. |
| Implications | `HermesPublisher` catches throwables. Missing required envelope fields return `null` without posting. |

### D-003 — Event names are an allowlist in Gaia

| | |
|---|---|
| Status | Accepted |
| Decision | Publishable names live in `EventNames`. `HermesPublisher` refuses anything outside `EventNames::ALL` before HTTP. |
| Rationale | Hermes will reject unknown names anyway. Failing locally keeps garbage off the wire and documents the contract in PHP. |
| Implications | Adding a live event requires `EventNames` plus the matching Hermes allowlist (`hermes/src/contract/names.js`). |

### D-004 — REST resources use controller components; notifications use a model hook

| | |
|---|---|
| Status | Accepted |
| Decision | Issue, milestone, conversation, comment, and vote live events attach via controller `$uses` (`Events\\…LiveEvents`). `notification.created` is invoked from `Systemnotificationrecipient::afterCreate`. |
| Rationale | Nested recipient rows are not created through REST, so a controller mixin never runs for them. |
| Implications | Do not reintroduce a generic model `afterCreate` live-sync for REST resources. Do not attach `NotificationLiveEvents` as a controller `$uses` mixin expecting recipient creates. |

### D-005 — Domain rules stay in components; HTTP/contract stay in `support/`

| | |
|---|---|
| Status | Accepted |
| Decision | `EventNames`, `LiveEventEnvelope`, `HermesPublisher`, and `ProjectIdResolver` live under `events/support/`. Each `*LiveEventsComponent` owns which fields and which event name fire for its resource. |
| Rationale | Envelope shape and ingest should not be copied per resource. Resource-specific “did status change?” logic should not live in the HTTP client. |
| Implications | New resources add a component (and `$uses`), not a new publisher class. |

### D-006 — User-scoped events reuse `projectId` as `user:<userId>`

| | |
|---|---|
| Status | Accepted |
| Decision | `notification.created` sets `projectId` to `user:` plus the recipient user id. There is no separate `scope` field on the envelope. |
| Rationale | Hermes already keys composite rooms on `(projectId, eventName)`. Reusing the field routes only to that recipient without a contract change. |
| Implications | Gaia must not put a real project id on notification envelopes. Replacing this with a dedicated `scope` field is a cross-repo contract change, not a Gaia-only fix. |

### D-007 — Comments and votes are live only on conversation rooms

| | |
|---|---|
| Status | Accepted |
| Decision | Comment and vote components publish only when `relatedTo` is `conversationroom` / `conversationrooms`. |
| Rationale | Issue comments, wiki comments, and similar threads are not subscribed by any Prometheus screen today. |
| Implications | `LiveEventsComponent::isConversationRelated()` is the shared gate. Issue/wiki comment live sync is out of scope until a client registers for it. |

### D-008 — Publish selective named events, not a generic “resource changed”

| | |
|---|---|
| Status | Accepted |
| Decision | Gaia emits specific names (`issue.status.changed`, `issue.assignee.changed`, …) with a `changes` payload for those fields. It does not emit a catch-all `resourceChanged`. |
| Rationale | Prometheus screens subscribe per event. A generic blob would force every open screen to interpret every write. |
| Implications | A new UI concern needs a new allowlisted name and a component branch, not a wider payload on an existing event. |

### D-009 — Machine ingest authenticates with the shared secret

| | |
|---|---|
| Status | Accepted |
| Decision | Gaia → Hermes `POST /publish` sends `X-Hermes-Secret` from `config/hermes.php` (`HERMES_SECRET`). This is not the user’s OAuth token. |
| Rationale | Ingest is a server-to-server call. Socket auth (user `GET /api/v1/user/me`) is a different door, owned by Hermes. |
| Implications | Do not send the current user’s access token as the publish credential. Do not treat removing query-string socket tokens as a reason to drop this header. |

---

## Open questions

- Replace `projectId: "user:…"` with a real `scope` field (requires Hermes + Prometheus).
- Live events for issue comments, wiki, activity, or issue list/detail screens.
- Sharing the allowlist as a package instead of lockstep PHP / JS copies.
