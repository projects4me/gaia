# Docs Decisions

Agreed decisions for the Docs module in Gaia. Add a new decision whenever behavior is agreed or changed.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend, `Doc` resource |
| Primary code | `app/metadata/model/Doc.php`, `app/api/v1/controllers/DocController.php`, `core/mvc/models/behaviors/docBehavior.php`, `core/mvc/models/behaviors/doccontentBehavior.php` |

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

### D-005 — A locked doc's content is read-only; only its owner or a project admin changes the lock

| | |
|---|---|
| Status | Accepted |
| Decision | While `Doc.locked` is true, its `Doccontent` row cannot be created or changed (`doccontentBehavior` fails validation, 422) or deleted (its `beforeDelete` cancels the delete before the soft delete runs, 409). Only the doc's stored `ownerId`, or a user whose ACL allows `project.update` (the project admin capability, as in RBAC D-052 privilege is a grant, not a role name), may change `locked`; `docBehavior` fails validation (422) for anyone else. The same rule guards a change of `ownerId`, and the owner is read from the stored row, so an editor cannot make itself the owner and then change the lock. Every other doc write still needs `doc.update`, and content writes `doccontent.update`, like any resource. |
| Rationale | Gaia owns authorization (D-003), so the collab server and the UI cannot be trusted to honor the lock. A validation rule in the model's behaviour holds for every write path, not only REST. |
| Consequence | Outside a request (no ACL loaded, e.g. a workflow) only the owner may change the lock. Under the permissive resolution mode (RBAC D-011) a role with no row for `doc.update` or `doccontent.update` is allowed, so a role meant to only read or create docs needs explicit deny rows. |
