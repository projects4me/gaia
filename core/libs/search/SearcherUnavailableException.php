<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Thrown by a searcher that cannot rank because a service it depends on is down (semantic search without Ares).
 * The search goes on without that kind and reports it unavailable; the message carries the reason, for the log.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearcherUnavailableException extends \Gaia\Exception\Exception
{
}
