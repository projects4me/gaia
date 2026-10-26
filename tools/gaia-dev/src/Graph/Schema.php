<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

use InvalidArgumentException;

/**
 * The graph schema: node types, edge types and the node types each edge may
 * connect. tools/gaia-dev/README.md documents it; bump VERSION on any change
 * that breaks readers (renamed or removed types or ids).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Schema
{
    public const VERSION = 1;

    public const MODEL = 'model';
    public const FIELD = 'field';
    public const RELATIONSHIP = 'relationship';
    public const BEHAVIOUR = 'behaviour';
    public const ROUTE = 'route';
    public const CONTROLLER = 'controller';
    public const ACTION = 'action';
    public const COMPONENT = 'component';
    public const PERMISSION = 'permission';
    public const PROFILE = 'profile';
    public const TEST_CASE = 'case';
    public const ASSERTION = 'assertion';
    public const FIXTURE = 'fixture';

    /**
     * Edge type => [from node type, to node type].
     */
    public const EDGES = [
        'has_field' => [self::MODEL, self::FIELD],
        'linked_to' => [self::FIELD, self::FIELD],
        'has_relationship' => [self::MODEL, self::RELATIONSHIP],
        'targets' => [self::RELATIONSHIP, self::MODEL],
        'via' => [self::RELATIONSHIP, self::MODEL],
        'uses_behaviour' => [self::MODEL, self::BEHAVIOUR],
        'has_action' => [self::CONTROLLER, self::ACTION],
        'serves' => [self::CONTROLLER, self::MODEL],
        'handled_by' => [self::ROUTE, self::ACTION],
        'uses_component' => [self::CONTROLLER, self::COMPONENT],
        'requires' => [self::ACTION, self::PERMISSION],
        'guards' => [self::PERMISSION, self::FIELD],
        'grants' => [self::PROFILE, self::PERMISSION],
        'denies' => [self::PROFILE, self::PERMISSION],
        'grants_members' => [self::PROFILE, self::PERMISSION],
        'covers' => [self::TEST_CASE, self::CONTROLLER],
        'exercises' => [self::TEST_CASE, self::ROUTE],
        'runs_as' => [self::TEST_CASE, self::PROFILE],
        'asserts' => [self::TEST_CASE, self::ASSERTION],
        'uses_fixture' => [self::TEST_CASE, self::FIXTURE],
        'depends_on' => [self::TEST_CASE, self::TEST_CASE],
    ];

    /**
     * @return string[]
     */
    public static function nodeTypes(): array
    {
        return [
            self::MODEL, self::FIELD, self::RELATIONSHIP, self::BEHAVIOUR, self::ROUTE, self::CONTROLLER,
            self::ACTION, self::COMPONENT, self::PERMISSION, self::PROFILE, self::TEST_CASE, self::ASSERTION,
            self::FIXTURE,
        ];
    }

    /**
     * Node ids are `<type>:<key>`, so a key only has to be unique within its type.
     *
     * @param string $type
     * @param string $key
     * @return string
     */
    public static function nodeId(string $type, string $key): string
    {
        self::assertNodeType($type);

        return $type . ':' . $key;
    }

    /**
     * @param string $type
     */
    public static function assertNodeType(string $type)
    {
        if (!in_array($type, self::nodeTypes(), true)) {
            throw new InvalidArgumentException("Unknown node type: {$type}");
        }
    }

    /**
     * @param string $type
     * @param string $fromType
     * @param string $toType
     */
    public static function assertEdge(string $type, string $fromType, string $toType)
    {
        if (!array_key_exists($type, self::EDGES)) {
            throw new InvalidArgumentException("Unknown edge type: {$type}");
        }
        if (self::EDGES[$type] !== [$fromType, $toType]) {
            throw new InvalidArgumentException(
                "Edge {$type} connects " . implode(' -> ', self::EDGES[$type]) . ", not {$fromType} -> {$toType}"
            );
        }
    }
}
