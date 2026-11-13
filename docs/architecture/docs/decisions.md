# Docs Decisions

Agreed decisions for the Docs module in Gaia. Add a new decision whenever behavior is agreed or changed.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend, `Doc` resource |
| Primary code | `app/metadata/model/Doc.php`, `app/api/v1/controllers/DocController.php` |

---

## Architecture decisions

### D-001 — Docs is independent of Wiki

| | |
|---|---|
| Status | Accepted |
| Decision | Docs is a new, independent module built from scratch. It shares no code or tables with Wiki, beyond the generic Tag/Tagged tagging. |
| Rationale | Wiki is kept unchanged while Docs is built, so neither module constrains the other. |
| Consequence | Once the Docs module is complete, the Wiki code is retired. Whether existing wiki content is migrated into Docs or dropped is decided at retirement, once the Docs body format is settled. |

### D-002 — The Yjs state in Doccontent is the master copy

| | |
|---|---|
| Status | Accepted |
| Decision | A doc's content lives in its own resource, `Doccontent`. The Yjs state is the master copy. `html` and `plainText` are derived copies for export, search and AI, written by the collab server and never edited directly. The state is base64 in a text column because the REST API is JSON. |
| Rationale | Autosaves are frequent and large. Keeping them off the `Doc` row means they do not rewrite the doc metadata, are not copied into the audit log (`Doccontent` has no `auditBehavior`) and never travel with list queries of docs. |
| Consequence | `Doc` has no body field. Anything that needs the text reads the derived copies, and a change to the state is followed by a rewrite of both. |

### D-003 — Content is written with the connected user's own token

| | |
|---|---|
| Status | Accepted |
| Decision | The collab server saves content with the token of the user who is connected, not with a service account. |
| Rationale | Gaia ACL (`doccontent.*` through the project group) and `modifiedUser` stay authoritative, so the content has the same access rules and audit trail as any other resource. |
| Consequence | A user without `doccontent.update` cannot change content through the collab server either. |

### D-004 — One merged snapshot row per doc, no update log

| | |
|---|---|
| Status | Accepted |
| Decision | A doc has one `Doccontent` row holding the merged Yjs state. Gaia enforces this: a second row for the same `docId` fails validation, and the unique index on `docId` backs it up against concurrent creates. A deleted row still counts, so a doc whose row was deleted cannot get a second one; restoring a deleted row is not part of this resource yet. |
| Rationale | An update log grows without bound and needs compaction. Version history is out of scope for now. |
| Consequence | Version history, if wanted, is a separate resource later. |
