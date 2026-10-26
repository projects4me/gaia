<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

use RuntimeException;

/**
 * A scaffold that cannot be produced or written: unknown route or profile, duplicate
 * case id, a file whose layout the scaffolder does not recognise. The message is the
 * one-line reason printed on stderr; nothing has been written when it is thrown.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldException extends RuntimeException
{
}
