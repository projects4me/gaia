<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;

/**
 * Checks that every relationship named in a request's `rels` (or `include`) is a relationship of the queried model,
 * so the ACL and the query only ever look up known names.
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
}
