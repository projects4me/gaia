-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- Make an existing user an agent (users.type = Agent plus its agents row), e.g. the coding agent the
-- workflow tools sign in as (Agent Odysseus and the per-engineer agent users). New agents are created
-- through POST /api/v1/agent instead.
-- The user keeps its password hash, so tools that already sign in as it keep working; the user
-- endpoints refuse to set a new password on an agent.
-- Roles are not touched: assign the agent's role (e.g. a 'Coding Agent' role) in Roles.
-- Idempotent: run it again and nothing changes. Run it after migrating, once per agent user:
--   psql -d <database> -v ON_ERROR_STOP=1 -v email=agent.odysseus@projects4.me -f db/seeds/agent_user.sql

UPDATE users
SET type = 'Agent'
WHERE email = :'email'
  AND deleted = 0
  AND type IS DISTINCT FROM 'Agent';

INSERT INTO agents (
    id, "userId", name, purpose, deleted,
    "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "dateCreated", "dateModified"
)
SELECT gen_random_uuid()::varchar, u.id, u.name, 'Coding agent', FALSE,
       u.id, u.name, u.id, u.name, NOW(), NOW()
FROM users AS u
WHERE u.email = :'email'
  AND u.deleted = 0
ON CONFLICT ("userId") DO NOTHING;
