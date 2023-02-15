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
 * @package Foundation\Core\MVC\Models\Relationships
 * @category HasManyToMany
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class HasManyToMany
{
    /**
     * This contains all fields that are related to hasManyToMany relationship.
     * 
     * @var array
     */
    protected $fields = [];

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
     * @param \Phalcon\Mvc\Model\Query\Builder $queryBuilder
     */
    public function prepareJoin($relationshipName, $relationshipMeta, $modelAlias, $joinType, $queryBuilder)
    {
        // for a many-many relationshipMeta two joins are required
        $relatedModel = $relationshipMeta['relatedModel'];
        $relatedModelAlias = Util::extractClassFromNamespace($relatedModel);
        $secondaryModel = $relationshipMeta['secondaryModel'];

        //If an exclusive condition for related model is defined then use that
        if (isset($relationshipMeta['rhsConditionExclusive'])) {
            $relatedQuery = $relationshipMeta['rhsConditionExclusive'];
        }
        else {
            $relatedQuery = $modelAlias . '.' . $relationshipMeta['primaryKey'] .
                ' = ' . $relationshipName . $relatedModelAlias . '.' . $relationshipMeta['rhsKey'];
        }

        //If an exclusive condition for secondary model is defined then use that
        if (isset($relationshipMeta['lhsConditionExclusive'])) {
            $secondaryQuery = $relationshipMeta['lhsConditionExclusive'];
        }
        else {
            $secondaryQuery = $relationshipName . '.' . $relationshipMeta['secondaryKey'] .
                ' = ' . $relationshipName . $relatedModelAlias . '.' . $relationshipMeta['lhsKey'];
        }

        // if a condition is set in the metadata then use it
        if (isset($relationshipMeta['condition'])) {
            $relatedQuery .= ' AND (' . $relationshipMeta['condition'] . ')';
        }

        $queryBuilder->join($relatedModel, $relatedQuery, $relationshipName . $relatedModelAlias, $joinType);
        $queryBuilder->join($secondaryModel, $secondaryQuery, $relationshipName, $joinType);
    }

    /**
     * This function prepares where condition.
     * 
     * @param array $relMeta
     * @param array $whereClauses
     * @return string
     */
    public function prepareWhere($relMeta, $whereClauses)
    {
        $whereClause = '';
        foreach ($whereClauses as $where) {
            $regex = "/(?<=[(])[A-z]+/";
            preg_match($regex, $where, $relName);

            //regex for extracting field by removing '(' ')' brackets
            $regex = '/(?<=[(])[A-z]+[.][A-z]+/';
            preg_match($regex, $where, $field);

            $newRelField = $this->changeAliasOfRel($relMeta, $field[0]);
            $updatedWhere = str_replace($field[0], $newRelField, $where);
            if ($whereClause) {
                $whereClause .= ` AND {$updatedWhere}`;
            }
            else {
                $whereClause = $updatedWhere;
            }
        }
        return $whereClause;
    }

    /**
     * This function returns fields related to given hasManyToMany relationship.
     * 
     * @param string $relName Name of the relationship.
     * @return array
     */
    public function getFields($relName)
    {
        $fields = [];
        if (isset($this->fields[$relName])) {
            $fields = $this->fields[$relName];
        }
        return $fields;
    }

    /**
     * This function set all fields related to hasManyToMany relationship.
     * 
     * @param array $parameters Relationship parameters.
     * @param string $relName Name of the relationship.
     * @param array $relMeta Relationship metadata.
     * @param array $hasManyToManyRelationships
     */
    public function setFields(&$parameters, $relName, $relMeta, $hasManyToManyRelationships)
    {
        $relatedModelName = Util::extractClassFromNamespace($relMeta['relatedModel']);

        foreach ($parameters['fields'] as $field) {
            list($relName, $fieldName) = explode('.', $field);

            if (in_array($relName, $hasManyToManyRelationships)) {

                $index = array_search($field, $parameters['fields']);
                unset($parameters['fields'][$index]);

                $relatedField = $this->changeAliasOfRel($relMeta, $relName);

                $this->fields[$relName][] = $relatedField;
            }

            if (!empty($this->fields[$relName])) {
                $relatedModelRhsField = "{$relatedModelName}.{$relMeta['rhsKey']}";
                $relatedModelLhsField = "{$relatedModelName}.{$relMeta['lhsKey']}";
                array_push($this->fields[$relName], $relatedModelRhsField, $relatedModelLhsField);
            }
        }
    }

    /**
     * This function change alias of relationship from a given query. 
     * e.g "skills.name : Emberjs" query for User model is requested so, it will change skills.name to Tag.name.
     * 
     * @param array $relMeta Metadata of relationship.
     * @param string $field Field of relationship.
     * 
     */
    public function changeAliasOfRel($relMeta, $field)
    {
        $fieldParts = explode('.', $field);
        return Util::extractClassFromNamespace($relMeta['secondaryModel']) . '.' . $fieldParts[1];
    }
}
