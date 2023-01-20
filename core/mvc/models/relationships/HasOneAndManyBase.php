<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Relationships;

/**
 * This class is base for hasOne, HasMany and belongsTo relationships.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Core\Mvc\Models\Relationships
 * @category HasOneAndManyBase
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class HasOneAndManyBase
{
    /**
     * This function prepares join based on relationship meta and set that join in query builder.
     *
     * @param string $relationshipName
     * @param array $relationshipMeta
     * @param string $modelAlias
     * @param string $joinType
     */
    public function prepareJoin($relationshipName, $relationshipMeta, $modelAlias, $joinType)
    {
        $relatedModel = $relationshipMeta['relatedModel'];

        // If an exclusive condition is defined then use that
        if (isset($relationshipMeta['conditionExclusive'])) {
            $relatedQuery = $relationshipMeta['conditionExclusive'];
        }
        else {
            $relatedQuery = $modelAlias . '.' . $relationshipMeta['primaryKey'] .
                ' = ' . $relationshipName . '.' . $relationshipMeta['relatedKey'];
        }

        // if a condition is set in the metadata then use it
        if (isset($relationshipMeta['condition'])) {
            $relatedQuery .= ' AND ' . $relationshipMeta['condition'];
        }
        // for each relationship apply the relationship joins to phalcon query object
        $queryBuilder = $this->di->get('currentQueryBuilder');
        $queryBuilder->join($relatedModel, $relatedQuery, $relationshipName, $joinType);
    }
}