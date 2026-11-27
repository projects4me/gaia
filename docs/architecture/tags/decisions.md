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

### D-002 — A filter on a many-to-many relationship is a subquery, and lists read those relationships per page

| | |
|---|---|
| Status | Accepted |
| Decision | A `query` leaf on a `hasManyToMany` relationship (e.g. `(tag.tag : frontend)` on Issue or Conversationroom) becomes a test on the record's id, `Issue.id IN (SELECT tagTagged.relatedId FROM Tagged tagTagged WHERE <condition> AND tagTagged.tagId IN (SELECT tag.id FROM Tag tag WHERE <leaf>))`, one subquery per leaf (`ManyToManyFilter`, called by `Clause::prepareWhere`). A list (`Model::readAll`) does not join a requested `hasManyToMany` relationship: it reads the page's records first and then that relationship for their ids (`Model::executeHasManyRel` with the base ids), so the include holds every related row of each record. Both apply to a relationship without exclusive join conditions whose `condition` reads the junction alias alone. A list that is grouped, counted, has `having`, sorts by the relationship or streams its rows keeps the join. A relationship filter still needs the relationship in `rels` (agaia-4985); an empty `rels` joins none, so a relationship filter without `rels` is a 400. Tag names match exactly, case included: the UI sends the stored spelling, picked from the project's tag list. |
| Rationale | The join multiplied a record by its matching related rows: `limit` and `page` counted rows, so a record with two matching tags cut a page short and came back on the next page, and `(tag.tag : a) AND (tag.tag : b)` tested one tag row and matched nothing (agaia-4987). |
| Consequence | `(tag.tag : a) AND (tag.tag : b)` means "has both tags"; `OR` and `CONTAINS` mean "has any of them". A `NULL`/`!NULL` leaf on such a relationship tests the related field of existing rows, not "has no related rows". A list's tag include is no longer narrowed to the tags the filter matched. Relationships whose condition reads the base model (Chatroom `ownedby`, `conversers`) or with exclusive conditions (User `badgeLevels`) keep the join. `hasMany` includes still join, so they can still cut a page short. `meta.count` counts the look-ahead record of a page that has a next one. |
