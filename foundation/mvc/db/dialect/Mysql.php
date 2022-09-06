<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Dialect;

/**
 * This class is responsible to create sql expressions that are used by
 * MYSQL RDBMS.
 *
 * @author Rana Nouman <ranamnouman@yahoo.com>
 * @package Foundation\Mvc\Db\Dialect
 * @category Mysql
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Mysql
{
    /**
     * This function returns sql to create trigger.
     *
     * @return string
     */
    public function createTrigger($tableName, $schema)
    {
        return "CREATE TRIGGER {$schema['triggerName']} 
                {$schema['eventType']} on {$tableName}
                FOR EACH ROW 
                    SET {$schema['statement']}";
    }

    /**
     * This function returns sql to list triggers.
     *
     * @return string
     */
    public function listTriggers($tableName)
    {
        return "SHOW TRIGGERS like '{$tableName}'";
    }

    /**
     * This function returns sql to create sql view.
     *
     * @return string
     */
    public function createView($tableName, $viewSql)
    {
        return "CREATE OR REPLACE VIEW {$tableName} AS {$viewSql};";
    }
}