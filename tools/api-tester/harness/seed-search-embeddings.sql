-- Stored embeddings for the search fixture (agaia-4896): run by harness/seed-search.sh right after seed-search.sql,
-- with the data file (fixtures/search-embeddings.json) in the setting search.embeddings. Each fixture issue gets its
-- semantic text, the embedding of that text and the text's hash as both the desired and the actual hash, so the
-- meaning-search cases need not wait for Ares and a reindex finds nothing to re-embed. Only queries are embedded live.
-- A record whose subject or description differs from the data file stops the seed: regenerate the file with
-- harness/generate-search-embeddings.sh.
BEGIN;

DO $$
DECLARE
  doc jsonb := current_setting('search.embeddings')::jsonb;
  expected int := jsonb_array_length(doc -> 'records');
  loaded int;
BEGIN
  UPDATE issues i SET
    "searchSemanticText" = r ->> 'semanticText',
    "searchDesiredHash" = r ->> 'hash',
    "searchActualHash" = r ->> 'hash',
    "searchEmbedding" = (r ->> 'embedding')::vector,
    "searchEmbeddedAt" = NOW()
  FROM jsonb_array_elements(doc -> 'records') r
  WHERE i.id = r ->> 'id' AND i.subject IS NOT DISTINCT FROM r ->> 'subject'
    AND i.description IS NOT DISTINCT FROM r ->> 'description';
  GET DIAGNOSTICS loaded = ROW_COUNT;

  IF loaded <> expected OR EXISTS (
    SELECT 1 FROM issues i WHERE i.id LIKE 'api-test-search-%' AND i.deleted = 0
      AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(doc -> 'records') r WHERE r ->> 'id' = i.id)
  ) THEN
    RAISE EXCEPTION 'search fixture: fixtures/search-embeddings.json is out of step with seed-search.sql '
      '(% of % records matched); regenerate it with harness/generate-search-embeddings.sh', loaded, expected;
  END IF;
END $$;

COMMIT;
