<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Phalcon 4 class names on Phalcon 5.
 *
 * Until the switch-over to PHP 8.5 / Phalcon 5, the same code runs on both
 * stacks. Phalcon 5 moved or removed some Phalcon 4 classes that Gaia still
 * uses by their old names. This file defines those names on Phalcon 5 and
 * does nothing on Phalcon 4, where they exist.
 *
 * - Moved without API changes: class aliases (class_alias accepts internal
 *   classes since PHP 8.3).
 * - Changed API or removed: small classes in compat/phalcon5/ that keep the
 *   Phalcon 4 API.
 *
 * Remove this file, compat/phalcon5/ and their require at the switch-over,
 * once the code uses the Phalcon 5 names (docs/upgrade/php85-phalcon5.md).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Compat
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */

// Phalcon\Autoload\Loader is new in Phalcon 5. It is an extension class, so
// no autoloader is needed (or triggered) to find it.
if (!class_exists('Phalcon\Autoload\Loader', false)) {
    return;
}

$phalcon5Aliases = [
    'Phalcon\Di' => 'Phalcon\Di\Di',
    'Phalcon\DiInterface' => 'Phalcon\Di\DiInterface',
    'Phalcon\Config' => 'Phalcon\Config\Config',
    'Phalcon\Logger' => 'Phalcon\Logger\Logger',
];

foreach ($phalcon5Aliases as $phalcon4Name => $phalcon5Name) {
    if (!class_exists($phalcon4Name, false) && !interface_exists($phalcon4Name, false)) {
        class_alias($phalcon5Name, $phalcon4Name);
    }
}

unset($phalcon5Aliases, $phalcon4Name, $phalcon5Name);

require_once __DIR__ . '/phalcon5/Exception.php';
require_once __DIR__ . '/phalcon5/Loader.php';
require_once __DIR__ . '/phalcon5/Text.php';
