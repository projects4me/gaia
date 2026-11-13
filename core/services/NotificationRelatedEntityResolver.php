<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Services;

/**
 * Works out the relatedTo/relatedId pair of a stored system notification from its description
 * and context, using the same mapping the notification modules apply when they create one
 * (app/api/v1/controllers/components/notifications/modules):
 * - issue, comment and timelog notifications relate to the issue;
 * - milestone notifications relate to the milestone;
 * - membership notifications (context.relatedTo = project) relate to the project.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Services
 * @category Notifications
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationRelatedEntityResolver
{
    /**
     * context.relatedTo values whose notifications relate to an issue.
     *
     * @var string[]
     */
    private const ISSUE_KINDS = ['issue', 'comment', 'timelog'];

    /**
     * @var NotificationRelatedRecordFinder
     */
    protected $finder;

    /**
     * @param NotificationRelatedRecordFinder $finder Looks up ids the context names only by short code
     */
    public function __construct(NotificationRelatedRecordFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Resolve the related entity of a notification.
     *
     * @param  string      $description The notification description, e.g. "... {{Issue@<id>}} ..."
     * @param  string|null $context     The notification context as stored (JSON)
     * @return array|null ['relatedTo' => string, 'relatedId' => string], or null when it can't be resolved
     */
    public function resolve($description, $context)
    {
        $context = json_decode((string) $context, true);
        if (!is_array($context) || empty($context['relatedTo'])) {
            return null;
        }

        $kind = $context['relatedTo'];
        if (in_array($kind, self::ISSUE_KINDS, true)) {
            return $this->related('issue', $this->resolveIssueId($description, $context));
        }
        if ($kind === 'milestone') {
            return $this->related('milestone', $this->resolveMilestoneId($description, $context));
        }
        if ($kind === 'project') {
            return $this->related('project', $this->resolveProjectId($description, $context));
        }

        return null;
    }

    /**
     * @param  string $description
     * @param  array  $context
     * @return string|null
     */
    protected function resolveIssueId($description, array $context)
    {
        $id = $this->tagId('Issue', $description);
        if ($id === null && !empty($context['projectShortcode']) && !empty($context['issueNumber'])) {
            $id = $this->finder->findIssueId($context['projectShortcode'], $context['issueNumber']);
        }

        return $id;
    }

    /**
     * @param  string $description
     * @param  array  $context
     * @return string|null
     */
    protected function resolveMilestoneId($description, array $context)
    {
        if (!empty($context['milestoneId'])) {
            return (string) $context['milestoneId'];
        }

        return $this->tagId('Milestone', $description);
    }

    /**
     * @param  string $description
     * @param  array  $context
     * @return string|null
     */
    protected function resolveProjectId($description, array $context)
    {
        if (!empty($context['projectId'])) {
            return (string) $context['projectId'];
        }

        $id = $this->tagId('Project', $description);
        if ($id === null && !empty($context['projectShortcode'])) {
            $id = $this->finder->findProjectId($context['projectShortcode']);
        }

        return $id;
    }

    /**
     * Read the id from a {{<Model>@<id>}} tag in a description.
     *
     * @param  string $model
     * @param  string $description
     * @return string|null
     */
    protected function tagId($model, $description)
    {
        if (preg_match('/\{\{' . $model . '@([^}]+)\}\}/', (string) $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  string      $relatedTo
     * @param  string|null $relatedId
     * @return array|null
     */
    private function related($relatedTo, $relatedId)
    {
        // relatedId is varchar(36): anything longer isn't an id
        if ($relatedId === null || $relatedId === '' || strlen($relatedId) > 36) {
            return null;
        }

        return ['relatedTo' => $relatedTo, 'relatedId' => $relatedId];
    }
}
