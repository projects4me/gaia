<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Phalcon;

/**
 * Phalcon 4's base exception, which Phalcon 5 removed. Gaia throws it by this
 * name. Phalcon 5's own exceptions don't extend it, so nothing catches it by
 * type (Gaia\Exception\PhalconError handles any Throwable).
 *
 * Loaded only on Phalcon 5, by core/libs/compat/phalcon5.php.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Compat
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Exception extends \Exception
{
}
