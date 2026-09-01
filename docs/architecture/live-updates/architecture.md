# Live updates — Gaia

Gaia is the source of truth for live updates. After a successful persist it
posts a V2 domain-event envelope to Hermes. It does not name Socket.IO rooms,
keep socket sessions, or know which Prometheus screens are open.

Hermes relay contract: sibling repo `hermes/ARCHITECTURE.md`.
Prometheus consumption: sibling repo `prometheus/docs/architecture/live-updates/`.

```
REST write succeeds
        │
        ▼
 LiveEvents component (or NotificationLiveEvents)
        │
        ▼
 HermesPublisher  POST {HERMES_URL}/publish
                  X-Hermes-Secret
        │
        ▼
 Hermes (relay)
```

## Ownership in this repo


| Piece                    | Job                                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------------- |
| REST controller `$uses`  | Attach a domain `*LiveEvents` component so `afterCreate` / `afterUpdate` / `afterDelete` run after persist. |
| `*LiveEventsComponent`   | Decide *whether* and *which* event to publish from the saved model.                                         |
| `events/support/`        | Envelope, allowlist, project-id resolution, HTTP ingest. Shared, not domain-specific.                       |
| `NotificationLiveEvents` | Same ingest path for recipient rows that never go through REST.                                             |
| Hermes                   | Authenticate sockets and emit `domain:event`. Not Gaia's job.                                               |


## Publish path

`HermesPublisher` builds an envelope via `LiveEventEnvelope` and POSTs it to
`/publish`. Ingest failures are logged and swallowed so the REST response is
unchanged.

```json
{
  "schemaVersion": 2,
  "eventId": "event-uuid",
  "eventName": "issue.status.changed",
  "occurredAt": "2026-08-20T10:00:00Z",
  "projectId": "project-uuid",
  "resource": { "type": "issue", "id": "issue-uuid" },
  "actorId": "user-uuid",
  "changes": { "status": "closed" },
  "meta": { "source": "gaia" }
}
```

`projectId` is a project id for work events. User-scoped notifications put
`user:<userId>` in that field so Hermes can route with the existing composite
key. `actorId` may be `null`. Names outside `EventNames::ALL` are rejected
before HTTP.

## What publishes what

Controller-attached components (REST `$uses`):


| Controller                   | Component                       | Events                                                             |
| ---------------------------- | ------------------------------- | ------------------------------------------------------------------ |
| `IssueController`            | `IssueLiveEvents`               | issue created, status, assignee, dates, dependency created/deleted |
| `MilestoneController`        | `MilestoneLiveEvents`           | milestone created, completed                                       |
| `CommentController`          | `ConversationCommentLiveEvents` | conversation comment created/updated/deleted                       |
| `VoteController`             | `ConversationVoteLiveEvents`    | conversation vote added/removed                                    |
| `ConversationroomController` | `ConversationLiveEvents`        | conversation.created                                               |


Model hook (not REST):


| Model                         | Class                    | Event                                                     |
| ----------------------------- | ------------------------ | --------------------------------------------------------- |
| `Systemnotificationrecipient` | `NotificationLiveEvents` | `notification.created` scoped to `user:<recipientUserId>` |


Comments and votes publish only when `relatedTo` is a conversation room.
Issue comments, wiki comments, and activity rows are not live-synced.

## Config

`config/hermes.php`:


| Key      | Env             | Default                                                                                |
| -------- | --------------- | -------------------------------------------------------------------------------------- |
| `url`    | `HERMES_URL`    | `http://host.docker.internal:9000` (OG `gaia`) / `:9001` (`gaia-test` → `hermes-test`) |
| `secret` | `HERMES_SECRET` | `hermes-dev-secret`                                                                    |


`HermesPublisher` sends the secret as `X-Hermes-Secret`.

Local compose: OG Gaia publishes to Hermes `:9000`; `gaia-test` publishes to `hermes-test` `:9001` (socket auth against `:8081`) so api-tester live mode does not retarget the main Hermes `GAIA_URL`.

## Key files


| Area              | Path                                                                     |
| ----------------- | ------------------------------------------------------------------------ |
| Allowlist         | `app/api/v1/controllers/components/events/support/EventNames.php`        |
| Envelope          | `app/api/v1/controllers/components/events/support/LiveEventEnvelope.php` |
| HTTP ingest       | `app/api/v1/controllers/components/events/support/HermesPublisher.php`   |
| Project id        | `app/api/v1/controllers/components/events/support/ProjectIdResolver.php` |
| Base component    | `app/api/v1/controllers/components/events/LiveEventsComponent.php`       |
| Domain components | `app/api/v1/controllers/components/events/*LiveEventsComponent.php`      |
| Notifications     | `app/api/v1/controllers/components/events/NotificationLiveEvents.php`    |
| Recipient hook    | `app/models/Systemnotificationrecipient.php`                             |
| Config            | `config/hermes.php`                                                      |
| Unit tests        | `tests/api/controllers/components/events/LiveEventsComponentsTest.php`   |
| Live api-tester   | `tools/api-tester/apis/live.json`                                        |


`EventNames` must stay in lockstep with `hermes/src/contract/names.js`.

## Testing

Two layers cover Gaia → Hermes: PHPUnit for publish *rules*, and api-tester live mode for real REST → Hermes wiring (and fail-open). Operator details also live in `tools/api-tester/README.md` § Live mode.

### Unit (PHPUnit)

File: `tests/api/controllers/components/events/LiveEventsComponentsTest.php`


| Test                                                    | Purpose                                                                      |
| ------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `testIssueCreatePublishesAllowlistedFields`             | Issue create emits `issue.created` with allowlisted fields only (no `audit`) |
| `testMultiFieldIssuePatchPublishesEachMatchingEvent`    | One patch can emit status/assignee/dates/dependency events from audit        |
| `testDependencyReplacementPublishesDeleteThenCreate`    | Parent swap → dependency deleted then created                                |
| `testDependencyDeleteUsesOldParent`                     | Clearing parent publishes delete with old parent in meta                     |
| `testNoOpIssueUpdatePublishesNothing`                   | Empty audit → no publish                                                     |
| `testMilestoneCompletedRequiresTransitionIntoCompleted` | `milestone.completed` only on real transition into completed                 |
| `testCommentEventsIgnoreIssueComments`                  | Issue comments ignored; room comments publish create/delete                  |
| `testVoteEventsIgnoreNonConversationVotes`              | Non-room votes ignored; room votes publish add/remove                        |
| `testConversationCreatedOnlyOnCreate`                   | `conversation.created` on create; update is a no-op                          |
| `testComponentsPublishThroughTheirOwnHooks`             | Multi-component smoke; envelope has `schemaVersion`, `eventId`, `projectId`  |
| `testPublisherFailOpenDoesNotThrow`                     | Hermes client throws → no exception; save path continues                     |
| `testPublisherFailOpenOnUnauthorizedDoesNotThrow`       | HTTP 401 from Hermes → still fail-open                                       |
| `testPublisherFailOpenOnTimeoutDoesNotThrow`            | Connect/timeout error → still fail-open                                      |
| `testNotificationCreatedEnvelopeUsesUserScope`          | Notifications use `projectId` = `user:<id>`                                  |
| `testNotificationLiveEventsPicksAllowlistedFields`      | Notification field picking; no audit/metadata leak                           |
| `testPublisherRejectsUnknownEventNames`                 | Unknown names (e.g. `issue.deleted`) never POSTed                            |


Run inside the Gaia app container (needs Phalcon), for example:

```bash
vendor/bin/phpunit -c tests/phpunit.xml \
  tests/api/controllers/components/events/LiveEventsComponentsTest.php
```

### Live (api-tester)

Catalog: `tools/api-tester/apis/live.json`. Default `backend` / `client` / `acl` modes never assert Hermes.

Local compose uses two Hermes services (sibling `hermes` repo):


| Hermes service | Port    | `GAIA_URL`          | Used by                  |
| -------------- | ------- | ------------------- | ------------------------ |
| `hermes`       | `:9000` | OG Gaia `:8080`     | Prometheus / daily dev   |
| `hermes-test`  | `:9001` | `gaia-test` `:8081` | api-tester `--mode live` |


Do not retarget the main Hermes `GAIA_URL` for live tests.

#### Happy path (Hermes up — REST success and matching `domain:event`)


| Case id                                                       | Purpose                                                 |
| ------------------------------------------------------------- | ------------------------------------------------------- |
| `live-setup-issuestatus-for-status-change`                    | Create alt status for the status-change case            |
| `live-issue-create-publishes-issue-created`                   | Real REST create → `issue.created`                      |
| `live-issue-patch-status-publishes-issue-status-changed`      | Real REST status patch → `issue.status.changed`         |
| `live-conversationroom-create-publishes-conversation-created` | Real REST room create → `conversation.created`          |
| `live-conversation-comment-create-publishes-comment-created`  | Real REST room comment → `conversation.comment.created` |


```bash
# hermes repo
docker compose up -d hermes-test

# gaia repo (seeded pr4m_test + gaia-test on :8081)
./tools/api-tester/harness/prepare-test-db.sh   # if needed
./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh \
  --base-uri http://localhost:8081 \
  --mode live \
  --hermes-url http://localhost:9001
```

Requires Node for `tools/api-tester/harness/hermes-wait` (uses local or sibling `hermes/node_modules` for `socket.io-client`).

Optional: `--filter 'setup|publishes'` to skip fail-open cases when running against hermes-test.

#### Fail-open (Hermes unreachable — REST still succeeds)

Uses `gaia-test-hermes-down` (`HERMES_URL=http://127.0.0.1:9`). No `--hermes-url` / Socket.IO.


| Case id                                                                       | Purpose                          |
| ----------------------------------------------------------------------------- | -------------------------------- |
| `live-fail-open-setup-issuestatus`                                            | Setup status for fail-open patch |
| `live-issue-create-succeeds-when-hermes-unreachable-fail-open`                | Issue create still `201`         |
| `live-issue-patch-status-succeeds-when-hermes-unreachable-fail-open`          | Status patch still `200`         |
| `live-conversationroom-create-succeeds-when-hermes-unreachable-fail-open`     | Room create still `201`          |
| `live-conversation-comment-create-succeeds-when-hermes-unreachable-fail-open` | Comment create still `201`       |


```bash
TEST_SERVICE=gaia-test-hermes-down ./tools/api-tester/harness/use-test-db.sh
./tools/api-tester/harness/run-api-tests.sh \
  --base-uri http://localhost:8082 \
  --mode live \
  --filter fail-open
```

