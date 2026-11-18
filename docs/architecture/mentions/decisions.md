# Mentions Decisions

Agreed decisions for recording user mentions in Gaia. Add a new decision whenever behavior is agreed or changed.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend, `Mention` resource and the models that turn mentions on |
| Primary code | `core/mvc/models/behaviors/mentionsBehavior.php`, `core/libs/mentions/MentionParser.php`, `core/libs/mentions/MentionRecorder.php`, `app/metadata/model/Mention.php` |

---

## Architecture decisions

### D-001 — Mentions are recorded by a model behavior, not a controller component

| | |
|---|---|
| Status | Accepted |
| Decision | A model turns mentions on by listing `mentionsBehavior` in its metadata `behaviors` and naming its text fields in `'mentions' => ['fields' => [...]]`. The behavior runs after a create, and after an update that changed one of those fields, and hands the record to `MentionRecorder`. |
| Rationale | Mentions must be caught on every write path: REST create and update, collection PATCH, and saves outside controllers such as the workflow Set field action. Only the model sees all of them; a controller component would miss the saves that bypass `RestController`. |
| Consequence | Recording mentions is a side effect of the save: `mentionsBehavior` logs any error and never fails the user's write. Parsing (`MentionParser`) and recording (`MentionRecorder`) live in the domain layer, so the behavior only decides when to record. |

### D-002 — Mention rows are append-only, one per record and user

| | |
|---|---|
| Status | Accepted |
| Decision | `MentionRecorder` creates a `Mention` row for a mentioned user only when the record has no row for that user yet, deleted or not. Removing a mention leaves its row; adding it back does not create another. The check is a read before the write; a plain index on `relatedTo` and `relatedId` serves it. |
| Rationale | Notifications are driven from these rows, so a user mentioned, unmentioned and mentioned again in the same record is told once. |
| Consequence | The rows are a history of who was ever mentioned in a record, not of who is mentioned now. `relatedTo` is the model's name in lowercase. There is no unique constraint: the table holds legacy rows from 2015–2017 with duplicates, so two concurrent saves of the same record can, rarely, write a second row for a user. Adding the constraint with a cleanup of the legacy duplicates is a follow-up. |

### D-003 — Only active members of the record's project are recorded, never the actor

| | |
|---|---|
| Status | Accepted |
| Decision | A mention is recorded only for a user who exists, is not deleted, is not the user who saved the record, and is a member of the record's project. The project is resolved with `ProjectIdResolver` and stored on the row as `projectId`; a record without a project records nobody. |
| Rationale | A mention must not reach someone who cannot see the record, and writing your own name is not a mention worth telling you about. Storing `projectId` spares later consumers (notifications, the read ACL) from walking back to the record. |
| Consequence | Membership is checked when the mention is written; a user who joins the project later is recorded on the next save that changes the text. `ProjectIdResolver` sits with the live-events support classes today; moving it into the domain layer is a follow-up because the gaia-dev scaffold edits it in place. |
