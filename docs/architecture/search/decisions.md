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
| Status | Superseded by D-010 |
| Decision | The searchers' rankings were combined with reciprocal rank fusion (k = 60), so scores of different searchers need not be comparable. Records the exact searcher found (such as an issue number) stay on top, in fused order. Each searcher gives twice the limit in candidates so fusion can reorder. |
| Why | Full-text ranks and trigram similarities are on different scales; ranks are not. A user who types an issue number expects that issue first. |
| Consequences | `score` orders results within one response only; it is not a probability and not comparable between queries. |

### D-008 — Semantic matching embeds the query and degrades when Ares is down

| | |
|---|---|
| Status | Accepted |
| Decision | `SemanticSearcher` embeds the query once per search through `Embeddings::embedQuery` (Ares) and ranks the records whose `searchEmbedding` is within `system.search.semanticMaxDistance` cosine distance (default 0.5), nearest first, inside the record scope and `deleted = 0` before `LIMIT` like the other searchers. The query runs with `hnsw.iterative_scan = strict_order` (pgvector 0.8), so when the plan reads the HNSW index, nearer records out of scope or past the cut-off do not crowd the eligible ones out of its `hnsw.ef_search` candidates (it gives up at `hnsw.max_scan_tuples`). It runs for every type with semantic fields the user may read. When Ares cannot embed the query the searcher throws `SearcherUnavailableException`; `SearchService` logs it, searches on without semantic matching and reports it in `SearchResults`, which the response shows as `meta.semantic: "unavailable"`. |
| Why | A paraphrase shares no words with the record, so only meaning finds it. Ares being down must not fail a search the other searchers can answer, and a failed Ares call is reported, never passed off as a complete answer. |
| Consequences | Ares adds one call per search; a search without Ares has no semantic matches and says so. The distance is a cut-off; the ranking (D-010) turns it into a 0-1 meaning score. |

### D-009 — The backfill runs on start, and Ares pages through the pending records

| | |
|---|---|
| Status | Accepted |
| Decision | `php cli.php search backfill [type]` is the text step of the reindex alone: it stores the semantic text and `searchDesiredHash` of the records that have none or an outdated one, publishes nothing and prints counts per type. `docker/dev/start.sh` runs it after `migration migrate`; a failure is logged and the container still starts. `search reindex` stays a manual command, with no scheduler. `GET /api/v1/searchpending?after=<type>:<id>&limit=<n>` lists, as `{data: [{type, resource, id}], next}`, the non-deleted records of every type with semantic fields whose `searchDesiredHash` is set and whose `searchActualHash` is null or differs from it, ordered by type then id. `next` is the keyset cursor of the last record on a full page and null otherwise; `limit` defaults to 200 and is capped at 500; a malformed cursor, a cursor of a type without semantic fields or a limit below 1 is 400. Like `search` (D-001) it is a list-only resource with no model, a second exception to standard CRUD resources: Phalcon routes have no nested paths, so it is a flat resource. Only the Ares service calls it: `SearchpendingController` sets `$authorization = false` and `PendingEmbeddingsCallerGuard` requires a live user-less token with the scope `searchpending.get` from an active service client of kind `ares`. No token, a dead one or an inactive client is 401; a user, another kind or a token without the scope is 403. The controller stamps the client's last use, as `authorize()` does for other service requests. The cursor splits at its first colon, so a record id may hold colons (up to 255 bytes, no control characters). |
| Why | Rows that existed before the search columns, or a change to the text rule, leave texts missing or outdated after a deploy, and the backfill fixes them before Ares asks. Hermes keeps no history, so events missed while Ares was down are recovered by Ares asking for what is pending, not by Gaia re-announcing on a timer. A keyset cursor stays correct while records leave the set as they are embedded; an offset would skip records. Ids only keep the list cheap: Ares reads the text through the issue resource (D-072 in the RBAC decisions). |
| Consequences | `config/serviceclients.php` gives kind `ares` the scope `searchpending.get`; an Ares client enrolled before it needs the scope added. A record with no `searchDesiredHash` yet is not pending until the backfill or a save gives it one. The bulk API refuses `searchpending`, as it does every resource that skips user authorization. |

### D-010 — Results score their strongest signal from 0 to 1, with a threshold

| | |
|---|---|
| Status | Accepted; superseded in part by D-012 (query operators) and D-013 (number queries, equal scores) |
| Decision | `SearchService` parses the query once into a `SearchQuery` (words, words that are not Postgres english stop words, numbers; it also read quoted phrases and `-word` terms until D-012 removed query operators) and hands it to every searcher. `StrongestSignal` replaces reciprocal rank fusion (D-007): a record scores the most of its signals on a 0-1 scale: FTS's normalized `ts_rank_cd` as it is; fuzzy `fuzzyWeight` x (similarity - `fuzzyThreshold`) / (1 - `fuzzyThreshold`); semantic `clamp((semanticFar - distance) / (semanticFar - semanticNear), 0, 1)`. Records under `threshold` are dropped. Exact matches score 1 and come first: the number (`ExactMatcher`) or the display title with case and spacing ignored (`ExactTitleMatcher`, kind `exact` too, a searcher of its own so a user who may not read the title still finds a record by number). Scores never rise down the list; equal scores keep key order; the top `limit` are returned. A query without a letter or a digit finds nothing; fuzzy matching says nothing for a query of stop words only or of fewer than 3 letters and digits outside them. Settings in `system.search`, validated when the service is built: `threshold` 0.15, `fuzzyWeight` 0.6, `semanticNear` 0.156, `semanticFar` 0.402. The response's `score` is this 0-1 score; `matchedBy` lists every kind that found the record and `pinned` stays true for exact matches. The response does not carry the per-signal values, and the semantic calibration is a plain setting, not keyed by the embedding model's name. |
| Why | Reciprocal rank scores are positional (about 0.016-0.033), kept every candidate any searcher found and could not drop noise, so a gibberish or unrelated query still returned a full page. A score on one scale means how well a record matched and allows a cut-off. Per-signal values would freeze the searchers' internals into the API for a debugging need; `matchedBy` already says which kinds matched. Only one embedding model is in use, so a per-model calibration table would have one row. |
| Consequences | `score` is comparable between queries and results under the threshold are gone, so a query can return fewer than `limit` results or none. `semanticNear` and `semanticFar` are calibrated for the current embedding model and must be recalibrated when it changes. The exact title comparison reads the title of every record in scope (no index serves it). |

### D-011 — Fuzzy-only results are kept only for likely typos

| | |
|---|---|
| Status | Accepted |
| Decision | Fuzzy title similarity adds letter-overlap noise to queries of real words (a spot-check of 169 low-scoring results: fuzzy-only 8% relevant, meaning 54%). `UnknownWords` finds the query's unknown words: words of 3+ letters that are not stop words or numbers and occur in no record the user may read, checked against each searched type's `searchVector` at search time (`searchVector @@ plainto_tsquery(config, word)`, one indexed `EXISTS` per word and type, through `CandidateQuery` inside the scope). `StrongestSignal` drops a record only fuzzy matched when the query has no unknown word and some record matches by keyword, or by meaning at `threshold` or above. A query with an unknown word, or one nothing else matches, keeps its fuzzy-only results. Exact and number matches are never dropped. With no type whose `searchVector` the user may read, the query counts as having an unknown word. |
| Why | Raising the threshold would throw away the useful meaning results; gating fuzzy keeps them and still finds targets of typos. |
| Consequences | No vocabulary tables. A word only in a project the user cannot read is unknown to them. A fuzzy-only result for a real-word query can no longer appear next to keyword or meaning matches. |

### D-012 — Records holding every query word rank first, and search has no query operators

| | |
|---|---|
| Status | Accepted |
| Decision | For a query of two or more different words that are not stop words, `StrongestSignal` puts the records the keyword search matched first (its query needs every word, so a keyword match is an all-words match): their score maps linearly to [`allWordsBoundary` + (1 - `allWordsBoundary`) x `threshold`, 1] and the others' to [`threshold`, `allWordsBoundary`]. `allWordsBoundary` is a `system.search` setting (0.5), past `threshold` and under 1. The threshold decides on the original score, so this reorders and never adds or drops a record; exact records keep 1. Search has no query operators: `TextSearchQuery` builds the keyword query with `plainto_tsquery`, so quotes and a leading dash are plain text and `ledger -export` or `"ledger export"` search like `ledger export`. |
| Why | A record matching every word the user typed is a better answer than one matching a single word strongly. Operators (`"phrase"`, `-word`) are something users must learn and few use; `websearch_to_tsquery` honoured them in the keyword search only, while fuzzy and meaning matching ignored them, so a query with an operator got a mix of both readings. |
| Consequences | For a query of several words `score` is the banded score, not the raw strongest signal: a weak all-words match scores above a strong partial match, so scores compare within one query, and between queries only of the same kind (one word or several). A user cannot exclude a word or ask for an exact phrase; quoting a pasted error message searches its words. |

### D-013 — Numbers and references are looked up, not searched as text

| | |
|---|---|
| Status | Accepted |
| Decision | `SearchRoute` picks the searchers by the query's shape (`SearchQuery::number()`, `reference()`), as stages that run only when the ones before found nothing. A number alone (`4170`, `#4170`, any length) is a lookup: `ExactMatcher` finds the record with that number; with none and two digits or more, `PartialNumberMatcher` (kind `number`) finds the integer exact fields starting or ending with it (`CAST(field AS TEXT) LIKE`), and `StrongestSignal` scores a number starting with it from `partialNumberBoundary` (0.5) to 1 and one ending with it from `threshold` to `partialNumberBoundary`, each by the share of digits matched; no text searcher runs, so a number nobody has finds nothing. A reference (`prefix-number`, e.g. `agaia-4170`) whose prefix a record the user may see has is a lookup by `ReferenceMatcher` (kind `exact`): the block's `referencePrefix` field equals the prefix with case ignored and an integer exact field equals the number. A prefix nobody in scope has is plain text, without its number. In any other query `ExactMatcher` looks up every number when the query has up to `numberQueryWords` (5) words, and only the numbers written `#N` in a longer one (pasted text); its records are exact, so they come first at 1 above the text matches. `referencePrefix` is an optional search block key naming a text field (Issue: `projectShortcode`), valid only with an integer exact field. Equal scores now keep the order the searchers found them in (each best first) instead of key order, so the partial number order, newest (highest number) first, stays. Every lookup goes through `CandidateQuery`, inside the scope, before ranking and the limit. |
| Why | A number or a short code is how people name an issue; searching its digits as text only found noise (a fuzzy match on 99 for 99999). A generic prefix key keeps the matching out of Issue-specific code. Whether a prefix is known is checked in the user's scope so the answer says nothing about projects they are not in, and so a hyphenated word like `covid-19` stays text. The number cast is not indexed: an index would not serve the "ends with" half, the scope already limits the scan to the user's records, and the stage only runs for a number nobody in scope has. |
| Consequences | A number query never returns text matches, even a record whose text holds that number. `matchedBy` may hold `number` (not pinned). Pasted text over five words no longer pins a record for a bare number in it. A record a user cannot see is never found by its number, part of it or its reference. The prefix is read from the record's own field (Issue keeps a copy of its project's short code, set on create), so a copy that went stale finds nothing under the new code. |

### D-014 — Keyword queries also match other forms of their words that occur in readable records

| | |
|---|---|
| Status | Accepted |
| Decision | `QueryExpander` proposes other forms of each query word: the developer abbreviation or its full words (about 50 pairs, both ways), the British or American ending (`-our/-or`, `-ise/-ize`, `-isation/-ization`, `-yse/-yze`, `-elled/-eled`, `-tre/-ter`, `-ogue/-og`, `-ence/-ense`, also before a plural `s`, on words of `spellingMinLength` letters or more, a `system.search` setting, 5), two adjacent words joined and a joined word split into two (halves of `splitMinPart` letters or more, 3, the most even split first). Only words of letters that stand alone in the text are expanded, so config in a token such as config.php or 1.2.3 is left whole, and never stop words, so a pair is never split or joined around one (`logout` never becomes `log out`). Word forms come first, then joins, then splits, and at most `maxAlternatives` (16) different forms are proposed; a repeated word gets the same forms, counted once. `QueryExpansion` keeps a form only when it occurs in a record of the type the user may read, checked against the type's `searchVector` through `CandidateQuery` inside the scope: a word by one indexed `EXISTS` (`plainto_tsquery`), a split pair as a phrase (`phraseto_tsquery`) that needs `splitMinRecords` records (a setting, 2). The kept forms make a `KeywordQuery`: the query text as typed without the words that have forms, and a group per word or joined pair, which `TextSearchQuery` turns into the tsquery: each word ORed with its forms, a joined pair as both words or the joined word. It is built once per type and query and each form is checked once; `SearchService` hands the same keyword query to the snippet, so the snippet marks the forms too. |
| Why | Records write the same thing several ways (`taskboard` and `task board`, `colour` and `color`, `config` and `configuration`) and the keyword search, which needs every word, missed the other ways. Checking each form against readable records at search time keeps forms that would only add noise out of the query, needs no vocabulary tables (agaia-4891 was dropped), and cannot reveal a word from a record the user may not read. |
| Consequences | A query does up to `maxAlternatives` extra indexed lookups per type searched (about 0.3-1.6 ms each on 3,659 issues). A query with no kept form runs the same tsquery as before. A failed lookup fails the search, as a failed keyword search does. The ending rules also propose non-words (`editour`); the lookup drops them, but they take places among the `maxAlternatives`. A word with a form keeps its own lookup in `UnknownWords`: a word no readable record has still counts as unknown even when its other spelling occurs. |

### D-015 — Paths and file names are indexed and queried as their words too

| | |
|---|---|
| Status | Accepted |
| Decision | `PostgresSearchDdl` builds each source of a generated `searchVector` as its own tsvector followed by the tsvector of the same text with the `WORD_SEPARATORS` (`/ . _ \`) read as spaces, less the lexemes the first one has already (removed with `ts_delete` and `tsvector_to_array`; every function is immutable, as a generated column needs). The original tokens stay (oauth/callback, image-upload.js), and the parts are words too (`oauth`, `callback`, `imag`, `upload`, `js`). `TextSearchQuery` reads the same separators as spaces in every word it binds to `plainto_tsquery`, so a pasted path (src/OAuth/callback) needs each of its parts, which the split parts of the record match. The column's signature changes, so the migration drops and re-adds it and `syncIndexes` recreates its GIN index. |
| Why | Postgres's parser keeps a path, a file name or a dotted name as one token, so `oauth`, `image` or `semantic` missed the records naming OAuth/callback, image-upload.js or meta.semantic, and a pasted path missed a record naming part of it. Adding only the parts the text has not got already keeps a plain word's positions as they were, so `ts_rank_cd` of records without paths does not change (indexing both texts whole counted every plain word twice: 0.17 became 0.29 for a word written once). |
| Consequences | A word found only inside a path counts once, at a position after the source's own words, so it ranks like a word written once. A phrase across a path part and a plain word of the same lexeme may not match (the part is not added again). Decimal numbers and versions are split too (v1.5 also gives v1 and 5). The snippet's `ts_headline` parses the text whole, so a match inside a path is found but not marked. Migrating rebuilds every table's `searchVector` and its index, a full rewrite of those tables. |
