#!/usr/bin/env bash
# Gaia-specific harness (not part of portable black-box core).
# Creates pr4m_test with same structure as pr4m, copies ACL/oauth baseline,
# seeds deterministic rows, and writes fixtures for api-tester.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
PG_CONTAINER="${PG_CONTAINER:-postgres-dev}"
SOURCE_DB="${SOURCE_DB:-pr4m}"
TEST_DB="${TEST_DB:-pr4m_test}"
PG_USER="${PG_USER:-nouman}"
PGPORT="${PGPORT:-5432}"
FIXTURES_OUT="${FIXTURES_OUT:-$ROOT_DIR/tools/api-tester/fixtures/default.json}"
SCHEMA_DUMP="/tmp/${SOURCE_DB}_schema_$$.sql"

# PGHOST set => talk to Postgres over TCP (CI). Else docker exec (local compose).
if [[ -n "${PGHOST:-}" ]]; then
  echo "==> Using Postgres TCP ${PGHOST}:${PGPORT} (user=${PG_USER})"
  psql_db() {
    local db="$1"; shift
    PGPASSWORD="${PGPASSWORD:-}" psql -h "$PGHOST" -p "$PGPORT" -U "$PG_USER" -d "$db" "$@"
  }
  pg_dump_db() {
    local db="$1"; shift
    PGPASSWORD="${PGPASSWORD:-}" pg_dump -h "$PGHOST" -p "$PGPORT" -U "$PG_USER" -d "$db" "$@"
  }
  scrub_dump() {
    sed -i \
      -e '/^\\restrict /d' \
      -e '/^\\unrestrict /d' \
      -e '/^CREATE SCHEMA /d' \
      -e '/^CREATE SCHEMA IF NOT EXISTS /d' \
      "$1"
  }
  rm_dump() { rm -f "$1"; }
  copy_table_data() {
    local table="$1"
    pg_dump_db "$SOURCE_DB" --data-only --no-owner --column-inserts -t "public.$table" \
      | sed -e '/^\\restrict /d' -e '/^\\unrestrict /d' \
      | psql_db "$TEST_DB" -v ON_ERROR_STOP=1 >/dev/null
  }
  copy_csv() {
    local src_sql="$1"
    local dst_sql="$2"
    psql_db "$SOURCE_DB" -Atc "$src_sql" | psql_db "$TEST_DB" -c "$dst_sql" >/dev/null
  }
else
  echo "==> Checking container: $PG_CONTAINER"
  docker ps --format '{{.Names}}' | grep -qx "$PG_CONTAINER" || {
    echo "Postgres container not running: $PG_CONTAINER" >&2
    echo "Or set PGHOST for TCP mode (CI)." >&2
    exit 1
  }
  psql_db() {
    local db="$1"; shift
    docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$db" "$@"
  }
  pg_dump_db() {
    local db="$1"; shift
    docker exec "$PG_CONTAINER" pg_dump -U "$PG_USER" -d "$db" "$@"
  }
  scrub_dump() {
    docker exec "$PG_CONTAINER" sh -c "sed -i \
      -e '/^\\\\restrict /d' \
      -e '/^\\\\unrestrict /d' \
      -e '/^CREATE SCHEMA /d' \
      -e '/^CREATE SCHEMA IF NOT EXISTS /d' \
      '$1'"
  }
  rm_dump() {
    docker exec "$PG_CONTAINER" rm -f "$1"
  }
  copy_table_data() {
    local table="$1"
    docker exec "$PG_CONTAINER" sh -c \
      "pg_dump -U '$PG_USER' -d '$SOURCE_DB' --data-only --no-owner --column-inserts -t 'public.$table' \
       | sed -e '/^\\\\restrict /d' -e '/^\\\\unrestrict /d' \
       | psql -U '$PG_USER' -d '$TEST_DB' -v ON_ERROR_STOP=1" >/dev/null
  }
  copy_csv() {
    local src_sql="$1"
    local dst_sql="$2"
    docker exec -i "$PG_CONTAINER" \
      env PGUSER="$PG_USER" SOURCE_DB="$SOURCE_DB" TEST_DB="$TEST_DB" SRC_SQL="$src_sql" DST_SQL="$dst_sql" \
      bash -lc 'psql -U "$PGUSER" -d "$SOURCE_DB" -Atc "$SRC_SQL" | psql -U "$PGUSER" -d "$TEST_DB" -c "$DST_SQL"' >/dev/null
  }
fi

echo "==> Ensuring database exists: $TEST_DB"
psql_db postgres -v ON_ERROR_STOP=1 <<SQL
SELECT 'CREATE DATABASE ${TEST_DB} OWNER ${PG_USER}'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${TEST_DB}')\gexec
SQL

echo "==> Dumping schema from $SOURCE_DB (public + auth)"
pg_dump_db "$SOURCE_DB" \
  --schema-only --no-owner --no-privileges \
  -n public -n auth \
  -f "$SCHEMA_DUMP"
scrub_dump "$SCHEMA_DUMP"

echo "==> Recreating schemas in $TEST_DB"
psql_db "$TEST_DB" -v ON_ERROR_STOP=1 <<SQL
DROP SCHEMA IF EXISTS public CASCADE;
DROP SCHEMA IF EXISTS auth CASCADE;
CREATE SCHEMA public AUTHORIZATION ${PG_USER};
CREATE SCHEMA auth AUTHORIZATION ${PG_USER};
CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;
SQL

psql_db "$TEST_DB" -v ON_ERROR_STOP=1 -f "$SCHEMA_DUMP"
rm_dump "$SCHEMA_DUMP"

echo "==> Patching auth helpers/policies in $TEST_DB for seeded test user"
# Note: pr4m dump hardcodes a production user id in auth helpers/RLS.
# For pr4m_test we bind them to the seeded api-tester user so issue reads work
# without changing application auth semantics.
# Action-based RBAC: permissions use resourceName (e.g. issue.get) + allowed.
psql_db "$TEST_DB" -v ON_ERROR_STOP=1 <<'SQL'
CREATE OR REPLACE FUNCTION auth.access_level(
  p_user_id text,
  p_project_id text,
  p_entity text,
  p_action text
)
RETURNS integer
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
AS $function$
DECLARE
  lvl int := 0;
  resource_name text;
  action_name text;
BEGIN
  IF p_user_id IS NULL THEN RETURN 0; END IF;

  action_name := CASE p_action
    WHEN 'readF' THEN 'get'
    WHEN 'createF' THEN 'create'
    WHEN 'updateF' THEN 'update'
    WHEN 'deleteF' THEN 'delete'
    ELSE lower(p_action)
  END;
  resource_name := lower(p_entity) || '.' || action_name;

  SELECT COALESCE(MAX(CASE WHEN p.allowed = 1 THEN 9 ELSE 0 END), 0)
    INTO lvl
  FROM user_roles ur
  JOIN permissions p ON p."roleId" = ur."roleId"
  WHERE ur."userId" = p_user_id
    AND p."resourceName" = resource_name;

  RETURN lvl;
END;
$function$;

CREATE OR REPLACE FUNCTION auth.accessible_projects_for_issue_read()
RETURNS TABLE(project_id text, read_level integer)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $function$
  SELECT m."projectId", MAX(CASE WHEN p.allowed = 1 THEN 9 ELSE 0 END)
  FROM memberships m
  JOIN user_roles ur ON ur."userId" = m."userId"
  JOIN permissions p ON p."roleId" = ur."roleId"
  WHERE m."userId" = 'api-test-user-0001'
    AND p."resourceName" = 'issue.get'
  GROUP BY m."projectId";
$function$;

CREATE OR REPLACE FUNCTION auth.can_access_issue(
  p_issue_id text,
  p_project_id text,
  p_assignee text,
  p_created_user text,
  p_action text DEFAULT 'readF'::text
)
RETURNS boolean
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
AS $function$
DECLARE
  uid text := 'api-test-user-0001';
  lvl int;
BEGIN
  lvl := auth.access_level(uid, p_project_id, 'Issue', p_action);

  IF lvl = 9 THEN RETURN true; END IF;
  IF lvl = 0 THEN RETURN false; END IF;
  IF lvl = 1 THEN RETURN p_assignee = uid; END IF;
  IF lvl >= 2 THEN RETURN true; END IF;

  RETURN false;
END;
$function$;

DROP POLICY IF EXISTS issues_select ON issues;
CREATE POLICY issues_select ON issues
FOR SELECT
USING (
  (("projectId")::text IN (
    SELECT accessible_projects_for_issue_read.project_id
    FROM auth.accessible_projects_for_issue_read() accessible_projects_for_issue_read(project_id, read_level)
    WHERE accessible_projects_for_issue_read.read_level >= 2
  ))
  OR
  (
    (("projectId")::text IN (
      SELECT accessible_projects_for_issue_read.project_id
      FROM auth.accessible_projects_for_issue_read() accessible_projects_for_issue_read(project_id, read_level)
      WHERE accessible_projects_for_issue_read.read_level = 1
    ))
    AND ((assignee)::text = 'api-test-user-0001'::text)
  )
);
SQL

echo "==> Copying ACL/oauth + catalog baseline data from $SOURCE_DB"
# resources table removed in action-based RBAC; permissions are seeded below.
BASELINE_TABLES=(oauth_clients roles permissions acl_controllers)
for table in "${BASELINE_TABLES[@]}"; do
  echo "    - $table"
  copy_table_data "$table"
done

echo "==> Copying system issue_types / issue_statuses from $SOURCE_DB (as seen in pr4m)"
copy_csv   "COPY (SELECT id,name,\"dateCreated\",\"dateModified\",deleted,description,\"createdUser\",\"modifiedUser\",system,\"projectId\",\"createdUserName\",\"modifiedUserName\" FROM issue_types WHERE system=1 AND deleted=0) TO STDOUT WITH CSV"   "COPY issue_types (id,name,\"dateCreated\",\"dateModified\",deleted,description,\"createdUser\",\"modifiedUser\",system,\"projectId\",\"createdUserName\",\"modifiedUserName\") FROM STDIN WITH CSV"

copy_csv   "COPY (SELECT id,name,\"dateCreated\",\"dateModified\",deleted,description,\"createdUser\",\"createdUserName\",\"modifiedUser\",\"modifiedUserName\",system,\"projectId\",done FROM issue_statuses WHERE system=1 AND deleted=0) TO STDOUT WITH CSV"   "COPY issue_statuses (id,name,\"dateCreated\",\"dateModified\",deleted,description,\"createdUser\",\"createdUserName\",\"modifiedUser\",\"modifiedUserName\",system,\"projectId\",done) FROM STDIN WITH CSV"

echo "==> Seeding deterministic fixture entities modeled on pr4m patterns"
# password hash for: unit-testing
# Patterns mirrored from pr4m:
# - accountStatus = 'active' (lowercase)
# - membership.projectId for project membership
# - user_roles for application-wide role assignment
# - Admin roleId = '1' for seeded user_roles
# - Global role for system-level user_roles (role from pr4m Global roles)
# - system issue types/statuses from copied catalog
# - project name STARTS A/P for query DSL coverage
psql_db "$TEST_DB" -v ON_ERROR_STOP=1 <<'SQL'
BEGIN;

-- Resolve Global role and system status/type from copied catalogs
DO $$
DECLARE
  v_global_role text;
  v_bug_type text;
  v_new_status text;
BEGIN
  SELECT id INTO v_global_role FROM roles WHERE name = 'Global' AND deleted = 0 ORDER BY id LIMIT 1;
  IF v_global_role IS NULL THEN
    v_global_role := '1';
  END IF;

  SELECT id INTO v_bug_type FROM issue_types WHERE system = 1 AND name = 'Bug' AND deleted = 0 LIMIT 1;
  IF v_bug_type IS NULL THEN
    SELECT id INTO v_bug_type FROM issue_types WHERE system = 1 AND deleted = 0 ORDER BY id LIMIT 1;
  END IF;

  SELECT id INTO v_new_status FROM issue_statuses WHERE system = 1 AND name = 'new' AND deleted = 0 LIMIT 1;
  IF v_new_status IS NULL THEN
    SELECT id INTO v_new_status FROM issue_statuses WHERE system = 1 AND deleted = 0 ORDER BY id LIMIT 1;
  END IF;

  CREATE TEMP TABLE seed_ctx (
    global_role text,
    bug_type text,
    new_status text
  ) ON COMMIT DROP;
  INSERT INTO seed_ctx VALUES (v_global_role, v_bug_type, v_new_status);
END $$;

-- Remove leftover runtime tags from previous api-tester runs (unique on tag).
-- Soft-deleted rows still occupy the unique index, so hard-delete by prefix.
DELETE FROM tags
WHERE tag LIKE 'api-tester-tag%';

INSERT INTO users (
  id, password, email, name, deleted, "createdUser", "modifiedUser",
  "createdUserName", "modifiedUserName", "accountStatus", "failedLoginAttempts"
) VALUES (
  'api-test-user-0001',
  '$2y$10$OH8mqmGV2uLOLyoSdLGm/ejzhLXVOsOz/Ld2fi610E/qWWTqQ6e1G',
  'api-tester@example.com',
  'API Tester',
  0,
  'api-test-user-0001',
  'api-test-user-0001',
  'API Tester',
  'API Tester',
  'active',
  0
),
(
  -- Secondary member used for disposable membership mutations (not the auth user)
  'api-test-user-0002',
  '$2y$10$OH8mqmGV2uLOLyoSdLGm/ejzhLXVOsOz/Ld2fi610E/qWWTqQ6e1G',
  'api-tester-member@example.com',
  'API Tester Member',
  0,
  'api-test-user-0001',
  'api-test-user-0001',
  'API Tester',
  'API Tester',
  'active',
  0
),
(
  -- Restricted ACL user: child modules allowed, project.get denied
  'api-test-user-acl-np',
  '$2y$10$OH8mqmGV2uLOLyoSdLGm/ejzhLXVOsOz/Ld2fi610E/qWWTqQ6e1G',
  'api-tester-acl-noproject@example.com',
  'API Tester ACL No Project',
  0,
  'api-test-user-0001',
  'api-test-user-0001',
  'API Tester',
  'API Tester',
  'active',
  0
),
(
  -- Restricted ACL user: project allowed, issue denied (eager-rel omit coverage)
  'api-test-user-acl-pi',
  '$2y$10$OH8mqmGV2uLOLyoSdLGm/ejzhLXVOsOz/Ld2fi610E/qWWTqQ6e1G',
  'api-tester-acl-project@example.com',
  'API Tester ACL Project Only',
  0,
  'api-test-user-0001',
  'api-test-user-0001',
  'API Tester',
  'API Tester',
  'active',
  0
)
ON CONFLICT (id) DO UPDATE SET
  password = EXCLUDED.password,
  email = EXCLUDED.email,
  "accountStatus" = 'active',
  "failedLoginAttempts" = 0;

INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id)
VALUES (
  '1', 'projects4me', '06110fb83488715ca69057f4a7cedf93',
  'http://projects4me/', 'password refresh_token', 'application', NULL
)
ON CONFLICT (client_id) DO UPDATE SET
  client_secret = EXCLUDED.client_secret,
  grant_types = EXCLUDED.grant_types;

-- Projects: names start with A and P for STARTS operator coverage
INSERT INTO projects (
  id, name, "shortCode", type, "modifiedUser", deleted, status,
  "createdUser", "createdUserName", "modifiedUserName", done, "projectManager"
) VALUES
(
  'api-test-project-001', 'Alpha Test Project', 'ATP', 'software',
  'api-test-user-0001', 0, 'in_progress',
  'api-test-user-0001', 'API Tester', 'API Tester', 0, 'api-test-user-0001'
),
(
  'api-test-project-002', 'Projects4Me Mirror', 'P4MM', 'software',
  'api-test-user-0001', 0, 'new',
  'api-test-user-0001', 'API Tester', 'API Tester', 0, 'api-test-user-0001'
)
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, deleted = 0, status = EXCLUDED.status;

-- Project memberships
INSERT INTO memberships (
  id, "createdUser", "modifiedUser", "userId",
  "createdUserName", "modifiedUserName", "projectId"
) VALUES
(
  'api-test-membership-1', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-0001', 'API Tester', 'API Tester', 'api-test-project-001'
),
(
  'api-test-membership-2', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-0001', 'API Tester', 'API Tester', 'api-test-project-002'
)
ON CONFLICT (id) DO UPDATE SET
  "userId" = EXCLUDED."userId",
  "projectId" = EXCLUDED."projectId";

-- Application-wide role assignments
INSERT INTO user_roles (
  id, "createdUser", "modifiedUser", "userId", "roleId",
  "createdUserName", "modifiedUserName"
) VALUES
(
  'api-test-userrole-1', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-0001', '1', 'API Tester', 'API Tester'
),
(
  'api-test-userrole-g', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-0001', (SELECT global_role FROM seed_ctx), 'API Tester', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET
  "userId" = EXCLUDED."userId",
  "roleId" = EXCLUDED."roleId";

-- Action-based ACL grants for Admin + Global roles used by the test user.
-- Covers default RestController actions for modules exercised by api-tester.
DELETE FROM permissions
WHERE id LIKE 'atp-%'
   OR "roleId" IN ('1', (SELECT global_role FROM seed_ctx));

INSERT INTO permissions (id, "roleId", "resourceName", allowed, "dateCreated", "dateModified")
SELECT
  -- id is varchar(36): "atp-" (4) + md5 hex (32)
  'atp-' || md5(r.role_id || ':' || m.module || '.' || a.action),
  r.role_id,
  m.module || '.' || a.action,
  1,
  NOW(),
  NOW()
FROM (
  SELECT '1'::text AS role_id
  UNION
  SELECT global_role FROM seed_ctx
) r
CROSS JOIN (
  VALUES
    ('activity'), ('comment'), ('conversationroom'), ('issue'), ('issuetype'),
    ('issuestatus'), ('membership'), ('milestone'), ('project'), ('role'),
    ('permission'), ('tag'), ('tagged'), ('timelog'), ('user'), ('userskill'),
    ('userqualification'), ('wiki'), ('vote'), ('savedsearch'), ('upload'),
    ('systemsetting')
) AS m(module)
CROSS JOIN (
  VALUES ('get'), ('create'), ('update'), ('delete')
) AS a(action);

-- Roles for group-based ACL api-tester scenarios
INSERT INTO roles (
  id, name, description, deleted, "createdUser", "modifiedUser", "createdUserName", "modifiedUserName"
) VALUES
(
  'api-test-role-acl-np', 'ACL No Project', 'issue/comment allowed, project.get denied', 0,
  'api-test-user-0001', 'api-test-user-0001', 'API Tester', 'API Tester'
),
(
  'api-test-role-acl-pi', 'ACL Project Only', 'project allowed, issue.get denied', 0,
  'api-test-user-0001', 'api-test-user-0001', 'API Tester', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, deleted = 0;

DELETE FROM permissions WHERE id LIKE 'atp-acl-%';
DELETE FROM user_roles WHERE id LIKE 'api-test-userrole-acl-%';

INSERT INTO user_roles (
  id, "createdUser", "modifiedUser", "userId", "roleId",
  "createdUserName", "modifiedUserName"
) VALUES
(
  'api-test-userrole-acl-np', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-acl-np', 'api-test-role-acl-np', 'API Tester', 'API Tester'
),
(
  'api-test-userrole-acl-pi', 'api-test-user-0001', 'api-test-user-0001',
  'api-test-user-acl-pi', 'api-test-role-acl-pi', 'API Tester', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET
  "userId" = EXCLUDED."userId",
  "roleId" = EXCLUDED."roleId";

-- No-project role: child modules allowed, project.get denied (group cascade deny)
INSERT INTO permissions (id, "roleId", "resourceName", allowed, "dateCreated", "dateModified")
VALUES
  ('atp-acl-np-issue-get', 'api-test-role-acl-np', 'issue.get', 1, NOW(), NOW()),
  ('atp-acl-np-comment-get', 'api-test-role-acl-np', 'comment.get', 1, NOW(), NOW()),
  ('atp-acl-np-conv-get', 'api-test-role-acl-np', 'conversationroom.get', 1, NOW(), NOW()),
  ('atp-acl-np-project-get', 'api-test-role-acl-np', 'project.get', 0, NOW(), NOW());

-- Project-only role: can read projects, cannot read issues (rel omit / related 403)
INSERT INTO permissions (id, "roleId", "resourceName", allowed, "dateCreated", "dateModified")
VALUES
  ('atp-acl-pi-project-get', 'api-test-role-acl-pi', 'project.get', 1, NOW(), NOW()),
  ('atp-acl-pi-issue-get', 'api-test-role-acl-pi', 'issue.get', 0, NOW(), NOW()),
  ('atp-acl-pi-conv-get', 'api-test-role-acl-pi', 'conversationroom.get', 1, NOW(), NOW()),
  ('atp-acl-pi-comment-get', 'api-test-role-acl-pi', 'comment.get', 1, NOW(), NOW());

-- ACL controller entry for user (mirrors pr4m acl_controllers.relatedTo='user')
INSERT INTO acl_controllers (
  id, "relatedId", "relatedTo", lft, rht, deleted, "createdUser", "createdUserName", "modifiedUser", "modifiedUserName"
) VALUES (
  'api-test-acl-ctrl-01', 'api-test-user-0001', 'user', 0, 0, 0,
  'api-test-user-0001', 'API Tester', 'api-test-user-0001', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET "relatedId" = EXCLUDED."relatedId", deleted = 0;

INSERT INTO milestones (
  id, name, deleted, "createdUser", "modifiedUser", status, "milestoneType", "projectId",
  "createdUserName", "modifiedUserName"
) VALUES
(
  'api-test-milestone-01', 'Sprint 1', 0, 'api-test-user-0001', 'api-test-user-0001',
  'in_progress', 'sprint', 'api-test-project-001', 'API Tester', 'API Tester'
),
(
  'api-test-milestone-02', 'Planned Release', 0, 'api-test-user-0001', 'api-test-user-0001',
  'planned', 'release', 'api-test-project-001', 'API Tester', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, deleted = 0;

INSERT INTO conversation_rooms (
  id, subject, deleted, "createdUser", "modifiedUser", "roomType", "projectId",
  "createdUserName", "modifiedUserName", "projectShortcode", "dateModified"
) VALUES (
  'api-test-convroom-001', 'API Test Room', 0, 'api-test-user-0001', 'api-test-user-0001',
  'project', 'api-test-project-001', 'API Tester', 'API Tester', 'ATP', NOW() - INTERVAL '1 day'
)
ON CONFLICT (id) DO UPDATE SET deleted = 0, "projectId" = EXCLUDED."projectId";

-- Seed comment for ACL direct-access cases (after conversation room exists)
INSERT INTO comments (
  id, comment, deleted, "createdUser", "modifiedUser",
  "createdUserName", "modifiedUserName", "relatedTo", "relatedId"
) VALUES (
  'api-test-comment-000001', 'ACL seed comment', 0,
  'api-test-user-0001', 'api-test-user-0001',
  'API Tester', 'API Tester', 'conversationrooms', 'api-test-convroom-001'
)
ON CONFLICT (id) DO UPDATE SET deleted = 0, "relatedId" = EXCLUDED."relatedId";

-- Issue with milestone + one without milestone (NULL/EMPTY query coverage)
INSERT INTO issues (
  id, subject, deleted, "createdUser", owner, assignee, "reportedUser", "modifiedUser",
  priority, status, "projectId", "milestoneId", "typeId", "createdUserName", "modifiedUserName",
  "statusId", "projectShortcode", "isPlanned", "isReopened", "issueNumber"
) VALUES
(
  'api-test-issue-000001', 'API Tester Seed Issue', 0,
  'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001',
  'medium', 'new', 'api-test-project-001', 'api-test-milestone-01', (SELECT bug_type FROM seed_ctx),
  'API Tester', 'API Tester', (SELECT new_status FROM seed_ctx), 'ATP', 0, 0, 1
),
(
  'api-test-issue-000002', 'Backlog Issue No Milestone', 0,
  'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001', 'api-test-user-0001',
  'low', 'new', 'api-test-project-001', NULL, (SELECT bug_type FROM seed_ctx),
  'API Tester', 'API Tester', (SELECT new_status FROM seed_ctx), 'ATP', 0, 0, 2
)
ON CONFLICT (id) DO UPDATE SET subject = EXCLUDED.subject, deleted = 0, "milestoneId" = EXCLUDED."milestoneId";

INSERT INTO saved_searches (
  id, "createdUser", "createdUserName", public, "relatedTo", name, searchquery, "projectId", deleted
) VALUES (
  'api-test-savedsearch1', 'api-test-user-0001', 'API Tester', 1, 'issue',
  'Open Issues', '(Issue.projectId : api-test-project-001)', 'api-test-project-001', 0
)
ON CONFLICT (id) DO UPDATE SET public = 1, deleted = 0;

INSERT INTO system_notifications (
  id, description, "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", context
) VALUES (
  'api-test-sysnotif-01', 'API tester notification', 'api-test-user-0001', 'API Tester',
  'api-test-user-0001', 'API Tester', '{"source":"api-tester"}'
)
ON CONFLICT (id) DO UPDATE SET description = EXCLUDED.description;

INSERT INTO time_logs (
  id, deleted, "createdUser", "modifiedUser", "issueId", minutes, hours, days, context,
  "createdUserName", "modifiedUserName", "projectId", "projectShortcode"
) VALUES (
  'api-test-timelog-00001', 0, 'api-test-user-0001', 'api-test-user-0001',
  'api-test-issue-000001', 30, 0, 0, 'work',
  'API Tester', 'API Tester', 'api-test-project-001', 'ATP'
)
ON CONFLICT (id) DO UPDATE SET minutes = 30, deleted = 0;

INSERT INTO activities (
  id, description, "createdUser", "relatedTo", "relatedId", type, "createdUserName", deleted
) VALUES (
  'api-test-activity-0001', 'Seeded activity', 'api-test-user-0001',
  'Issue', 'api-test-issue-000001', 'create', 'API Tester', 0
)
ON CONFLICT (id) DO UPDATE SET deleted = 0;

INSERT INTO system_notification_recipients (
  id, "systemNotificationId", "userId", "isRead",
  "createdUser", "createdUserName", "modifiedUser", "modifiedUserName"
) VALUES (
  'api-test-sysnotifrec01', 'api-test-sysnotif-01', 'api-test-user-0001', 0,
  'api-test-user-0001', 'API Tester', 'api-test-user-0001', 'API Tester'
)
ON CONFLICT (id) DO UPDATE SET "isRead" = 0, "userId" = EXCLUDED."userId";

INSERT INTO dashboards (
  id, "userId", name, deleted, widgets
) VALUES (
  'api-test-dashboard-001', 'api-test-user-0001', 'API Tester Dashboard', 0, '[]'
)
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, deleted = 0;

-- Keep serial in sync after seeding explicit issueNumber values
SELECT setval(
  '"issues_issueNumber_seq"',
  GREATEST((SELECT COALESCE(MAX("issueNumber"), 1) FROM issues), 1)
);

-- Refresh auth materialized views when present (used by RLS helpers)
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM pg_matviews WHERE schemaname = 'auth' AND matviewname = 'accessible_issues'
  ) THEN
    REFRESH MATERIALIZED VIEW auth.accessible_issues;
  END IF;
END $$;

COMMIT;
SQL

echo "==> Writing fixtures JSON for portable api-tester (IDs read from seeded DB)"
BUG_TYPE_ID="$(psql_db "$TEST_DB" -Atc "SELECT id FROM issue_types WHERE system=1 AND name='Bug' AND deleted=0 LIMIT 1")"
NEW_STATUS_ID="$(psql_db "$TEST_DB" -Atc "SELECT id FROM issue_statuses WHERE system=1 AND name='new' AND deleted=0 LIMIT 1")"
python3 - <<PY
import json, os
path = """${FIXTURES_OUT}"""
data = {
  "description": "Generated by Gaia harness prepare-test-db.sh from pr4m-shaped seed data.",
  "auth": {
    "clientId": "projects4me",
    "clientSecret": "06110fb83488715ca69057f4a7cedf93",
    "email": "api-tester@example.com",
    "password": "unit-testing",
    "tokenPath": "/api/v1/token"
  },
  "authProfiles": {
    "aclNoProject": {
      "email": "api-tester-acl-noproject@example.com",
      "password": "unit-testing"
    },
    "aclProjectOnly": {
      "email": "api-tester-acl-project@example.com",
      "password": "unit-testing"
    }
  },
  "values": {
    "userId": "api-test-user-0001",
    "memberUserId": "api-test-user-0002",
    "projectId": "api-test-project-001",
    "projectShortcode": "ATP",
    "issueId": "api-test-issue-000001",
    "milestoneId": "api-test-milestone-01",
    "roleId": "1",
    "developerRoleId": "3",
    "membershipId": "api-test-membership-1",
    "issuestatusId": """${NEW_STATUS_ID}""" or "api-test-issuestatus1",
    "issuetypeId": """${BUG_TYPE_ID}""" or "api-test-issuetype-01",
    "systemnotificationId": "api-test-sysnotif-01",
    "systemnotificationrecipientId": "api-test-sysnotifrec01",
    "conversationroomId": "api-test-convroom-001",
    "commentId": "api-test-comment-000001",
    "activityId": "api-test-activity-0001",
    "timelogId": "api-test-timelog-00001",
    "dashboardId": "api-test-dashboard-001",
    "savedsearchId": "api-test-savedsearch1",
    "params.c_id": "api-test-convroom-001",
    "params.issue_number": "1",
    "milestone.id": "api-test-milestone-01",
    "this.trackedProject.getProjectId()": "api-test-project-001"
  }
}
os.makedirs(os.path.dirname(path), exist_ok=True)
with open(path, "w") as f:
    json.dump(data, f, indent=2)
    f.write("\n")
print("Wrote", path)
print("issuetypeId=", data["values"]["issuetypeId"])
print("issuestatusId=", data["values"]["issuestatusId"])
PY

echo "==> Done preparing $TEST_DB"
echo
echo "Env ready. Point api-tester at a running API, e.g.:"
echo "  ./tools/api-tester/harness/use-test-db.sh   # optional: local gaia-test on :8081"
echo "  ./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode backend"
echo "  ./tools/api-tester/harness/run-api-tests.sh --base-uri http://localhost:8081 --mode acl"
