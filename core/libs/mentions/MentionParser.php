<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Mentions;

/**
 * Reads the users mentioned in a text. A mention is a link to a user's page,
 * /app/user/<id>, written as an HTML anchor (Prometheus' editor) or a
 * markdown link (markdown bodies), with a relative or absolute URL.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Mentions
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionParser
{
    /**
     * The href of an HTML anchor, quoted with " or '.
     */
    private const ANCHOR = '/<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1/is';

    /**
     * The target of a markdown link, optionally in <> and followed by a title.
     */
    private const MARKDOWN = '/\[[^\]]*\]\(\s*<?([^\s)>]+)>?(?:\s+(?:"[^"]*"|\'[^\']*\'))?\s*\)/';

    /**
     * The path of a user's page.
     */
    private const USER_PATH = '#^/app/user/([A-Za-z0-9-]+)/?$#';

    /**
     * The distinct ids of the users the text mentions, in the order they
     * first appear.
     *
     * @param  string|null $text
     * @return array
     */
    public function userIds($text)
    {
        if (!is_string($text) || $text === '') {
            return [];
        }

        $found = [];
        foreach ([[self::ANCHOR, 2], [self::MARKDOWN, 1]] as list($pattern, $group)) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[$group] as list($url, $offset)) {
                    $userId = $this->userIdOf($url);
                    if ($userId !== null) {
                        $found[$offset] = $userId;
                    }
                }
            }
        }
        ksort($found);

        return array_values(array_unique($found));
    }

    /**
     * The user id a URL points to, or null when it is not a user's page.
     * Absolute URLs must be http(s); relative ones must start at the root.
     *
     * @param  string $url
     * @return string|null
     */
    private function userIdOf($url)
    {
        $parts = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5));
        if ($parts === false || !isset($parts['path'])) {
            return null;
        }

        if (isset($parts['scheme'])) {
            if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true) || empty($parts['host'])) {
                return null;
            }
        } elseif (isset($parts['host'])) {
            return null;
        }

        return preg_match(self::USER_PATH, $parts['path'], $match) ? $match[1] : null;
    }
}
