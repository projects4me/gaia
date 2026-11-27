<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * Turns a where leaf on a hasManyToMany relationship into a test on the queried record's id, so the filter needs
 * no join: `(tag.tag : x)` on Issue becomes "Issue.id is among the records tagged with a tag named x". Each leaf
 * gets its own subquery, so `(tag.tag : a) AND (tag.tag : b)` means "has tag a and has tag b", and a record with
 * several matching related rows is still one row of the result (Tags D-002).
 *
 * Only relationships the subquery can express are rewritten: no exclusive join conditions, and a `condition` that
 * reads the junction alias alone. Any other leaf is returned unchanged and stays a filter on the joined rows.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category ManyToManyFilter
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ManyToManyFilter
{
    /**
     * @var \Phalcon\Di\FactoryDefault
     */
    protected $di;

    /**
     * @param \Phalcon\Di\FactoryDefault $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * Whether a relationship of the model is a hasManyToMany one a filter subquery can express.
     *
     * @param  string $modelAlias Alias of the queried model.
     * @param  string $relName    Name of the relationship.
     * @return bool
     */
    public function supports($modelAlias, $relName)
    {
        if (!$this->di->has('metaManager')) {
            return false;
        }

        $relMeta = $this->di->get('metaManager')->getRelationshipMeta($modelAlias, $relName, false);

        return $this->supportsMeta($relMeta, $relName);
    }

    /**
     * Rewrite a translated leaf on a supported hasManyToMany relationship into a subquery on the record's id;
     * any other leaf comes back unchanged.
     *
     * @param  string $modelAlias Alias of the queried model.
     * @param  string $relName    The alias the leaf's field is qualified with.
     * @param  string $leaf       The translated leaf, e.g. `(tag.tag = :q0:)`.
     * @return string
     */
    public function rewrite($modelAlias, $relName, $leaf)
    {
        if ($relName === $modelAlias || !$this->di->has('metaManager')) {
            return $leaf;
        }

        $relMeta = $this->di->get('metaManager')->getRelationshipMeta($modelAlias, $relName, false);
        if (!$this->supportsMeta($relMeta, $relName)) {
            return $leaf;
        }

        $junction = $relName . $this->aliasOf($relMeta['relatedModel']);
        $matching = 'SELECT ' . $relName . '.' . $relMeta['secondaryKey']
            . ' FROM ' . $this->phqlModel($relMeta['secondaryModel']) . ' ' . $relName
            . ' WHERE ' . $leaf;
        $where = $junction . '.' . $relMeta['lhsKey'] . ' IN (' . $matching . ')';
        if (!empty($relMeta['condition'])) {
            $where = '(' . $relMeta['condition'] . ') AND ' . $where;
        }

        return '(' . $modelAlias . '.' . $relMeta['primaryKey'] . ' IN (SELECT ' . $junction . '.' . $relMeta['rhsKey']
            . ' FROM ' . $this->phqlModel($relMeta['relatedModel']) . ' ' . $junction
            . ' WHERE ' . $where . '))';
    }

    /**
     * @param  array  $relMeta Relationship metadata with its `type` (empty when the model has no such relationship).
     * @param  string $relName Name of the relationship.
     * @return bool
     */
    protected function supportsMeta($relMeta, $relName)
    {
        if (empty($relMeta) || ($relMeta['type'] ?? '') !== 'hasManyToMany') {
            return false;
        }

        if (isset($relMeta['rhsConditionExclusive']) || isset($relMeta['lhsConditionExclusive'])) {
            return false;
        }

        if (empty($relMeta['condition'])) {
            return true;
        }

        // The subquery has the junction alone in scope, so the condition may read nothing else.
        $junction = $relName . $this->aliasOf($relMeta['relatedModel']);
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*\./', $relMeta['condition'], $aliases);

        return array_diff(array_unique($aliases[1]), [$junction]) === [];
    }

    /**
     * The model name a PHQL FROM takes: the namespace without its leading backslash.
     *
     * @param  string $namespace
     * @return string
     */
    protected function phqlModel($namespace)
    {
        return ltrim($namespace, '\\');
    }

    /**
     * The class name of a model namespace, e.g. Tagged for \Gaia\MVC\Models\Tagged.
     *
     * @param  string $namespace
     * @return string
     */
    protected function aliasOf($namespace)
    {
        $parts = explode('\\', $namespace);

        return end($parts);
    }
}
