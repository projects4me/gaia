-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- The 'Agent coding loop' workflow (Workflow, Workflownode, Workflowedge and Workflowreference tables).
-- It replaces the status logic Talos kept for itself:
--
--   Issue assignee changed
--     -> Filter: assigned to Agent Achilles or Agent Odysseus, changed by that agent's sponsor,
--        in a milestone named Claude-v1 or Agents
--     -> Switch on the issue status
--          new                              -> Run agent (new session): start the issue
--          in_progress, feedback, pending   -> Run agent (resume the last session): continue
--          in_review                        -> Run agent (new session): address the review
--     -> the agent reports an outcome, which is the port the run leaves the Run agent step by:
--          done                             -> End
--          needs_input                      -> comment, status Feedback, assign the sponsor
--          blocked, failed, limit           -> comment, status Pending, assign the sponsor
--
-- The agent is the one behind the issue's assignee ({agent:issue.assignee}); the prompts are the ones in
-- dev-tools/talos/agents/odysseus/agent.json. The workflow is scoped to the projects that have a milestone
-- named Claude-v1 or Agents and is seeded as Draft: activate it once Talos' own status logic is switched off.
--
-- Needs the agents 'Agent Achilles' and 'Agent Odysseus' (nothing is seeded when neither exists).
-- Idempotent: ids are derived from the workflow name, and an existing workflow is left as it is so edits
-- made in the editor are kept.
--   docker exec -i postgres-dev psql -U nouman -d pr4m -v ON_ERROR_STOP=1 < db/seeds/agent_coding_loop_workflow.sql

DO $seed$
DECLARE
    wf_name CONSTANT text := 'Agent coding loop';
    wf_id text := md5('workflow:' || wf_name)::uuid::text;
    agent_users jsonb;
    milestone_ids jsonb;
    project_ids jsonb;
    prompt_prefix text;
BEGIN
    IF EXISTS (SELECT 1 FROM workflows WHERE id = wf_id) THEN
        RAISE NOTICE 'Workflow "%" is already seeded, left as it is.', wf_name;
        RETURN;
    END IF;

    SELECT COALESCE(jsonb_agg(DISTINCT "userId"), '[]') INTO agent_users
    FROM agents
    WHERE name IN ('Agent Achilles', 'Agent Odysseus') AND deleted = FALSE;

    IF jsonb_array_length(agent_users) = 0 THEN
        RAISE NOTICE 'No Agent Achilles or Agent Odysseus, workflow "%" not seeded.', wf_name;
        RETURN;
    END IF;

    SELECT COALESCE(jsonb_agg(id), '[]'), COALESCE(jsonb_agg(DISTINCT "projectId"), '[]')
    INTO milestone_ids, project_ids
    FROM milestones
    WHERE name IN ('Claude-v1', 'Agents') AND deleted = 0;

    prompt_prefix := 'Projects4Me issue #{issue.issueNumber} ({issue.projectShortcode})';

    CREATE TEMP TABLE seed_nodes (key text, category text, kind text, name text, config jsonb, x int, y int) ON COMMIT DROP;
    CREATE TEMP TABLE seed_edges (from_key text, port text, to_key text) ON COMMIT DROP;

    INSERT INTO seed_nodes VALUES
        ('trigger', 'trigger', 'issue.assignee.changed', 'Issue assignee changed', '{}', 40, 220),
        ('filter', 'condition', 'filter', 'Assigned to an agent by its sponsor', jsonb_build_object('conditions', jsonb_build_array(
            jsonb_build_object('field', 'issue.assignee', 'operator', 'in', 'value', agent_users),
            jsonb_build_object('field', 'actor.id', 'operator', 'equals', 'value', '{sponsor:issue.assignee}'),
            jsonb_build_object('field', 'issue.milestoneId', 'operator', 'in', 'value', milestone_ids)
        )), 280, 220),
        ('status', 'condition', 'switch', 'Issue status', jsonb_build_object('cases', jsonb_build_array(
            jsonb_build_object('key', 'new', 'conditions', jsonb_build_array(
                jsonb_build_object('field', 'issue.status', 'operator', 'equals', 'value', 'new'))),
            jsonb_build_object('key', 'resume', 'conditions', jsonb_build_array(
                jsonb_build_object('field', 'issue.status', 'operator', 'in', 'value', jsonb_build_array('in_progress', 'feedback', 'pending')))),
            jsonb_build_object('key', 'in_review', 'conditions', jsonb_build_array(
                jsonb_build_object('field', 'issue.status', 'operator', 'equals', 'value', 'in_review')))
        )), 520, 220),
        ('run_new', 'actor', 'run_agent', 'Start the issue', jsonb_build_object(
            'agentId', '{agent:issue.assignee}', 'session', 'new',
            'prompt', 'Work ' || prompt_prefix || ' end to end: run /start-issue {issue.issueNumber}, and implement it. When it is ready, run /ship-issue {issue.issueNumber} and end with outcome done.'
        ), 800, 60),
        ('run_resume', 'actor', 'run_agent', 'Continue the issue', jsonb_build_object(
            'agentId', '{agent:issue.assignee}', 'session', 'resume',
            'prompt', 'Continue ' || prompt_prefix || '. {actor.name} assigned it back to you: read the latest comments (dev-tools/ai/ai issue {issue.issueNumber}), make sure branch claude-issue-{issue.issueNumber} is checked out (dev-tools/ai/ai start <repo> {issue.issueNumber} if not), restart the timer with dev-tools/ai/ai timer {issue.issueNumber}, then finish the work. When it is ready, run /ship-issue {issue.issueNumber} and end with outcome done.'
        ), 800, 220),
        ('run_review', 'actor', 'run_agent', 'Address the review', jsonb_build_object(
            'agentId', '{agent:issue.assignee}', 'session', 'new',
            'prompt', '{actor.name} reviewed the PR for ' || prompt_prefix || ' and assigned it back to you. Find the PR number in the issue''s comments (dev-tools/ai/ai issue {issue.issueNumber}), restart the timer with dev-tools/ai/ai timer {issue.issueNumber}, then run /address-review <repo> <pr> {issue.issueNumber}.'
        ), 800, 380),
        ('end', 'action', 'end', 'End', '{}', 1360, 220),
        ('feedback_comment', 'action', 'add_comment', 'Explain what is needed', jsonb_build_object(
            'body', '<p>The agent needs input on this issue.</p>', 'ownerId', '{issue.assignee}'), 1100, 60),
        ('feedback_status', 'action', 'set_status', 'Status Feedback', jsonb_build_object('status', 'feedback'), 1360, 60),
        ('feedback_assign', 'action', 'assign', 'Assign the sponsor', jsonb_build_object('assigneeId', '{sponsor:issue.assignee}'), 1620, 60),
        ('pending_comment', 'action', 'add_comment', 'Explain what is blocking', jsonb_build_object(
            'body', '<p>The agent could not finish this issue (blocked, failed or out of turns). See the run for details.</p>', 'ownerId', '{issue.assignee}'), 1100, 380),
        ('pending_status', 'action', 'set_status', 'Status Pending', jsonb_build_object('status', 'pending'), 1360, 380),
        ('pending_assign', 'action', 'assign', 'Assign the sponsor', jsonb_build_object('assigneeId', '{sponsor:issue.assignee}'), 1620, 380);

    INSERT INTO seed_edges VALUES
        ('trigger', 'next', 'filter'),
        ('filter', 'pass', 'status'),
        ('status', 'new', 'run_new'),
        ('status', 'resume', 'run_resume'),
        ('status', 'in_review', 'run_review'),
        ('feedback_comment', 'next', 'feedback_status'),
        ('feedback_status', 'next', 'feedback_assign'),
        ('pending_comment', 'next', 'pending_status'),
        ('pending_status', 'next', 'pending_assign');

    INSERT INTO seed_edges
    SELECT run.key, outcome.port, outcome.target
    FROM (VALUES ('run_new'), ('run_resume'), ('run_review')) AS run(key)
    CROSS JOIN (VALUES
        ('done', 'end'),
        ('needs_input', 'feedback_comment'),
        ('blocked', 'pending_comment'),
        ('failed', 'pending_comment'),
        ('limit', 'pending_comment')
    ) AS outcome(port, target);

    INSERT INTO workflows (id, name, description, status, scope, version, "ownerId", "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "dateCreated", "dateModified", deleted)
    VALUES (wf_id, wf_name,
            'Runs a coding agent when an issue is assigned to it by its sponsor, and hands the issue back to the sponsor when the agent needs input or cannot finish.',
            'Draft', project_ids::text, 0, NULL, '1', 'Hammad Hassan', '1', 'Hammad Hassan', NOW(), NOW(), FALSE);

    INSERT INTO workflow_nodes (id, "workflowId", category, kind, config, name, "positionX", "positionY", "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "dateCreated", "dateModified", deleted)
    SELECT md5(wf_id || ':node:' || key)::uuid::text, wf_id, category, kind, config::text, name, x, y,
           '1', 'Hammad Hassan', '1', 'Hammad Hassan', NOW(), NOW(), FALSE
    FROM seed_nodes;

    INSERT INTO workflow_edges (id, "workflowId", "fromNodeId", "fromPort", "toNodeId", "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "dateCreated", "dateModified", deleted)
    SELECT md5(wf_id || ':edge:' || from_key || ':' || port || ':' || to_key)::uuid::text, wf_id,
           md5(wf_id || ':node:' || from_key)::uuid::text, port, md5(wf_id || ':node:' || to_key)::uuid::text,
           '1', 'Hammad Hassan', '1', 'Hammad Hassan', NOW(), NOW(), FALSE
    FROM seed_edges;

    -- No workflow_references rows: the nodes name their agent and users by expression, and references
    -- only record users and agents named by id.

    DROP TABLE seed_edges;
    DROP TABLE seed_nodes;
END
$seed$;
