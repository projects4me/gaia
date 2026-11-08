-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- OAuth client kinds (oauth_clients.kind, see rbac decision D-062).
-- The migration adds the column with every existing client as 'app'; this seed marks the clients
-- agents sign in with (client_credentials clients bound to an agent's user) as 'agent'.
-- Idempotent: safe to run on every environment and every deploy.
--   psql -d <database> -f db/seeds/oauth_client_kinds.sql
-- Until it runs, agent sign-in keeps working: the grant treats any non-service client as a
-- possible agent and checks for an active agent user, as before.

UPDATE oauth_clients c
SET kind = 'agent'
WHERE c.kind = 'app'
  AND c.grant_types = 'client_credentials'
  AND EXISTS (SELECT 1 FROM agents a WHERE a."userId" = c.user_id AND a.deleted = FALSE);
