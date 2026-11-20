# Search decisions (Gaia)

Agreed decisions for searching across modules: the `search` resource
(`GET /api/v1/search`), the metadata that makes a module searchable, the
stored search columns, semantic embedding and ranking.

| Field | Value |
|-------|-------|
| Status | Accepted |
| Scope | Cross-module search over HTTP |
| Primary code | `core/libs/search/`, `app/api/v1/controllers/SearchController.php`, `app/api/v1/controllers/SearchpendingController.php` |

---

## How to use this file

- Add a new decision whenever search behaviour is agreed or changed.
- Mark open questions separately so they are not treated as settled policy.

---

## Architecture decisions

### D-001 — Search is a deliberate exception to standard CRUD resources

| | |
|---|---|
| Status | Accepted |
| Decision | `GET /api/v1/search?types=issue&q=...&limit=20` is a list-only resource with no model of its own. `SearchController::listAction()` only parses the query string into a `SearchRequest`, calls `SearchService` and shapes the answer. It declares `$aclMap` and `$aclAllowed = false`, so the action catalog publishes no `search.*` permission. |
| Why | A search spans several modules in one call and returns ranked results, which `GET /api/v1/<module>` cannot express. A permission of its own would let a role see results its modules deny. |
| Consequences | Authorization is the searched module's: `SearchService` requires `<module>.get` for every requested type (a denied type answers 403 for the whole request) and keeps each candidate query inside the module's record scope. The caller still needs a valid OAuth token. A missing or too short `q`, an unknown type or a bad `limit` answer 400. |

### D-002 — The response is a collection of `searchresult` items

| | |
|---|---|
| Status | Accepted |
| Decision | `data` holds `{type: "searchresult", id: "<type>:<recordId>", attributes: {module, recordId, title, snippet, score, matchedBy, pinned}}` and `meta` holds `{query, types, semantic}`, where `semantic` is `unavailable` when semantic matching could not run (D-008) and `ok` otherwise. `pinned` is true when the exact searcher found the record. `title` and `snippet` are null when the user may not read the field. |
| Why | The composite id keeps ids unique across modules; the client opens the record from `module` and `recordId`. |
| Consequences | The shape is the same for every searchable module, so a new module needs only its search metadata. |

### D-003 — A module opts in through search metadata

| | |
|---|---|
| Status | Accepted |
| Decision | A model is searchable when its metadata has a search block; `SearchableRegistry` finds it by type (the name used in `types=`) and refuses two models claiming one type. |
| Why | Modules differ in what is worth matching; the block, not the search code, names the fields. |
| Consequences | `SearchService` knows no module by name. |

### D-004 — Searchers run per type and are skipped for unreadable fields

| | |
|---|---|
| Status | Accepted |
| Decision | Each type is searched by an exact, a full-text, a fuzzy and a semantic searcher. A searcher is skipped for a type when the user may not read one of its fields. Every candidate query stays inside the module's record scope and `deleted = 0` before ranking and `LIMIT`. |
| Why | A match on a hidden field would leak its content through the ranking. |
| Consequences | A user with field restrictions may get fewer matches than an admin for the same query. |

### D-005 — Search columns are stored system columns, not module fields

| | |
|---|---|
| Status | Accepted |
| Decision | The text and vector columns the searchers read (and the semantic text and its hash) are stored on the module's table as system search columns, maintained by Gaia, never written through the module's REST API. |
| Why | Full-text and fuzzy search need an index over precomputed text; computing it per query would not scale. |
| Consequences | `SemanticReindexer` backfills them; a change to the text rule is a reindex, not a migration of data by hand. |

### D-006 — Ares embeds on events, and the hash is the precondition

| | |
|---|---|
| Status | Accepted |
| Decision | When a record's semantic text changes Gaia announces `search.semantic.changed`; Ares computes the embedding and stores it only if the hash it embedded still equals the stored hash. Each record keeps two hashes: `searchDesiredHash`, of its current semantic text, and `searchActualHash`, of the text its stored embedding was made from; a record needs embedding when `searchActualHash` is null or differs from `searchDesiredHash`. The reindex re-announces every record whose embedding is missing or was made from another text. |
| Why | Embedding is slow and belongs to Ares. Hermes keeps no history, so events missed while Ares was down are recovered through the pending list (D-009) or a manual reindex. A late embedding of old text must not overwrite a newer one. |
| Consequences | Semantic matching is eventually consistent: a record whose embedding is not stored yet is not a semantic candidate. Search uses the embeddings as D-008 describes. |

### D-007 — Rankings are fused by reciprocal rank, with exact matches first

| | |
|---|---|
| Status | Accepted |
| Decision | `RankFusion` combines the searchers' rankings with reciprocal rank fusion (`RankFusion::K` = 60), so scores of different searchers need not be comparable. Records the exact searcher found (such as an issue number) stay on top, in fused order. Each searcher gives twice the limit in candidates so fusion can reorder. |
| Why | Full-text ranks and trigram similarities are on different scales; ranks are not. A user who types an issue number expects that issue first. |
| Consequences | `score` orders results within one response only; it is not a probability and not comparable between queries. |

### D-008 — Semantic matching embeds the query and degrades when Ares is down

| | |
|---|---|
| Status | Accepted |
| Decision | `SemanticSearcher` embeds the query once per search through `Embeddings::embedQuery` (Ares) and ranks the records whose `searchEmbedding` is within `system.search.semanticMaxDistance` cosine distance (default 0.5), nearest first, inside the record scope and `deleted = 0` before `LIMIT` like the other searchers. The query runs with `hnsw.iterative_scan = strict_order` (pgvector 0.8), so when the plan reads the HNSW index, nearer records out of scope or past the cut-off do not crowd the eligible ones out of its `hnsw.ef_search` candidates (it gives up at `hnsw.max_scan_tuples`). It runs for every type with semantic fields the user may read. When Ares cannot embed the query the searcher throws `SearcherUnavailableException`; `SearchService` logs it, searches on without semantic matching and reports it in `SearchResults`, which the response shows as `meta.semantic: "unavailable"`. |
| Why | A paraphrase shares no words with the record, so only meaning finds it. Ares being down must not fail a search the other searchers can answer, and a failed Ares call is reported, never passed off as a complete answer. |
| Consequences | Ares adds one call per search; a search without Ares has no semantic matches and says so. The distance is a cut-off, not a ranking score: fusion (D-007) still orders by rank. |

### D-009 — The backfill runs on start, and Ares pages through the pending records

| | |
|---|---|
| Status | Accepted |
| Decision | `php cli.php search backfill [type]` is the text step of the reindex alone: it stores the semantic text and `searchDesiredHash` of the records that have none or an outdated one, publishes nothing and prints counts per type. `docker/dev/start.sh` runs it after `migration migrate`; a failure is logged and the container still starts. `search reindex` stays a manual command, with no scheduler. `GET /api/v1/searchpending?after=<type>:<id>&limit=<n>` lists, as `{data: [{type, resource, id}], next}`, the non-deleted records of every type with semantic fields whose `searchDesiredHash` is set and whose `searchActualHash` is null or differs from it, ordered by type then id. `next` is the keyset cursor of the last record on a full page and null otherwise; `limit` defaults to 200 and is capped at 500; a malformed cursor, a cursor of a type without semantic fields or a limit below 1 is 400. Like `search` (D-001) it is a list-only resource with no model, a second exception to standard CRUD resources: Phalcon routes have no nested paths, so it is a flat resource. Only the Ares service calls it: `SearchpendingController` sets `$authorization = false` and `PendingEmbeddingsCallerGuard` requires a live user-less token with the scope `searchpending.get` from an active service client of kind `ares`. No token, a dead one or an inactive client is 401; a user, another kind or a token without the scope is 403. The controller stamps the client's last use, as `authorize()` does for other service requests. The cursor splits at its first colon, so a record id may hold colons (up to 255 bytes, no control characters). |
| Why | Rows that existed before the search columns, or a change to the text rule, leave texts missing or outdated after a deploy, and the backfill fixes them before Ares asks. Hermes keeps no history, so events missed while Ares was down are recovered by Ares asking for what is pending, not by Gaia re-announcing on a timer. A keyset cursor stays correct while records leave the set as they are embedded; an offset would skip records. Ids only keep the list cheap: Ares reads the text through the issue resource (D-072 in the RBAC decisions). |
| Consequences | `config/serviceclients.php` gives kind `ares` the scope `searchpending.get`; an Ares client enrolled before it needs the scope added. A record with no `searchDesiredHash` yet is not pending until the backfill or a save gives it one. The bulk API refuses `searchpending`, as it does every resource that skips user authorization. |
