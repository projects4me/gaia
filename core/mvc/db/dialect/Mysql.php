<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Dialect;

/**
 * This class is responsible to create sql expressions that are used by
 * MYSQL RDBMS.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
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
                    {$schema['statement']}";
    }

    /**
     * This function returns sql to check whether trigger exists or not.
     *
     * @return string
     */
    public function showTrigger($tableName)
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
        return "CREATE OR REPLACE VIEW {$tableName} AS {$viewSql}";
    }

    /**
     * This function returns sql to check whether function exists or not.
     *
     * @return string
     */
    public function showFunction($functionName)
    {
        return "SHOW FUNCTION STATUS where name = '{$functionName}'";
    }    

    /**
     * This function returns sql to create function.
     *
     * @return string
     */
    public function createFunction($functionName, $parameters, $returnType, $statement)
    {
        return "CREATE FUNCTION {$functionName}($parameters)
                RETURNS {$returnType} DETERMINISTIC
                {$statement}";
    }    
}