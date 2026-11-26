# Tags Decisions

Agreed decisions for tags in Gaia. Add a new decision whenever behavior is agreed or changed.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend, `Tag` and `Tagged` resources |
| Primary code | `app/metadata/model/Tag.php`, `app/metadata/model/Tagged.php`, `core/mvc/models/behaviors/tagBehavior.php`, `core/mvc/models/behaviors/taggedBehavior.php`, `core/libs/tags/Tags.php`, `core/libs/tags/TagName.php` |

---

## Architecture decisions

### D-001 — A tag belongs to one project; legacy global tags become unreadable

| | |
|---|---|
| Status | Accepted |
| Decision | A tag has a `projectId` and is scoped by the `Project` group, so only members of the project can read or use it. A name is unique per project, compared case-insensitively, and is stored trimmed with inner whitespace collapsed, 1 to 50 characters, in the user's case. A tagging (`Tagged`) is scoped through the tagged record (issue, conversation room, wiki page or doc) by `when` paths, and its tag must belong to the record's project. A record has a tag at most once, and a tagging can't be changed, only deleted. Metadata indexes can't express a case-insensitive unique pair, so `tagBehavior` and `taggedBehavior` enforce these rules and answer 422. |
| Rationale | Tags were global: one name system-wide, no ACL, so anyone with `tagged.create` could tag any record and every tag name was readable by everyone. Prometheus suggests tags from those already used in a project, so a tag has to belong to one. |
| Consequence | Tags created before this change have no project. They fail the project ACL and are unreadable; only the legacy wiki used them, and it is being retired (Docs D-001). Records other than the four taggable types (for example projects) can no longer be tagged. The uniqueness check runs before the insert, so two concurrent creates of the same name can both pass. The migration drops the old global unique index on `tags.tag` (`core/mvc/db/migration/Postgres.php` drops a single-column unique index when the metadata indexes that column without uniqueness). |
