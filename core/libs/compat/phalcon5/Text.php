<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Phalcon;

use Phalcon\Support\Helper\Str\Camelize;
use Phalcon\Support\Helper\Str\Humanize;
use Phalcon\Support\Helper\Str\Lower;
use Phalcon\Support\Helper\Str\Random;

/**
 * The part of Phalcon 4's Phalcon\Text that Gaia uses, which Phalcon 5
 * replaced with the Phalcon\Support\Helper\Str classes.
 *
 * Loaded only on Phalcon 5, by core/libs/compat/phalcon5.php.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Compat
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Text
{
    const RANDOM_ALNUM = Random::RANDOM_ALNUM;
    const RANDOM_ALPHA = Random::RANDOM_ALPHA;
    const RANDOM_HEXDEC = Random::RANDOM_HEXDEC;
    const RANDOM_NUMERIC = Random::RANDOM_NUMERIC;
    const RANDOM_NOZERO = Random::RANDOM_NOZERO;
    const RANDOM_DISTINCT = Random::RANDOM_DISTINCT;

    /**
     * "issue_activity" -> "IssueActivity".
     */
    public static function camelize(string $text, ?string $delimiter = null): string
    {
        return (new Camelize())($text, $delimiter);
    }

    /**
     * "in_progress" -> "in progress".
     */
    public static function humanize(string $text): string
    {
        return (new Humanize())($text);
    }

    public static function lower(string $text, string $encoding = 'UTF-8'): string
    {
        return (new Lower())($text, $encoding);
    }

    public static function random(int $type = self::RANDOM_ALNUM, int $length = 8): string
    {
        return (new Random())($type, $length);
    }
}
