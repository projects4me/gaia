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

### D-003 — A repository is a workspace's git URL and base branch, with no secrets

| | |
|---|---|
| Status | Accepted |
| Decision | A workspace's environment lists the repositories its agents work on. A `Repository` (table `repositories`) holds `workspaceId`, `name`, `url`, `baseBranch` (default `main`) and `description`, with standard CRUD on `/api/v1/repository`, permissioned by actions only (`repository.get`, `repository.create`, `repository.update`, `repository.delete`) and no record scope. Creating or editing one also needs `workspace.update` (`RepositoryController`), since it changes the workspace's environment. `repositoryBehavior` keeps the name unique within the workspace, the workspace existing, and `workspaceId` unchangeable after create (422). `RepositoryUrl` accepts an https or ssh git URL (or the scp-like `git@host:owner/repo.git`) and rejects one with credentials embedded, such as `https://user:token@host/repo.git` (422). |
| Rationale | Gaia owns the data but not the secrets: tokens and keys stay on the runtime, so a URL that carries one is refused instead of stored. Requiring `workspace.update` keeps "who may change a workspace" in one grant. |
| Consequence | Runtime reads of repositories and linking a repository to projects come in later issues. No permission seed SQL ships: permissions are granted by hand. |

### D-004 — All projects of a workspace have the same members

| | |
|---|---|
| Status | Accepted |
| Decision | Membership and record ACL stay on the projects; the workspace only keeps the member set the same in all its projects. Creating a `Membership` in a project of a workspace creates the same user's membership in every other live project of that workspace that lacks it; deleting one deletes the user's memberships in all the other projects. A project created into an existing workspace gets the members of the workspace's other projects. The rule lives in `Gaia\Libraries\Workspaces\MembershipSync` (data access in `WorkspaceMemberships`), called from the `Membership` model's `afterCreate`/`afterDelete` hooks and from `projectWorkspaceBehavior::afterCreate`, so REST, collection requests, workflows and the system all sync. The sync acts as the system: the caller's `membership.create`/`membership.delete` on the one project is enough, record scope on the other projects is not checked. Rows written by the sync do not start another round (a guard flag while a round runs). |
| Rationale | Project membership is what the ACL (and the planned rule engine) builds on; keeping it per project and syncing it keeps one source of truth for who sees a project. |
| Consequence | A membership save or delete and its sync run in one database transaction: on its own, a failed sync rolls both back and fails the write with a message on `projectId`; inside a caller's transaction the write joins it and a failed sync throws, so the caller rolls back. After the write, `user.access.changed` is published once per synced user (after commit when the write owns the transaction, before the caller's commit when it joins one) (the event is user-scoped and carries only the user id), so Hermes re-checks access for writes from any path; a REST write also publishes its own from `MembershipLiveEventsComponent`. Synced rows send no notification and record no project activity: the direct write's one notification and activity stand for the workspace join. Seeding a new project runs after the project insert, all or none, but not in one transaction with it (as in D-002): a failed seed is rolled back and logged, and the project stays (the next membership write in the workspace brings it in line). `OnboardMemberComponent` reuses the project manager's seeded membership instead of creating a second one, and still notifies them. Changing `projectId` or `userId` of an existing membership is not synced. Issues assigned to a removed member in the other projects are not handed over yet. |

### D-005 — Removing a member hands over their open issues

| | |
|---|---|
| Status | Accepted |
| Decision | `DELETE /api/v1/membership/{id}` takes an optional query parameter `handoverTo` (a user id). Removal takes the user out of the whole workspace (D-004), so their open issues (status not `done`, per the project's issue statuses) in every live project of the workspace need a new assignee. Without `handoverTo` and with open issues: 409, with their number, nothing deleted. With `handoverTo=<userId>`: it must be a remaining member of the workspace, a person or an agent, and not the removed user (422 otherwise); the issues are reassigned through the `Issue` model (so activities, notifications and events fire) and the membership is deleted, all in one transaction. With no open issues the membership is deleted without the parameter. `handoverTo=none` is refused with 422: `Issue.assignee` is required and Gaia has no unassigned value. The rule lives in `Gaia\Libraries\Workspaces\MemberRemoval`, called by `MembershipController::deleteAction`. It replaces the earlier `newAssigneeId` parameter, which handed over only the one project's in-progress issues. |
| Rationale | An issue left with someone who has no access to its project can not be worked or seen by them. |
| Consequence | Unassigning needs an assignee-less issue state, a separate decision. The handover and delete run in one transaction, and the D-004 sync joins it. |
