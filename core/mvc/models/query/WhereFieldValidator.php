<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;

/**
 * Checks that a field named in a query leaf is a plain identifier (`field` or `alias.field`) that names a field of
 * the queried model or of one of its relationships, so nothing but a known field name reaches the PHQL.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category WhereFieldValidator
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WhereFieldValidator
{
    /**
     * `field` or `alias.field`, identifier characters only.
     */
    const FIELD_PATTERN = '/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/';

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
     * Throw a BadRequest unless $field is a well-formed name of a known field.
     *
     * @param string $field      Field as written in the query, e.g. `Issue.subject` or `project.name`.
     * @param string $modelAlias Alias of the queried model.
     * @return void
     * @throws BadRequest
     */
    public function assertValid($field, $modelAlias)
    {
        if (!is_string($field) || !preg_match(self::FIELD_PATTERN, $field)) {
            throw new BadRequest('Invalid query: "' . $field . '" is not a valid field name.');
        }

        if (strpos($field, '.') !== false) {
            list($alias, $fieldName) = explode('.', $field, 2);
        } else {
            $alias = $modelAlias;
            $fieldName = $field;
        }

        $fields = $this->fieldsOf($alias, $modelAlias);
        if ($fields === null) {
            throw new BadRequest('Invalid query: unknown model or relationship "' . $alias . '" in "' . $field . '".');
        }

        // A queried model without field metadata (e.g. a route with no metadata file) can only be checked
        // for the identifier syntax above; its leaves still reach the PHQL only as bound values.
        if (empty($fields)) {
            return;
        }

        if (!array_key_exists($fieldName, $fields)) {
            throw new BadRequest('Invalid query: unknown field "' . $field . '".');
        }
    }

    /**
     * Field metadata of the model an alias points at: the queried model itself or one of its relationships.
     * Null when the alias names neither.
     *
     * @param string $alias
     * @param string $modelAlias
     * @return array|null
     */
    protected function fieldsOf($alias, $modelAlias)
    {
        $metaManager = $this->di->get('metaManager');

        if ($alias === $modelAlias) {
            $meta = $metaManager->getModelMeta($modelAlias);
            return $meta['fields'] ?? [];
        }

        if (empty($metaManager->getRelationshipMeta($modelAlias, $alias, false))) {
            return null;
        }

        $meta = $metaManager->getModelMeta($metaManager->getRelatedModelName($modelAlias, $alias));

        // A relationship whose model has no field metadata cannot be checked, so it is not accepted.
        return empty($meta['fields']) ? null : $meta['fields'];
    }
}
