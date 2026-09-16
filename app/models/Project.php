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
            foreach ($binding['paths'] as $path) {
                if (empty($path['when']) || !is_array($path['when'])) {
                    continue;
                }
                $matches = true;
                foreach ($path['when'] as $whenField => $whenValue) {
                    if ((string) $record->{$whenField} !== (string) $whenValue) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }

                $relatedModel = (string) $path['relatedModel'];
                $localKey = isset($path['localKey']) ? (string) $path['localKey'] : 'relatedId';
                $relatedKey = isset($path['relatedKey']) ? (string) $path['relatedKey'] : 'id';
                $projectField = (string) $path['projectField'];
                $relatedClass = '\\Gaia\\MVC\\Models\\' . $relatedModel;
                if (!class_exists($relatedClass)) {
                    return null;
                }
                $related = $relatedClass::findFirst([
                    'conditions' => "{$relatedKey} = :rid:",
                    'bind' => ['rid' => $record->{$localKey}],
                ]);
                return $related ? (string) $related->{$projectField} : null;
            }
            return null;
        }

        $field = isset($binding['field']) ? (string) $binding['field'] : 'projectId';
        if ($field === 'id' && $modelName === 'Project') {
            return (string) $record->id;
        }

        return isset($record->{$field}) ? (string) $record->{$field} : null;
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
