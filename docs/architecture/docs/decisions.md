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
