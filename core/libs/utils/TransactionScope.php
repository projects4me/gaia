<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Phalcon\Db\Adapter\AdapterInterface;

/**
 * Runs a piece of work in one database transaction: it commits when the work
 * succeeded and rolls back when it failed or threw.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TransactionScope
{
    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @param AdapterInterface $connection The connection the work saves through (the shared `db` service).
     */
    public function __construct(AdapterInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param  callable $work      Does the work and returns its result.
     * @param  callable $succeeded Given the result, tells whether to commit.
     * @return mixed The result of $work.
     * @throws \Throwable What $work threw, after rolling back.
     */
    public function run(callable $work, callable $succeeded)
    {
        $this->connection->begin();

        try {
            $result = $work();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        if ($succeeded($result)) {
            $this->connection->commit();
        } else {
            $this->rollback();
        }

        return $result;
    }

    /**
     * @return void
     */
    private function rollback()
    {
        if ($this->connection->isUnderTransaction()) {
            $this->connection->rollback();
        }
    }
}
