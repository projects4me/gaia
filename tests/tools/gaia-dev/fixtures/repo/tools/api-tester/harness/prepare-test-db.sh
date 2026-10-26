#!/usr/bin/env bash
# Test fixture for tests/tools/gaia-dev: the seed statements ProfileExtractor reads.

psql <<SQL
INSERT INTO users (
  id, password, email, name
) VALUES (
  'fixture-user-1',
  '$2y$10$fixturehash',
  'fixture@example.com',
  'Fixture User'
),
(
  -- A restricted user
  'fixture-user-2',
  '$2y$10$fixturehash',
  'limited@example.com',
  'Limited User'
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO user_roles (
  id, "createdUser", "modifiedUser", "userId", "roleId"
) VALUES
(
  'fixture-userrole-1', 'fixture-user-1', 'fixture-user-1',
  'fixture-user-1', '1'
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO permissions (id, "roleId", "resourceName", allowed)
SELECT 'atp-' || md5(m.module || a.action), '1', m.module || '.' || a.action, 1
FROM (
  VALUES
    ('widget'), ('tag')
) AS m(module)
CROSS JOIN (
  VALUES ('get'), ('create')
) AS a(action);

INSERT INTO roles (
  id, name, description, deleted
) VALUES
(
  'fixture-role-limited', 'Limited', 'widget.get for members only', 0
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO user_roles (
  id, "createdUser", "modifiedUser", "userId", "roleId"
) VALUES
(
  'fixture-userrole-2', 'fixture-user-1', 'fixture-user-1',
  'fixture-user-2', 'fixture-role-limited'
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO permissions (id, "roleId", "resourceName", allowed)
VALUES
  ('atp-limited-widget-get', 'fixture-role-limited', 'widget.get', 2),
  ('atp-limited-widget-create', 'fixture-role-limited', 'widget.create', 0),
  ('atp-limited-secret-get', 'fixture-role-limited', 'widget.secret.get', 0);
SQL
