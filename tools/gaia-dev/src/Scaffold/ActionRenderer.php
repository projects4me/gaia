<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Renders the thin override of a standard action: delegate to the domain rules, then to the standard action, which authorizes (the Design rules).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActionRenderer
{
    private const SUMMARY = [
        'list' => 'List',
        'get' => 'Get one record of',
        'post' => 'Create',
        'patch' => 'Update',
        'delete' => 'Delete',
    ];

    /**
     * @param ActionSpec $spec
     * @return string the method, ready to go before the class's closing brace
     */
    public function method(ActionSpec $spec): string
    {
        $lines = [
            "        // {$spec->domain}: apply the domain rules here, then let the standard action parse, authorize and respond.",
            '',
            "        return parent::{$spec->action()}();",
        ];

        return "    /**\n"
            . '     * ' . self::SUMMARY[$spec->verb] . " {$spec->resource()} records: parse, authorize ({$spec->permission()}), then the\n"
            . "     * rules of {$spec->domain} go here before the standard action runs.\n"
            . "     *\n"
            . '     * ' . ActionSpec::MARKER . " {$spec->verb}\n"
            . "     *\n"
            . "     * @return \\Phalcon\\Http\\Response\n"
            . "     */\n"
            . "    public function {$spec->action()}()\n    {\n"
            . implode("\n", $lines) . "\n    }\n";
    }
}
