-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- Permissions for the Ideas modules (idea, ideamessage, ideaplan, ideaplanissue) on existing roles.
-- Each role gets the same grant on the new modules as it holds on issue: <module>.<action> copies
-- issue.<action>, and every field of the new modules gets issue.get / issue.create / issue.update
-- for its get / create / update triple. Roles created later are seeded by RolePermissionSeeder.
-- Idempotent: rows a role already has are left alone, so it is safe to run on every deploy.
--   psql -d <database> -f db/seeds/idea_permissions.sql
-- IDs are deterministic ('idp-' + md5 of role and resource) so every environment has the same rows.

WITH modules(module) AS (
    VALUES ('idea'), ('ideamessage'), ('ideaplan'), ('ideaplanissue')
),
fields(module, field) AS (
    VALUES
        ('idea', 'title'), ('idea', 'description'), ('idea', 'status'),
        ('idea', 'dateCreated'), ('idea', 'dateModified'),
        ('ideamessage', 'body'), ('ideamessage', 'kind'), ('ideamessage', 'steps'),
        ('ideamessage', 'costUsd'), ('ideamessage', 'dateCreated'),
        ('ideaplan', 'version'), ('ideaplan', 'body'), ('ideaplan', 'status'), ('ideaplan', 'error'),
        ('ideaplan', 'dateCreated'), ('ideaplan', 'dateModified'),
        ('ideaplanissue', 'key'), ('ideaplanissue', 'dateCreated')
),
issue_grants AS (
    SELECT p."roleId", split_part(p."resourceName", '.', 2) AS action, p.allowed
    FROM permissions p
    JOIN roles r ON r.id = p."roleId" AND r.deleted = 0
    WHERE p."resourceName" IN ('issue.get', 'issue.create', 'issue.update', 'issue.delete')
),
wanted AS (
    SELECT g."roleId", m.module || '.' || g.action AS "resourceName", g.allowed
    FROM issue_grants g
    CROSS JOIN modules m
    UNION ALL
    SELECT g."roleId", f.module || '.' || f.field || '.' || g.action, g.allowed
    FROM issue_grants g
    CROSS JOIN fields f
    WHERE g.action IN ('get', 'create', 'update')
)
INSERT INTO permissions (id, "roleId", "resourceName", allowed, "dateCreated", "dateModified")
SELECT 'idp-' || md5(w."roleId" || ':' || w."resourceName"), w."roleId", w."resourceName", w.allowed, NOW(), NOW()
FROM wanted w
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE p."roleId" = w."roleId" AND p."resourceName" = w."resourceName"
);
