-- Manual script: put existing data into a "Default" workspace.
-- Run by hand only (see README.md). Not part of db/seeds, never loaded automatically.
-- Idempotent: a second run changes nothing.

BEGIN;

-- 1. The Default workspace (fixed id), created if missing.
INSERT INTO workspaces (id, name, description, "createdUser", "createdUserName", "modifiedUser", "modifiedUserName",
                        "dateCreated", "dateModified", deleted)
SELECT '00000000-0000-4000-8000-000000000001', 'Default', 'Existing data before workspaces were introduced',
       u.id, 'System', u.id, 'System', NOW(), NOW(), false
FROM (SELECT id FROM users ORDER BY "dateCreated" NULLS LAST, id LIMIT 1) u
WHERE NOT EXISTS (SELECT 1 FROM workspaces WHERE id = '00000000-0000-4000-8000-000000000001');

-- 2. Rows without a workspace go into it (deleted is smallint on projects, boolean elsewhere).
UPDATE projects    SET "workspaceId" = '00000000-0000-4000-8000-000000000001' WHERE ("workspaceId" IS NULL OR "workspaceId" = '') AND deleted = 0;
UPDATE agents      SET "workspaceId" = '00000000-0000-4000-8000-000000000001' WHERE ("workspaceId" IS NULL OR "workspaceId" = '') AND deleted = false;
UPDATE tool_servers SET "workspaceId" = '00000000-0000-4000-8000-000000000001' WHERE ("workspaceId" IS NULL OR "workspaceId" = '') AND deleted = false;
UPDATE skills      SET "workspaceId" = '00000000-0000-4000-8000-000000000001' WHERE ("workspaceId" IS NULL OR "workspaceId" = '') AND deleted = false;
UPDATE workflows   SET "workspaceId" = '00000000-0000-4000-8000-000000000001' WHERE ("workspaceId" IS NULL OR "workspaceId" = '') AND deleted = false;

-- 3. Every project of the Default workspace gets the union of the members (missing rows only).
INSERT INTO memberships (id, "projectId", "userId", "createdUser", "createdUserName", "modifiedUser",
                         "modifiedUserName", "dateCreated", "dateModified")
SELECT gen_random_uuid()::text, p.id, m."userId", m."userId", 'System', m."userId", 'System', NOW(), NOW()
FROM projects p
CROSS JOIN (SELECT DISTINCT mm."userId"
            FROM memberships mm
            JOIN projects pp ON pp.id = mm."projectId"
            WHERE pp."workspaceId" = '00000000-0000-4000-8000-000000000001' AND pp.deleted = 0) m
WHERE p."workspaceId" = '00000000-0000-4000-8000-000000000001' AND p.deleted = 0
  AND NOT EXISTS (SELECT 1 FROM memberships x WHERE x."projectId" = p.id AND x."userId" = m."userId");

-- 4. Short codes (Workspaces D-012): Default gets 'default'; every other live workspace without a code
--    takes its oldest live project's code, or its name (letters and digits, starting with a letter), or 'ws',
--    with the first free number appended when another live workspace has it.
DO $$
DECLARE
    ws RECORD;
    base TEXT;
    candidate TEXT;
    n INT;
BEGIN
    FOR ws IN
        SELECT w.id, w.name,
               (SELECT p."shortCode" FROM projects p
                WHERE p."workspaceId" = w.id AND p.deleted = 0
                ORDER BY p."dateCreated" NULLS LAST, p.id LIMIT 1) AS "projectCode"
        FROM workspaces w
        WHERE (w."shortCode" IS NULL OR w."shortCode" = '') AND w.deleted = false
        ORDER BY (w.id <> '00000000-0000-4000-8000-000000000001'), w."dateCreated" NULLS LAST, w.id
    LOOP
        IF ws.id = '00000000-0000-4000-8000-000000000001' THEN
            base := 'default';
        ELSE
            base := regexp_replace(lower(COALESCE(NULLIF(ws."projectCode", ''), ws.name, '')), '[^a-z0-9]', '', 'g');
            base := regexp_replace(base, '^[0-9]+', '');
        END IF;
        IF base = '' THEN
            base := 'ws';
        END IF;
        base := left(base, 50);
        candidate := base;
        n := 2;
        WHILE EXISTS (SELECT 1 FROM workspaces x WHERE x."shortCode" = candidate AND x.deleted = false) LOOP
            candidate := left(base, 50 - length(n::text)) || n::text;
            n := n + 1;
        END LOOP;
        UPDATE workspaces SET "shortCode" = candidate WHERE id = ws.id;
    END LOOP;
END $$;

COMMIT;
