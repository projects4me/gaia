# Model decisions (Gaia)

Agreed decisions for how Gaia's models and Phalcon's ORM get the description of their tables: columns, keys,
types and defaults.

| Field | Value |
|-------|-------|
| Status | Accepted |
| Scope | The ORM metadata of every model in `app/metadata/model/` |
| Primary code | `core/mvc/models/Model.php`, `core/libs/meta/manager.php` |

---

## How to use this file

- Add a new decision whenever how models describe their tables is agreed or changed.
- Mark open questions separately so they are not treated as settled policy.

---

## Architecture decisions

### D-001 — The ORM reads its metadata from Gaia's model metadata, not from the schema

| | |
|---|---|
| Status | Accepted |
| Decision | `Model::metaData()` returns `Manager::getOrmMetaData()`: the `MetaData::MODELS_*` entries that `Manager::getModelMeta()` builds from `app/metadata/model/<Model>.php`, with each column's type as the database adapter's migration handler stores it (`Manager::getColumnType()`, so a `datetime` field is a `TIMESTAMP` on PostgreSQL). Phalcon 5's models metadata service calls `metaData()` when a model has it, so the Introspection strategy, which queries `information_schema` and `pg_*` for every model on every request, is never used. `Manager::parseFields()` fills what introspection used to: the identity column is the `autoIncrement` field (a GUID primary key is not one), an `autoIncrement` column is not null (PostgreSQL makes a SERIAL NOT NULL), integer, float and decimal columns are numeric, and every column with a `default` or that allows null has a default value, so a not-null column with a database default (`deleted`, `status`) is not required on create. No column accepts an empty string in place of a value, as with introspection. |
| Why | The schema introspection cost about 24 ms of a 62 ms list on OVH (agaia-4544), on every request. The model metadata is already the source of truth: the migrations build the schema from it. The `default` values had been left out (`//$fields['default']`) since the metadata was first written, when the ORM still introspected the table and never read them. |
| Consequences | A column the migrations have not created yet (a branch ahead of its database) is in the ORM's metadata, so a query on it fails instead of the column being ignored; migrate first. `tests/foundation/mvc/models/ModelMetadataParityTest.php` compares `metaData()` of every model with what the Introspection strategy reads from the test database (attributes, keys, not null, types, bind types, identity, defaults) and fails on any difference that its allowlist does not name with a reason, so drift between the metadata and the schema is caught in CI rather than at run time. The allowlist holds the view-backed models (read-only; a view has no key and reports every column nullable), bigint columns declared `int`, double columns declared `float` and the search columns. `Model::initialize()` still tells Phalcon to skip the columns the ORM never writes. |

### D-002 — A field's metadata describes the column as it is stored

| | |
|---|---|
| Status | Accepted |
| Decision | The metadata of a field matches the column the database holds, including on the legacy tables loaded from the original dump. A `'type' => 'bool'` field kept in a `smallint` column says so with `'storage' => 'smallint'` (`Manager::getStoredType()`): the API still treats it as a boolean, while the ORM types it as a smallint, binds it as an integer and casts it as a number, and the migrations create a `smallint` column for it, so a database built from the metadata and one loaded from the dump agree. A column the database allows null is `'null' => true` in the metadata (e.g. `dateCreated` of `Dashboard`, `User.type`). Text columns are bound as strings (`Manager::getBindType()`), as introspection binds them. |
| Why | Once the ORM reads the metadata (D-001), a mismatch changes run-time behaviour: a `bool` field bound as a boolean into a `smallint` column fails every insert (`invalid input syntax for type smallint`), and a nullable column declared not null makes the ORM reject a null it accepts today. Turning those columns into `boolean` and `NOT NULL` instead would be a data migration on production and would need every `deleted = 0` comparison rewritten, since PostgreSQL rejects `boolean = integer`. |
| Consequences | A new field declares the column it really gets; `'storage'` is only for the legacy `smallint` booleans. The parity test fails when a field's metadata and its column disagree. |
