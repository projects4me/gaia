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
| Decision | `projects.workspaceId` is nullable in the database (existing rows are filled by a manual script later) but every new project gets one. `POST /project` without `workspaceId` creates a `Workspace` named after the project (cut to 100 characters) and sets it. With `workspaceId`, the workspace must exist (422 otherwise) and the caller needs `workspace.update` (403 otherwise). The rule lives in `Gaia\Libraries\Workspaces\ProjectWorkspace`, called from `projectWorkspaceBehavior`, so every create path gets it. Moving a project to another workspace later is D-011. |
| Rationale | Nobody has to think about workspaces to create a project, and the rule holds for REST and workflow creates alike. |
| Consequence | The workspace is created in `beforeCreate`, after the project own validation, but not in one database transaction with the project: a failure of the project insert itself would leave an empty workspace. Outside a request (no ACL loaded) the `workspace.update` check is skipped. |

### D-003 — A repository is a workspace's git URL and base branch, with no secrets

| | |
|---|---|
| Status | Accepted |
| Decision | A workspace's environment lists the repositories its agents work on. A `Repository` (table `repositories`) holds `workspaceId`, `name`, `url`, `baseBranch` (default `main`) and `description`, with standard CRUD on `/api/v1/repository`, permissioned by actions only (`repository.get`, `repository.create`, `repository.update`, `repository.delete`) and no record scope. Creating or editing one also needs `workspace.update` (`RepositoryController`), since it changes the workspace's environment. `repositoryBehavior` keeps the name unique within the workspace, the workspace existing, and `workspaceId` unchangeable after create (422). `RepositoryUrl` accepts an https or ssh git URL (or the scp-like `git@host:owner/repo.git`) and rejects one with credentials embedded, such as `https://user:token@host/repo.git` (422). |
| Rationale | Gaia owns the data but not the secrets: tokens and keys stay on the runtime, so a URL that carries one is refused instead of stored. Requiring `workspace.update` keeps "who may change a workspace" in one grant. |
| Consequence | Runtime reads of repositories come in a later issue; tying a repository to one project is D-015. No permission seed SQL ships: permissions are granted by hand. |

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

### D-006 — An agent belongs to one workspace and works only in its projects

| | |
|---|---|
| Status | Accepted |
| Decision | `agents.workspaceId` is nullable in the database (existing rows are filled by a manual script later) but required on `POST /agent` (422) and unchangeable afterwards (422, "The workspace of an agent cannot be changed."); the workspace must exist. `Workspace` has an `agents` relation. A `Membership` for an agent user may only be created in a project of the agent's workspace (422 on `projectId` otherwise), and an issue can be assigned to an agent only when its project is in the agent's workspace (422 on `assignee`, on create and when the assignee or project changes). The workspace sync (D-004) adds the agent to the workspace's other projects, which pass the guard. The rules live in `Gaia\Libraries\Workspaces\AgentWorkspace`, called from `agentWorkspaceBehavior`, `membershipAgentWorkspaceBehavior` and `issueAgentWorkspaceBehavior`, so every write path gets them. |
| Rationale | An agent works with the members and issues of its workspace; keeping that in the model layer holds for REST, workflows and the system alike. |
| Consequence | A user that is not an agent, and an agent without a workspace (legacy row), are not restricted. Moving an agent between workspaces, runtime rules and tool and skill grants are separate issues. No permission seed SQL ships. |

### D-007 — Tool servers and skills belong to a workspace; grants stay inside it

| | |
|---|---|
| Status | Accepted |
| Decision | `tool_servers.workspaceId` and `skills.workspaceId` are nullable in the database (existing rows are filled by a manual script later) but required on `POST /toolserver` and `POST /skill` (422) and unchangeable afterwards (422, "The workspace of a tool server or skill cannot be changed."); the workspace must exist. A tool has no field of its own: it follows its tool server. `Workspace` has `toolservers` and `skills` relations. `POST /agenttool` is refused with 422 (on `toolServerId` or `toolId`) when the agent and the tool server (or the server of the tool) are in different workspaces, and `POST /agentskill` with 422 (on `skillId`) when the agent and the skill are. The rules live in `Gaia\Libraries\Workspaces\CatalogWorkspace`, called from `catalogWorkspaceBehavior`, `agenttoolWorkspaceBehavior` and `agentskillWorkspaceBehavior`. |
| Rationale | An agent only gets the tools and skills of its own workspace; keeping the check in the model layer holds for REST, workflows and the system alike. |
| Consequence | A tool server, skill or agent without a workspace (legacy row) is not restricted. Moving tools or skills between workspaces and runtime read filters are separate issues. No permission seed SQL ships. |

### D-008 — A workflow belongs to one workspace; its scope and agents stay inside it

| | |
|---|---|
| Status | Accepted |
| Decision | `workflows.workspaceId` is nullable in the database (existing rows are filled by a manual script later) but required on `POST /workflow` (422) and unchangeable afterwards (422, "The workspace of a workflow cannot be changed."); the workspace must exist. `Workspace` has a `workflows` relation. `Workflow.scope` may only list projects of the workflow's workspace: a foreign project is refused with 422 on `scope`, naming the foreign ids, on create and on every update. An agent named by a node's `ref:Agent` config field (for example Run agent) must be in the workflow's workspace: a node save with another workspace's agent (or one without a workspace) is refused with 422 on `config`, and activating a workflow whose existing nodes break this is refused with 422 on `status`. The rules live in `Gaia\Libraries\Workspaces\WorkflowWorkspace`, called from `workflowWorkspaceBehavior` and `workflowNodeBehavior`, so every write path gets them. |
| Rationale | A workflow runs with the members, projects and agents of one workspace (D-006); keeping that in the model layer holds for REST, workflows and the system alike. |
| Consequence | A workflow without a workspace (legacy row) is not restricted. Moving a workflow between workspaces and re-checking runs already in flight are separate issues. Agent references written as expressions name no agent until the run, so they are not checked. No permission seed SQL ships. |

### D-009 — A workspace is bound to one runtime, which acts only for its agents

| | |
|---|---|
| Status | Accepted |
| Decision | `workspaces.runtimeId` (nullable) names the one runtime service client that may act for the workspace's agents; it must be an active service client of kind `runtime` when set or changed (422). A runtime may serve several workspaces. The delegation rule is RBAC D-073. |
| Rationale | The workspace is the environment agents run in, so it is where the runtime that runs them is chosen. |
| Consequence | `Workspace` has a `runtime` relation; the rule lives in `Gaia\Libraries\Workspaces\WorkspaceRuntime`, checked by `workspaceRuntimeBehavior`. A disabled or deleted runtime keeps its bindings but can no longer delegate. Routing run requests to the bound runtime and runtime read filters are separate issues. |

### D-010 — A workspace can be deleted only when it owns nothing

| | |
|---|---|
| Status | Accepted |
| Decision | `DELETE /api/v1/workspace/{id}` is refused with 409 while the workspace still has live (not deleted) projects, repositories, agents, tool servers, skills or workflows. The message and a `counts` object name how many of each kind. Projects and agents already cover agent runs and the issues assigned to the workspace's agents, so those need no check of their own. The rule lives in `Gaia\Libraries\Workspaces\WorkspaceDeleteGate` (one count per owned kind), called from `WorkspaceController::deleteAction`; any other delete path asks the same class. |
| Rationale | Deleting a workspace must not strand the things it holds; refusing is safer than guessing what to do with them. |
| Consequence | Nothing is cascaded: the caller deletes or moves the contents first. An empty workspace is soft-deleted as before. No permission seed SQL ships. |


### D-011 — A project moves to another workspace only when no work of the old one is in progress

| | |
|---|---|
| Status | Accepted |
| Decision | `PATCH /project/{id}` with a new `workspaceId` moves the project (it replaces the earlier 422). The caller needs `project.update` and `workspace.update`; `workspace.update` has no record scope, so one check covers the old and the new workspace (403, "You are not allowed to move projects between these workspaces."). The target must exist (422 on `workspaceId`); clearing it is refused (422, "A project must belong to a workspace."). The move is refused with 409, naming what blocks it, while: the project has `running` or `waiting` workflow runs; it has open issues (status not `done`) assigned to agents of the old workspace; workflows of the old workspace list it in their scope (named). After a move, in one transaction with the project save: the old workspace's agents' memberships in the project are deleted (the gate ensures they have no open issues there), then every project of the new workspace gets the union of the moved project's remaining members and the new workspace's members, through `MembershipSync` (which publishes `user.access.changed` per changed user after the commit). The gate lives in `Gaia\Libraries\Workspaces\ProjectMoveGate` (one method per kind of blocking work), called from `projectWorkspaceBehavior`; the save's transaction and the sync run from the `Project` model, so every write path gets them. |
| Rationale | A one-project workspace can join a team's workspace later, but work of the old workspace (its runs, its agents' issues, its workflows) must not be left pointing into a project it no longer holds. |
| Consequence | A legacy project without a workspace may be moved into one: only the run gate applies and no agents are removed. Agents, tools, skills and workflows never move with the project; its repositories do (D-015). As in D-004, synced rows send no notification and record no project activity. Outside a request (no ACL loaded) the `workspace.update` check is skipped. |

### D-012 — A workspace has a short code, unique among live workspaces

| | |
|---|---|
| Status | Accepted |
| Decision | `workspaces.shortCode` (varchar 50) is required on create and may be changed later (POST and PATCH). It is stored trimmed and in lower case, must start with a letter and hold only letters and digits (the format of project short codes), else 422 on `shortCode`; a code another workspace that is not deleted uses is refused with 409. The one-project workspace created for a project (D-002) takes the project's short code, or the first free one with a number appended when another workspace has it. The rule lives in `Gaia\Libraries\Workspaces\WorkspaceShortCode`, checked by `workspaceShortCodeBehavior` when the code is set or changed. |
| Rationale | Prometheus addresses a workspace by code (`/workspace/:shortCode`, sidebar, list), as it does projects. |
| Consequence | The column is nullable in the database so the declarative migration can add it to tables that already hold workspaces; the behaviour enforces "required". Rows from before the column keep saving until they are given a code; `db/manual/workspaces_default.sql` gives the Default workspace `default` and every other workspace without one a code from its oldest project (or its name). Uniqueness is checked in the domain, not by a unique index, since deleted rows may reuse a code; two creates racing for one code can both pass. |

### D-013 — A workspace reports its project and member counts as read-only computed fields

| | |
|---|---|
| Status | Accepted |
| Decision | `Workspace.projectCount` is the number of live (not deleted) projects of the workspace; `Workspace.memberCount` is the number of distinct users (agents included) with a membership in any of them, so a member of several of its projects counts once. Both are workspace totals, whatever projects the caller can see. They come back on `GET /workspace` and `GET /workspace/{id}` (the default select, or `fields=Workspace.projectCount`), and `sort=Workspace.projectCount` orders by them. A POST/PUT/PATCH body naming them is accepted and they are discarded, like a field the caller may not write. They are declared under the model metadata key `computed` (name, label, type and a PHQL `expression`), not under `fields`: no column, migration, permission-catalog entry, CSV column or `query` filter exists for them (a filter on one is 400 like an unknown field). |
| Rationale | The Workspaces list shows both counts on every row. A subquery column gives them in the list's own query, without a request per row and without a custom endpoint, and keeping them out of `fields` leaves every column-based path unchanged. |
| Consequence | `Manager::getComputedFields()` lists a model's computed fields. `Query::prepareSelectQuery()` takes them out of the requested fields and selects them through `Gaia\Core\MVC\Models\Query\ComputedColumns` as `(<expression>) AS <name>`, ordering a sort on one by that alias (a sort on one that is not selected is dropped). A grouped or counted select adds them only when requested. `Acl::isFieldActionAllowed()` allows `get` on a computed field when the model's `get` is allowed and denies every write, so no permission row is needed. Another model gets a computed field by declaring one in its metadata. |

### D-014 — A workspace lists its members once each through a read-only view resource

| | |
|---|---|
| Status | Accepted |
| Decision | `GET /api/v1/workspacemember` (and `/{id}`) reads the database view `workspace_members`: one row per live workspace and distinct user with a membership in any of its live projects, so a member of every project appears once and a page of `limit=20` holds 20 different people. Fields: `workspaceId` and `userId` (hasOne `workspace`, `user`), `userName` (the user's name, so `sort=Workspacemember.userName` orders by name), `kind` (`agent` for an agent user, `person` otherwise), `projectCount` (the live projects of the workspace the user is a member of) and `openIssueCount` (issues not deleted, assigned to the user, in a live project of the workspace, whose status is not done: the issues a removal hands over, D-005). The row `id` is the md5 of the workspace id, a colon and the user id, cast to a uuid, so it is stable across reads. Filter with `query=((Workspacemember.workspaceId : X))`. The route allows GET only: memberships stay the source of truth, and a write is 404 like any route that does not exist. Reading needs `workspacemember.get` and, as an authorization group (RBAC D-028), `workspace.get`. |
| Rationale | Paging memberships repeats a person once per project, so a person in all 40+ projects of a workspace filled every page. A view groups the memberships in the database and keeps paging, sorting and filtering on the standard list, without a custom action. Requested as reading with `workspace.get` alone; Gaia has no way for a module to borrow another module's permission, so the resource keeps its own `workspacemember.get` and requires `workspace.get` through its group. |
| Consequence | Files: `app/metadata/model/Workspacemember.php`, `app/models/Workspacemember.php`, `app/api/v1/controllers/WorkspacememberController.php`. The view is created by the migration from the model metadata. No permission seed SQL ships: an admin grants `workspacemember.get` by hand. The rows are not record-scoped (a workspace has none, D-001). The count of a workspace's rows equals `Workspace.memberCount` (D-013). |

### D-015 — A repository belongs to one project of its workspace or to the whole workspace, and moves with its project

| | |
|---|---|
| Status | Accepted |
| Decision | `Repository.projectId` (nullable, hasOne `project`) ties a repository to one project; empty means workspace-wide (an empty string is stored as null). It is set on POST and changed or cleared on PATCH; a value that is not a live project of the repository's workspace is refused with 422 on `projectId`. It is checked only when set or changed, so a repository whose project is later deleted still saves. Filter with the standard `query`, e.g. `((Repository.projectId : X))` or `((Repository.projectId CONTAINS 'X','Y'))`. When a project moves to another workspace (D-011), its live repositories move with it, in the move's transaction: `ProjectRepositories::move()` sets their `workspaceId` with the `movingWithProject` flag, the only way `repositoryBehavior` lets a repository's workspace change. Since names are unique within a workspace, `ProjectMoveGate` refuses the move with 409, naming them, while the target workspace already has a repository with a name of one of the project's. The rule lives in `Gaia\Libraries\Workspaces\ProjectRepositories` and `repositoryBehavior`. |
| Rationale | Prometheus shows each member project's repository on the workspace Overview and counts project and workspace repositories apart. A project's repository is part of the project, not of the old workspace's work: moving it keeps the project working where it lands, where blocking the move would make the user delete and recreate it. |
| Consequence | Workspace-wide repositories never move. A failed repository save rolls back the whole move (422 with the message on `workspaceId`). Deleting a project leaves its repositories in the workspace, still pointing at it. No permission seed SQL ships. |

### D-016 — A workspace reports its environment counts as read-only computed fields

| | |
|---|---|
| Status | Accepted |
| Decision | `Workspace.repositoryCount` (live repositories of the workspace), `projectRepositoryCount` (those with a `projectId`), `agentCount`, `toolserverCount`, `skillCount` (live rows of the workspace) and `runsInProgress` (workflow runs `running` or `waiting` in the workspace's live projects, the definition of the move gate in D-011). They are computed fields like D-013: returned on `GET /workspace` and `GET /workspace/{id}`, sortable, discarded on write, not filterable, whatever rows the caller can see. |
| Rationale | The workspace Overview shows an Environment box; subquery columns give the counts in the same query, without a request per kind and without a custom endpoint. |
| Consequence | Declared under `computed` in `app/metadata/model/Workspace.php`; no column, migration or permission row. |
