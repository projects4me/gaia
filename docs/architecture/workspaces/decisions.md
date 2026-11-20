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

### D-002 — A repository is a workspace's git URL and base branch, with no secrets

| | |
|---|---|
| Status | Accepted |
| Decision | A workspace's environment lists the repositories its agents work on. A `Repository` (table `repositories`) holds `workspaceId`, `name`, `url`, `baseBranch` (default `main`) and `description`, with standard CRUD on `/api/v1/repository`, permissioned by actions only (`repository.get`, `repository.create`, `repository.update`, `repository.delete`) and no record scope. Creating or editing one also needs `workspace.update` (`RepositoryController`), since it changes the workspace's environment. `repositoryBehavior` keeps the name unique within the workspace, the workspace existing, and `workspaceId` unchangeable after create (422). `RepositoryUrl` accepts an https or ssh git URL (or the scp-like `git@host:owner/repo.git`) and rejects one with credentials embedded, such as `https://user:token@host/repo.git` (422). |
| Rationale | Gaia owns the data but not the secrets: tokens and keys stay on the runtime, so a URL that carries one is refused instead of stored. Requiring `workspace.update` keeps "who may change a workspace" in one grant. |
| Consequence | Runtime reads of repositories and linking a repository to projects come in later issues. No permission seed SQL ships: permissions are granted by hand. |
