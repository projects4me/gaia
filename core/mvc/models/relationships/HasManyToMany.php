<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Relationships;

use Gaia\Libraries\Utils\Util;

/**
 * This class represents HasManyToMany relationship.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Core\Mvc\Models\Relationships
 * @category HasManyToMany
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class HasManyToMany
{
    /**
     * HasManyToMany constructor.
     *
     * @param \Phalcon\DiInterface  $di
     */
    function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This function prepares joins based on relationship meta and set that joins in query builder.
     *
     * @param string $relationshipName
     * @param array $relationshipMeta
     * @param string $modelAlias
     * @param string $joinType
     */
    public function prepareJoin($relationshipName, $relationshipMeta, $modelAlias, $joinType)
    {
        // for a many-many relationshipMeta two joins are required
        $relatedModel = $relationshipMeta['relatedModel'];
        $relatedModelAlias = Util::extractClassFromNamespace($relatedModel);
        $secondaryModel = $relationshipMeta['secondaryModel'];

        //If an exclusive condition for related model is defined then use that
        if (isset($relationshipMeta['rhsConditionExclusive'])) {
            $relatedQuery = $relationshipMeta['rhsConditionExclusive'];
        } else {
            $relatedQuery = $modelAlias . '.' . $relationshipMeta['primaryKey'] .
                ' = ' . $relationshipName . $relatedModelAlias . '.' . $relationshipMeta['rhsKey'];
        }

        //If an exclusive condition for secondary model is defined then use that
        if (isset($relationshipMeta['lhsConditionExclusive'])) {
            $secondaryQuery = $relationshipMeta['lhsConditionExclusive'];
        } else {
            $secondaryQuery = $relationshipName . '.' . $relationshipMeta['secondaryKey'] .
                ' = ' . $relationshipName . $relatedModelAlias . '.' . $relationshipMeta['lhsKey'];
        }

        // if a condition is set in the metadata then use it
        if (isset($relationshipMeta['condition'])) {
            $relatedQuery .= ' AND (' . $relationshipMeta['condition'] . ')';
        }

        $queryBuilder = $this->di->get('currentQueryBuilder');
        $queryBuilder->join($relatedModel, $relatedQuery, $relationshipName . $relatedModelAlias, $joinType);
        $queryBuilder->join($secondaryModel, $secondaryQuery, $relationshipName, $joinType);
    }
}
