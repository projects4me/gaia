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

COMMIT;
