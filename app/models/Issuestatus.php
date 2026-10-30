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
     * Why an invalid colour is refused.
     *
     * @var string
     */
    public const COLOR_MESSAGE = 'Colour must be a hex colour in the form #rrggbb, e.g. #1a2b3c.';

    /**
     * The label text colours a status may use; null means the UI picks one from the colour.
     *
     * @var array
     */
    public const TEXT_COLORS = ['dark', 'light'];

    /**
     * Why an invalid label text colour is refused.
     *
     * @var string
     */
    public const TEXT_COLOR_MESSAGE = 'Text colour must be dark, light or null.';

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
     * Whether the value is a label text colour a status may use: null (automatic),
     * `dark` or `light`, in any case.
     *
     * @param  mixed $textColor
     * @return bool
     */
    public static function isValidTextColor($textColor)
    {
        return $textColor === null || (is_string($textColor) && in_array(strtolower($textColor), self::TEXT_COLORS, true));
    }

    /**
     * Refuse to save an invalid colour or label text colour, and save valid ones
     * lowercase so the same value is always stored the same way.
     *
     * @return bool
     */
    public function beforeValidation()
    {
        $color = $this->attributeValue('color');
        $textColor = $this->attributeValue('textColor');

        if (!self::isValidColor($color)) {
            $this->appendMessage(new Message(self::COLOR_MESSAGE, 'color', 'InvalidValue'));
            return false;
        }

        if (!self::isValidTextColor($textColor)) {
            $this->appendMessage(new Message(self::TEXT_COLOR_MESSAGE, 'textColor', 'InvalidValue'));
            return false;
        }

        if ($color !== null) {
            $this->color = strtolower($color);
        }

        if ($textColor !== null) {
            $this->textColor = strtolower($textColor);
        }

        return true;
    }

    /**
     * The value of an attribute, null when it was not set. Reads the property directly,
     * so an unset attribute isn't looked up as a relation by the model's magic `__isset`.
     *
     * @param  string $name
     * @return mixed
     */
    protected function attributeValue($name)
    {
        return property_exists($this, $name) ? $this->$name : null;
    }
}
