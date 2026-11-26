<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;

/**
 * Checks the relationships a request names: every one in `rels` (or `include`) is a relationship of the queried
 * model, so the ACL and the query only ever look up known names, and every one its `query` filters on is in `rels`,
 * so no filter reaches a relationship the query does not join.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category RelationshipValidator
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RelationshipValidator
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
     * Throw a BadRequest naming the first relationship the model does not have.
     *
     * @param  array  $rels       The requested relationship names.
     * @param  string $modelAlias Alias of the queried model.
     * @return void
     * @throws BadRequest
     */
    public function assertKnown(array $rels, $modelAlias)
    {
        if (empty($rels)) {
            return;
        }

        $metaManager = $this->di->get('metaManager');
        foreach ($rels as $rel) {
            if (empty($metaManager->getRelationshipMeta($modelAlias, $rel, false))) {
                throw new BadRequest('Unknown relationship "' . $rel . '" in rels.');
            }
        }
    }

    /**
     * Throw a BadRequest naming the first relationship the query filters on that `rels` does not request.
     * Gaia joins only the requested relationships (all of them when `rels` is empty), so a filter on any other
     * one would reach an unjoined alias. An alias that is no relationship of the model is left to
     * WhereFieldValidator, which rejects it when the query is prepared.
     *
     * @param  string $where      The request's `query`.
     * @param  array  $rels       The requested relationship names; empty joins them all.
     * @param  string $modelAlias Alias of the queried model.
     * @return void
     * @throws BadRequest
     */
    public function assertQueriedAreRequested($where, array $rels, $modelAlias)
    {
        if (empty($rels) || trim((string) $where) === '') {
            return;
        }

        $metaManager = $this->di->get('metaManager');
        foreach ((new WhereTokenizer())->leaves($where) as $leaf) {
            $alias = $this->aliasOf($leaf['text']);
            if ($alias === null || $alias === $modelAlias || in_array($alias, $rels, true)) {
                continue;
            }

            if (!empty($metaManager->getRelationshipMeta($modelAlias, $alias, false))) {
                throw new BadRequest(
                    'Invalid query: relationship "' . $alias . '" is used in the query but not requested in rels.'
                );
            }
        }
    }

    /**
     * The alias a leaf's field is qualified with (`project` in `(project.name : x)`); null for a bare field.
     *
     * @param  string $leaf A where leaf, parentheses included.
     * @return string|null
     */
    private function aliasOf($leaf)
    {
        $field = strtok(ltrim(substr($leaf, 1)), " \t\r\n");
        if ($field === false || strpos($field, '.') === false) {
            return null;
        }

        return strstr($field, '.', true);
    }
}
