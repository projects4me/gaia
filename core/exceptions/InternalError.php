<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * An error inside Gaia that has no HTTP meaning of its own: a missing id or an
 * unknown relationship in a code path, a failed internal save.
 *
 * It replaces Phalcon 4's Phalcon\Exception, which Phalcon 5 removed. Like
 * that class it extends \Exception directly and builds no response itself, so
 * public/index.php and SubRequestRunner hand it to PhalconError: logged, a 500
 * with the raw message only when debug is on. It deliberately does not extend
 * Gaia\Exception\Exception, which would return the message to the client.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia
 * @category Exception
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class InternalError extends \Exception
{
}
