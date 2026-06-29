<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Dialect;

/**
 * This class is responsible to create sql expressions that are used by
 * PostgreSQL RDBMS.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Dialect
 * @category Postgres
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Postgres
{
    /**
     * This function returns sql to create a PostgreSQL trigger and its backing function.
     *
     * @param string $tableName Table name from model metadata
     * @param array $schema Trigger schema from model metadata
     * @return string
     */
    public function createTrigger($tableName, $schema)
    {
        $triggerName = $schema['triggerName'];
        $functionName = $triggerName . '_fn';
        $eventType = trim($schema['eventType']);
        $statement = $this->translateTriggerStatement($schema['statement'], $eventType);
        $quotedTable = $this->quoteIdentifier($tableName);

        return "DROP TRIGGER IF EXISTS {$triggerName} ON {$quotedTable};
                DROP FUNCTION IF EXISTS {$functionName}();
                CREATE OR REPLACE FUNCTION {$functionName}() RETURNS trigger AS \$\$
                BEGIN
                    {$statement}
                END;
                \$\$ LANGUAGE plpgsql;
                CREATE TRIGGER {$triggerName}
                {$eventType} ON {$quotedTable}
                FOR EACH ROW
                EXECUTE FUNCTION {$functionName}();";
    }

    /**
     * This function returns sql to check whether a trigger exists on a table.
     *
     * @param string $tableName Table name from model metadata
     * @return string
     */
    public function showTrigger($tableName)
    {
        $quotedTable = $this->quoteValue($tableName);

        return "SELECT trigger_name AS \"Trigger\"
                FROM information_schema.triggers
                WHERE event_object_schema = 'public'
                AND event_object_table = {$quotedTable}";
    }

    /**
     * This function returns sql to create a PostgreSQL view.
     *
     * @param string $tableName View name from model metadata
     * @param string $viewSql View SQL from model metadata
     * @return string
     */
    public function createView($tableName, $viewSql)
    {
        return "CREATE OR REPLACE VIEW {$this->quoteIdentifier($tableName)} AS {$viewSql}";
    }

    /**
     * This function returns sql to check whether a function exists.
     *
     * @param string $functionName Function name from model metadata
     * @return string
     */
    public function showFunction($functionName)
    {
        $quotedName = $this->quoteValue(strtolower($functionName));

        return "SELECT p.proname AS \"Name\"
                FROM pg_proc p
                INNER JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE n.nspname = 'public'
                AND p.proname = {$quotedName}
                LIMIT 1";
    }

    /**
     * This function returns sql to create a PostgreSQL function from MySQL-oriented metadata.
     *
     * @param string $functionName Function name from model metadata
     * @param string $parameters Function parameter list from model metadata
     * @param string $returnType Function return type from model metadata
     * @param string $statement Function body from model metadata
     * @return string
     */
    public function createFunction($functionName, $parameters, $returnType, $statement)
    {
        $pgReturnType = $this->translateReturnType($returnType);
        $pgStatement = $this->translateFunctionStatement($statement);
        $params = trim((string) $parameters) === '' ? '' : $parameters;

        return "CREATE OR REPLACE FUNCTION {$functionName}({$params})
                RETURNS {$pgReturnType} AS \$\$
                BEGIN
                    {$pgStatement}
                END;
                \$\$ LANGUAGE plpgsql STABLE;";
    }

    /**
     * This function returns sql to get the current charset and collation of a column.
     *
     * @param string $tableName Table name from model metadata
     * @param string $columnName Column name from model metadata
     * @return string
     */
    public function getColumnCollation($tableName, $columnName)
    {
        return "SELECT
                    COALESCE(character_set_name, 'UTF8') AS \"CHARACTER_SET_NAME\",
                    collation_name AS \"COLLATION_NAME\"
                FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = {$this->quoteValue($tableName)}
                AND column_name = {$this->quoteValue($columnName)}";
    }

    /**
     * This function returns sql to alter a column collation.
     *
     * @param string $tableName Table name from model metadata
     * @param string $columnName Column name from model metadata
     * @param string $columnDefinition SQL column type definition
     * @param string $charset Target character set
     * @param string $collation Target collation
     * @return string
     */
    public function alterColumnCollation($tableName, $columnName, $columnDefinition, $charset, $collation)
    {
        $quotedTable = $this->quoteIdentifier($tableName);
        $quotedColumn = $this->quoteIdentifier($columnName);
        $quotedCollation = $this->quoteIdentifier($collation);

        return "ALTER TABLE {$quotedTable}
                ALTER COLUMN {$quotedColumn} TYPE {$columnDefinition}
                COLLATE {$quotedCollation}";
    }

    /**
     * This function returns sql to set the model identifier session variable.
     *
     * @param string $modelId Model identifier value
     * @return string
     */
    public function setModelIdentifier($modelId)
    {
        return "SELECT set_config('gaia.model_id', {$this->quoteValue($modelId)}, false)";
    }

    /**
     * This function returns sql to set the current user identifier session variable.
     *
     * @param string $userId Current user identifier value
     * @return string
     */
    public function setCurrentUserIdentifier($userId)
    {
        return "SELECT set_config('gaia.current_user_id', {$this->quoteValue($userId)}, false)";
    }

    /**
     * This function translates a MySQL-oriented trigger body into PostgreSQL plpgsql.
     *
     * @param string $statement Trigger body from model metadata
     * @param string $eventType Trigger event type from model metadata
     * @return string
     */
    private function translateTriggerStatement($statement, $eventType)
    {
        $statement = trim($statement);
        $statement = rtrim($statement, ';');
        $statement = str_replace('`', '"', $statement);
        $statement = preg_replace('/\bELSEIF\b/i', 'ELSIF', $statement);
        $statement = $this->replaceMysqlStringLiterals($statement);
        $statement = preg_replace_callback(
            '/\bSET\s+NEW\.([a-zA-Z_][a-zA-Z0-9_]*)\s*=/i',
            function (array $matches) {
                return 'NEW.' . $this->quoteColumnReference($matches[1]) . ' :=';
            },
            $statement
        );
        $statement = preg_replace_callback(
            '/\bNEW\.([a-zA-Z_][a-zA-Z0-9_]*)\b/',
            function (array $matches) {
                return 'NEW.' . $this->quoteColumnReference($matches[1]);
            },
            $statement
        );
        $statement = preg_replace_callback(
            '/\bOLD\.([a-zA-Z_][a-zA-Z0-9_]*)\b/',
            function (array $matches) {
                return 'OLD.' . $this->quoteColumnReference($matches[1]);
            },
            $statement
        );
        $statement = $this->stripUpdateTableAliases($statement);
        $statement = $this->stripTableAliasReferences($statement);
        $statement = $this->quoteMixedCaseIdentifiers($statement);

        if (!preg_match('/\bRETURN\b/i', $statement)) {
            if (stripos($eventType, 'AFTER') === 0) {
                $statement .= ";\nRETURN NULL;";
            } else {
                $statement .= ";\nRETURN NEW;";
            }
        }

        return $statement;
    }

    /**
     * This function translates a MySQL function return type into PostgreSQL syntax.
     *
     * @param string $returnType Function return type from model metadata
     * @return string
     */
    private function translateReturnType($returnType)
    {
        $returnType = preg_replace('/\s+CHARSET\s+\w+/i', '', $returnType);

        return trim($returnType);
    }

    /**
     * This function translates a MySQL-oriented function body into PostgreSQL plpgsql.
     *
     * @param string $statement Function body from model metadata
     * @return string
     */
    private function translateFunctionStatement($statement)
    {
        $statement = trim($statement);
        $statement = rtrim($statement, ';');

        if (preg_match('/^return\s+@modelId$/i', $statement)) {
            return "RETURN current_setting('gaia.model_id', true);";
        }

        if (preg_match('/^return\s+@currentUserId$/i', $statement)) {
            return "RETURN current_setting('gaia.current_user_id', true);";
        }

        return $statement;
    }

    /**
     * This function converts MySQL-style double-quoted string literals to PostgreSQL single quotes.
     *
     * @param string $sql SQL fragment to normalize
     * @return string
     */
    private function replaceMysqlStringLiterals($sql)
    {
        return preg_replace('/(?<![.\w])"([^"]*)"/', "'$1'", $sql);
    }

    /**
     * This function removes MySQL-style table aliases from UPDATE statements.
     *
     * @param string $sql SQL fragment to normalize
     * @return string
     */
    private function stripUpdateTableAliases($sql)
    {
        return preg_replace_callback(
            '/\bUPDATE\s+([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+AS\s+|\s+)([a-zA-Z_][a-zA-Z0-9_]*)\s+SET\s+\2\./i',
            function (array $matches) {
                return 'UPDATE ' . $matches[1] . ' SET ';
            },
            $sql
        );
    }

    /**
     * This function replaces alias-qualified column references with quoted column names.
     *
     * @param string $sql SQL fragment to normalize
     * @return string
     */
    private function stripTableAliasReferences($sql)
    {
        return preg_replace_callback(
            '/\b(?!NEW|OLD)([A-Z][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\b/',
            function (array $matches) {
                return $this->quoteColumnReference($matches[2]);
            },
            $sql
        );
    }

    /**
     * This function quotes a column reference when it contains mixed case characters.
     *
     * @param string $column Column name
     * @return string
     */
    private function quoteColumnReference($column)
    {
        if (preg_match('/[A-Z]/', $column)) {
            return '"' . $column . '"';
        }

        return $column;
    }

    /**
     * This function quotes mixed-case identifiers for PostgreSQL outside string literals.
     *
     * @param string $sql SQL fragment to normalize
     * @return string
     */
    private function quoteMixedCaseIdentifiers($sql)
    {
        $parts = preg_split("/('(?:''|[^'])*')/", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $sql;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }

            $parts[$index] = $this->quoteMixedCaseIdentifiersInFragment($part);
        }

        return implode('', $parts);
    }

    /**
     * This function quotes mixed-case identifiers in a SQL fragment that contains no string literals.
     *
     * @param string $sql SQL fragment to normalize
     * @return string
     */
    private function quoteMixedCaseIdentifiersInFragment($sql)
    {
        $keywords = array(
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT', 'IN', 'IS', 'NULL', 'AS', 'ON',
            'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'GROUP', 'BY', 'ORDER', 'LIMIT',
            'UNION', 'ALL', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'SUM', 'MAX', 'MIN',
            'COUNT', 'CAST', 'DESC', 'ASC', 'IF', 'ELSIF', 'BEGIN', 'RETURN', 'NEW', 'OLD',
            'UPDATE', 'SET', 'INSERT', 'DELETE', 'INTO', 'VALUES', 'BETWEEN', 'LIKE',
            'TRUE', 'FALSE', 'WITH', 'HAVING', 'DISTINCT', 'COALESCE', 'TIMEZONE', 'NOW',
            'FLOOR', 'POWER', 'DATE', 'VARCHAR', 'UTC', 'OVER', 'PARTITION',
            'GETMODELID', 'GETCURRENTUSERID',
        );

        return preg_replace_callback(
            '/(?<!["\.])\b([a-zA-Z_][a-zA-Z0-9_]*[A-Z][a-zA-Z0-9_]*)\b(?!")/',
            function (array $matches) use ($keywords) {
                if (in_array(strtoupper($matches[1]), $keywords, true)) {
                    return $matches[1];
                }

                return '"' . $matches[1] . '"';
            },
            $sql
        );
    }

    /**
     * This function quotes a PostgreSQL identifier.
     *
     * @param string $identifier Identifier to quote
     * @return string
     */
    private function quoteIdentifier($identifier)
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * This function quotes a PostgreSQL string literal value.
     *
     * @param string $value Literal value to quote
     * @return string
     */
    private function quoteValue($value)
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
