<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * Thrown when the embeddings service cannot return a usable vector: it is
 * unreachable, timed out, rejected the request, or answered with a body that
 * does not match the contract. The message carries the reason.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EmbeddingsUnavailableException extends Exception
{
}
