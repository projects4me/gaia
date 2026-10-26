<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

use RuntimeException;

/**
 * A query that cannot be answered: unknown name, missing graph. The message is
 * the one-line hint printed on stderr.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryException extends RuntimeException
{
}
