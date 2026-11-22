<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

/**
 * Keeps issue relationships acyclic: answers whether adding an edge from
 * one issue to another would close a cycle over an {@see IssueEdgeSource}.
 * The walk is a recursive CTE bound by a depth cap and ignores ACL, so it
 * sees the whole graph.
 *
 * Writers that add edges take {@see lock()} inside their transaction first,
 * so two concurrent writes cannot each pass the check and together close a
 * cycle. Every edge kind shares the one lock key.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueGraph
{
    /**
     * The adding edge does not close a cycle.
     */
    const ACYCLIC = 'acyclic';

    /**
     * The adding edge closes a cycle (or is a self edge).
     */
    const CYCLE = 'cycle';

    /**
     * The walk reached the depth cap before it could prove there is no cycle.
     */
    const TOO_DEEP = 'tooDeep';

    /**
     * The default number of edges the walk follows from the target.
     */
    const DEFAULT_MAX_DEPTH = 1000;

    /**
     * The name hashed into the advisory lock key (pg_advisory_xact_lock).
     */
    const LOCK_NAME = 'gaia.issue-graph';

    /**
     * @var IssueEdgeSource
     */
    private $edges;

    /**
     * @var int
     */
    private $maxDepth;

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface|null
     */
    private $db;

    /**
     * @param IssueEdgeSource                           $edges
     * @param int                                       $maxDepth
     * @param \Phalcon\Db\Adapter\AdapterInterface|null $db
     */
    public function __construct(IssueEdgeSource $edges, $maxDepth = self::DEFAULT_MAX_DEPTH, $db = null)
    {
        $this->edges = $edges;
        $this->maxDepth = max(1, (int) $maxDepth);
        $this->db = $db;
    }

    /**
     * Whether adding the edge from -> to would close a cycle, or could not be
     * proven not to within the depth cap.
     *
     * @param  string $from
     * @param  string $to
     * @return bool
     */
    public function wouldCreateCycle($from, $to)
    {
        return $this->check($from, $to) !== self::ACYCLIC;
    }

    /**
     * Whether adding the edge from -> to keeps the graph acyclic: ACYCLIC,
     * CYCLE when "from" is reachable from "to" (or they are the same issue),
     * TOO_DEEP when the walk hit the depth cap first.
     *
     * @param  string $from
     * @param  string $to
     * @return string One of the ACYCLIC, CYCLE, TOO_DEEP constants
     */
    public function check($from, $to)
    {
        $from = (string) $from;
        $to = (string) $to;
        if ($from === $to) {
            return self::CYCLE;
        }

        $row = $this->db()->fetchOne(
            'WITH RECURSIVE "walk"("node", "depth") AS ('
            . ' SELECT CAST(? AS varchar), 0'
            . ' UNION'
            . ' SELECT "edge"."target", "walk"."depth" + 1'
            . ' FROM "walk" JOIN (' . $this->edges->edgesSql() . ') AS "edge" ON "edge"."source" = "walk"."node"'
            . ' WHERE "walk"."depth" < ? AND "walk"."node" <> ?'
            . ')'
            . ' SELECT COALESCE(BOOL_OR("node" = ?), false) AS "found", MAX("depth") AS "deepest" FROM "walk"',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            [$to, $this->maxDepth, $from, $from],
            [
                \Phalcon\Db\Column::BIND_PARAM_STR,
                \Phalcon\Db\Column::BIND_PARAM_INT,
                \Phalcon\Db\Column::BIND_PARAM_STR,
                \Phalcon\Db\Column::BIND_PARAM_STR,
            ]
        );

        if ($this->isTrue($row['found'] ?? false)) {
            return self::CYCLE;
        }

        return (int) ($row['deepest'] ?? 0) >= $this->maxDepth ? self::TOO_DEEP : self::ACYCLIC;
    }

    /**
     * Take the issue graph's advisory lock until the current transaction
     * ends. Outside a transaction the lock would end with the statement.
     *
     * @return void
     * @throws \LogicException When no transaction is open
     */
    public function lock()
    {
        $db = $this->db();
        if (!$db->isUnderTransaction()) {
            throw new \LogicException('The issue graph can only be locked inside a transaction.');
        }

        $db->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            [self::LOCK_NAME],
            [\Phalcon\Db\Column::BIND_PARAM_STR]
        );
    }

    /**
     * @param  mixed $value A Postgres boolean as PDO returns it
     * @return bool
     */
    private function isTrue($value)
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }

    /**
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    private function db()
    {
        if ($this->db === null) {
            $this->db = \Phalcon\Di\Di::getDefault()->getShared('db');
        }

        return $this->db;
    }
}
