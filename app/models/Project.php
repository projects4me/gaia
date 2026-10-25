<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Project Model
 *
 * Also owns Project-group record-scope helpers used by Acl: membership project
 * ids, groupKeys bindings, PHQL predicates, and resolving a row's project id.
 * Acl remains the policy orchestrator (when to filter / deny).
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Project extends Model
{
    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     *
     * @var bool
     */
    public $splitQueries = false;

    /**
     * Project ids the user belongs to via memberships.
     *
     * @param  string $userId
     * @return array
     */
    public static function accessibleIdsForUser($userId)
    {
        $userId = (string) $userId;
        if ($userId === '') {
            return [];
        }

        $ids = [];
        $memberships = Membership::find([
            'conditions' => 'userId = :userId:',
            'bind' => ['userId' => $userId],
            'columns' => 'projectId',
        ]);

        if ($memberships) {
            foreach ($memberships as $membership) {
                $projectId = (string) $membership->projectId;
                if ($projectId !== '') {
                    $ids[] = $projectId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Normalize Project groupKeys metadata into a binding array.
     *
     * @param  mixed $groupKey string field name or paths array
     * @return array
     */
    public static function normalizeGroupKeyBinding($groupKey)
    {
        if (is_array($groupKey)) {
            return $groupKey;
        }

        $field = ($groupKey === null || $groupKey === '')
            ? 'projectId'
            : (string) $groupKey;

        return ['field' => $field];
    }

    /**
     * Binding for the Project group model itself (rows are projects).
     *
     * @return array
     */
    public static function selfBinding()
    {
        return ['field' => 'id'];
    }

    /**
     * Build a PHQL fragment restricting rows to accessible projects.
     *
     * @param  string $modelName
     * @param  array  $binding
     * @param  array  $projectIds
     * @return string
     */
    public static function buildAclWhere($modelName, array $binding, array $projectIds)
    {
        if (empty($projectIds)) {
            return '1 = 0';
        }

        $inList = self::quoteIdList($projectIds);

        if (!empty($binding['paths']) && is_array($binding['paths'])) {
            return self::buildPathAclWhere($modelName, $binding['paths'], $inList);
        }

        $field = isset($binding['field']) ? (string) $binding['field'] : 'projectId';
        return "{$modelName}.{$field} IN ({$inList})";
    }

    /**
     * Resolve the project id that owns a record using groupKeys binding.
     *
     * @param  string $modelName
     * @param  string $recordId
     * @param  array  $binding
     * @return string|null
     */
    public static function resolveProjectIdForRecord($modelName, $recordId, array $binding)
    {
        $modelClass = '\\Gaia\\MVC\\Models\\' . $modelName;
        if (!class_exists($modelClass)) {
            return null;
        }

        $record = $modelClass::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $recordId],
        ]);
        if (!$record) {
            return null;
        }

        if (!empty($binding['paths']) && is_array($binding['paths'])) {
            $path = self::matchPath($binding['paths'], $record->toArray());
            if ($path === null) {
                return null;
            }
            $localKey = isset($path['localKey']) ? (string) $path['localKey'] : 'relatedId';
            return self::resolveProjectIdThroughPath($path, $record->{$localKey});
        }

        $field = isset($binding['field']) ? (string) $binding['field'] : 'projectId';
        if ($field === 'id' && $modelName === 'Project') {
            return (string) $record->id;
        }

        return isset($record->{$field}) ? (string) $record->{$field} : null;
    }

    /**
     * Resolve the project a write payload points at through a paths binding,
     * e.g. the idea an idea message is created for.
     *
     * Returns false when the payload does not name the parent (nothing to
     * check), null when the parent is unknown, else the project id.
     *
     * @param  array $binding
     * @param  array $attributes
     * @return string|null|false
     */
    public static function resolveProjectIdForAttributes(array $binding, array $attributes)
    {
        if (empty($binding['paths']) || !is_array($binding['paths'])) {
            return false;
        }

        $path = self::matchPath($binding['paths'], $attributes);
        if ($path === null) {
            return false;
        }

        $localKey = isset($path['localKey']) ? (string) $path['localKey'] : 'relatedId';
        if (!array_key_exists($localKey, $attributes)) {
            return false;
        }

        return self::resolveProjectIdThroughPath($path, $attributes[$localKey]);
    }

    /**
     * Project id of the record a reference field points at, e.g. the issue
     * of Ideaplanissue.issueId. Declared per model in metadata as
     * `'acl' => ['references' => ['issueId' => ['relatedModel' => 'Issue', 'projectField' => 'projectId']]]`.
     *
     * @param  array $reference
     * @param  mixed $value
     * @return string|null Null when the reference is malformed or the record is missing
     */
    public static function resolveProjectIdForReference(array $reference, $value)
    {
        if (empty($reference['relatedModel']) || empty($reference['projectField'])) {
            return null;
        }

        return self::resolveProjectIdThroughPath($reference, $value);
    }

    /**
     * First path whose `when` conditions hold for the values. A path without
     * `when` always applies (single-parent models such as Ideamessage -> Idea),
     * so it must be the model's only path or come last.
     *
     * @param  array $paths
     * @param  array $values
     * @return array|null
     */
    protected static function matchPath(array $paths, array $values)
    {
        foreach ($paths as $path) {
            if (empty($path['relatedModel']) || empty($path['projectField'])) {
                continue;
            }
            if (empty($path['when']) || !is_array($path['when'])) {
                return $path;
            }
            foreach ($path['when'] as $whenField => $whenValue) {
                $value = isset($values[$whenField]) ? (string) $values[$whenField] : null;
                if ($value !== (string) $whenValue) {
                    continue 2;
                }
            }
            return $path;
        }

        return null;
    }

    /**
     * Load the related record a path points at and read its project id.
     *
     * @param  array  $path
     * @param  mixed  $localValue
     * @return string|null
     */
    protected static function resolveProjectIdThroughPath(array $path, $localValue)
    {
        $relatedKey = isset($path['relatedKey']) ? (string) $path['relatedKey'] : 'id';
        $projectField = (string) $path['projectField'];
        $relatedClass = '\\Gaia\\MVC\\Models\\' . (string) $path['relatedModel'];
        if (!class_exists($relatedClass)) {
            return null;
        }
        $related = $relatedClass::findFirst([
            'conditions' => "{$relatedKey} = :rid:",
            'bind' => ['rid' => (string) $localValue],
        ]);
        return $related ? (string) $related->{$projectField} : null;
    }

    /**
     * @param  string $modelName
     * @param  array  $paths
     * @param  string $inList
     * @return string
     */
    protected static function buildPathAclWhere($modelName, array $paths, $inList)
    {
        $parts = [];
        foreach ($paths as $path) {
            if (empty($path['relatedModel']) || empty($path['projectField'])) {
                continue;
            }
            $relatedModel = (string) $path['relatedModel'];
            $localKey = isset($path['localKey']) ? (string) $path['localKey'] : 'relatedId';
            $relatedKey = isset($path['relatedKey']) ? (string) $path['relatedKey'] : 'id';
            $projectField = (string) $path['projectField'];

            $whenParts = [];
            if (!empty($path['when']) && is_array($path['when'])) {
                foreach ($path['when'] as $whenField => $whenValue) {
                    $whenParts[] = "{$modelName}.{$whenField} = '"
                        . addslashes((string) $whenValue) . "'";
                }
            }
            $whenSql = empty($whenParts) ? '1 = 1' : implode(' AND ', $whenParts);

            // relatedId already stores the project id (e.g. Activity -> project).
            if ($projectField === $relatedKey) {
                $parts[] = "({$whenSql} AND {$modelName}.{$localKey} IN ({$inList}))";
                continue;
            }

            // PHQL requires FQCN in subqueries; bare "FROM Issue" cannot be loaded.
            $relatedFqcn = 'Gaia\\MVC\\Models\\' . $relatedModel;
            $parts[] = "({$whenSql} AND {$modelName}.{$localKey} IN ("
                . "SELECT {$relatedModel}.{$relatedKey} FROM [{$relatedFqcn}] AS {$relatedModel} "
                . "WHERE {$relatedModel}.{$projectField} IN ({$inList})"
                . "))";
        }

        if (empty($parts)) {
            return '1 = 0';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * @param  array $ids
     * @return string
     */
    protected static function quoteIdList(array $ids)
    {
        $quoted = [];
        foreach ($ids as $id) {
            $quoted[] = "'" . addslashes((string) $id) . "'";
        }
        return implode(',', $quoted);
    }
}
