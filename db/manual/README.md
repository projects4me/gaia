# Manual database scripts

Scripts here are run by hand, on purpose. Nothing loads this folder: `db/seeds/*.sql` is read by explicit file name
by the test harness, so keep manual scripts out of `db/seeds`.

## workspaces_default.sql

When: once, on a database that holds projects, agents, tool servers, skills or workflows from before workspaces.
It creates the workspace "Default" (id `00000000-0000-4000-8000-000000000001`) if missing, puts every row without
a workspace into it, and adds the missing memberships so all projects of Default share one member set.
Existing memberships are never removed. It then gives every live workspace without a short code one (Workspaces
D-012): `default` for Default, otherwise the code of its oldest project, or its name, with a number appended when
taken. Last, every project in a workspace without a join date (`projects.workspaceJoinedAt`, Workspaces D-015) gets
its workspace's creation date. Run it after the migrations that add
`workspaces.shortCode` and `projects.workspaceJoinedAt`. Safe to run twice: the second run changes nothing.

Run it against a copy first, then the real database (take a backup):

    psql -v ON_ERROR_STOP=1 -d <database> -f db/manual/workspaces_default.sql
