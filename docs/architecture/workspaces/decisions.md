# Workspaces Decisions

Agreed decisions for Workspaces in Gaia. Add a new decision whenever behavior is agreed or changed.

| Field | Value |
|-------|-------|
| Status | In progress |
| Scope | Gaia backend, `Workspace` resource |
| Primary code | `app/metadata/model/Workspace.php`, `app/api/v1/controllers/WorkspaceController.php` |

---

## Architecture decisions

### D-001 — A workspace groups projects; membership and ACL stay project-based

| | |
|---|---|
| Status | Accepted |
| Decision | A workspace is a holding entity for projects and their environment (repositories, runtime, agents, tools, skills, workflows). It has no members of its own and no record scope: membership and record ACL stay on the projects. The `Workspace` resource is standard CRUD on `/api/v1/workspace`, permissioned by actions only (`workspace.get`, `workspace.create`, `workspace.update`, `workspace.delete`), with no `acl.groups`. Later issues add the project link; the workspace then only keeps memberships in sync. |
| Rationale | Project membership is already the access model for every project record. A second membership on the workspace would give two sources of truth for who can see a project. |
| Consequence | Deleting a workspace is a plain soft delete for now; the delete gates come with the project link. No permission seed SQL ships: permissions are granted by hand. |

### D-002 — Every project has a workspace; a project created on its own gets a one-project workspace

| | |
|---|---|
| Status | Accepted |
| Decision | `projects.workspaceId` is nullable in the database (existing rows are filled by a manual script later) but every new project gets one. `POST /project` without `workspaceId` creates a `Workspace` named after the project (cut to 100 characters) and sets it. With `workspaceId`, the workspace must exist (422 otherwise) and the caller needs `workspace.update` (403 otherwise). The rule lives in `Gaia\Libraries\Workspaces\ProjectWorkspace`, called from `projectWorkspaceBehavior`, so every create path gets it. `PATCH /project/{id}` refuses a changed `workspaceId` (422, "Moving a project is not supported yet.") until the project-move issue lands. |
| Rationale | Nobody has to think about workspaces to create a project, and the rule holds for REST and workflow creates alike. |
| Consequence | The workspace is created in `beforeCreate`, after the project own validation, but not in one database transaction with the project: a failure of the project insert itself would leave an empty workspace. Outside a request (no ACL loaded) the `workspace.update` check is skipped. |
