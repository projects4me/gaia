<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Security\Acl;

/**
 * The records of a model a user may find: not deleted and inside the module's record scope (Acl::applyRecordScope,
 * by acl.groupKeys, D-060). The scope is built as a PHQL query, the way the REST list applies it, and compiled to
 * the SQL subquery the candidate queries stay inside.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchScope
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var \Phalcon\Mvc\Model\ManagerInterface
     */
    private $modelsManager;

    /**
     * @param Acl $acl The ACL of the user searching
     * @param \Phalcon\Mvc\Model\ManagerInterface $modelsManager
     */
    public function __construct(Acl $acl, $modelsManager)
    {
        $this->acl = $acl;
        $this->modelsManager = $modelsManager;
    }

    /**
     * This function returns the SELECT of the ids of a model's records in scope, with its named parameters.
     *
     * @param string $model
     * @param array $fields The model's field schemas, keyed by field name
     * @return array ['sql' => string, 'bind' => array]
     */
    public function forModel($model, array $fields)
    {
        $params = array();
        $this->acl->applyRecordScope($model, $params);

        $builder = $this->modelsManager->createBuilder()
            ->columns(array($model . '.id'))
            ->from(array($model => 'Gaia\\MVC\\Models\\' . $model));
        if (isset($fields['deleted'])) {
            $builder->where($model . '.deleted = 0');
        }
        if (!empty($params['aclWhere'])) {
            $aclBind = isset($params['aclBind']) && is_array($params['aclBind']) ? $params['aclBind'] : array();
            $builder->andWhere($params['aclWhere'], $aclBind);
        }

        $sql = $builder->getQuery()->getSql();
        $bind = isset($sql['bind']) ? $sql['bind'] : array();
        foreach ($bind as $name => $value) {
            // The SQL runs without PHQL's bind types, so an array or typed bind would not expand or cast.
            if (!is_scalar($value) && $value !== null) {
                throw new \Gaia\Exception\Exception("The record scope of {$model} has a non-scalar parameter '{$name}'.");
            }
        }

        return array('sql' => $sql['sql'], 'bind' => $bind);
    }
}
