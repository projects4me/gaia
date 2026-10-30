<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Phalcon\Messages\Message;

/**
 * Issue status Model
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Issuestatus extends Model
{
    /**
     * Format of a status colour.
     *
     * @var string
     */
    public const COLOR_PATTERN = '/^#[0-9a-f]{6}\z/i';

    /**
     * Whether the value is a colour a status may use: null (no colour) or `#rrggbb`.
     *
     * @param  mixed $color
     * @return bool
     */
    public static function isValidColor($color)
    {
        return $color === null || (is_string($color) && preg_match(self::COLOR_PATTERN, $color) === 1);
    }

    /**
     * Refuse to save an invalid colour, and save a valid one lowercase so the
     * same colour is always stored the same way.
     *
     * @return bool
     */
    public function beforeValidation()
    {
        $color = isset($this->color) ? $this->color : null;

        if (!self::isValidColor($color)) {
            $this->appendMessage(new Message('Colour must be a hex colour in the form #rrggbb, e.g. #1a2b3c.', 'color', 'InvalidValue'));
            return false;
        }

        if ($color !== null) {
            $this->color = strtolower($color);
        }

        return true;
    }
}
