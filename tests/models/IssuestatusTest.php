<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Issuestatus;

/**
 * Unit tests for the Issuestatus colour rule: any `#rrggbb` hex colour or
 * null is valid, and the colour is saved lowercase.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuestatusTest extends TestCase
{
    /**
     * Any 6-digit hex colour, in either case, and null are valid.
     *
     * @return void
     */
    public function testHexColorsAndNullAreValid()
    {
        foreach (['#80b5d3', '#1a2b3c', '#1A2B3C', '#000000', '#FFFFFF', null] as $color) {
            $this->assertTrue(Issuestatus::isValidColor($color), var_export($color, true));
        }
    }

    /**
     * Colour names, short or malformed hex and CSS functions are rejected.
     *
     * @return void
     */
    public function testOtherValuesAreInvalid()
    {
        $invalid = ['', 'red', '#fff', '#12345g', '1a2b3c', '#1a2b3c4', '#1a2b3c ', "#1a2b3c\n", 'url(x)', 'rgb(1,2,3)', 123456, []];

        foreach ($invalid as $color) {
            $this->assertFalse(Issuestatus::isValidColor($color), var_export($color, true));
        }
    }

    /**
     * A valid colour is lowercased before it is saved.
     *
     * @return void
     */
    public function testColorIsSavedLowercase()
    {
        $status = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $status->color = '#1A2B3C';

        $this->assertTrue($status->beforeValidation());
        $this->assertSame('#1a2b3c', $status->color);
    }

    /**
     * A save with an invalid colour is refused with a message on `color`.
     *
     * @return void
     */
    public function testInvalidColorIsNotSaved()
    {
        $status = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $status->color = 'red';

        $this->assertFalse($status->beforeValidation());
        $this->assertSame('color', $status->getMessages()[0]->getField());
    }

    /**
     * Dark and light, in any case, are valid label text colours.
     *
     * @return void
     */
    public function testDarkAndLightTextColorsAreValid()
    {
        foreach (['dark', 'light', 'Dark', 'LIGHT'] as $textColor) {
            $this->assertTrue(Issuestatus::isValidTextColor($textColor), var_export($textColor, true));
        }
    }

    /**
     * Null, colour names, hex colours, "auto" and other values are not label text colours.
     *
     * @return void
     */
    public function testOtherTextColorsAreInvalid()
    {
        $invalid = [null, '', 'black', 'white', '#000000', '#ffffff', 'auto', 'darker', ' dark', "dark\n", 1, true, []];

        foreach ($invalid as $textColor) {
            $this->assertFalse(Issuestatus::isValidTextColor($textColor), var_export($textColor, true));
        }
    }

    /**
     * A valid label text colour is lowercased before it is saved.
     *
     * @return void
     */
    public function testTextColorIsSavedLowercase()
    {
        $status = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $status->textColor = 'Dark';

        $this->assertTrue($status->beforeValidation());
        $this->assertSame('dark', $status->textColor);
    }

    /**
     * A status saved without a label text colour, or with a null one (saved before
     * the default existed), gets the default light.
     *
     * @return void
     */
    public function testMissingTextColorDefaultsToLight()
    {
        $unset = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $unset->color = '#80B5D3';
        $empty = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $empty->textColor = null;

        $this->assertTrue($unset->beforeValidation());
        $this->assertSame('#80b5d3', $unset->color);
        $this->assertSame('light', $unset->textColor);
        $this->assertTrue($empty->beforeValidation());
        $this->assertSame('light', $empty->textColor);
    }

    /**
     * A save with an invalid label text colour is refused with a message on `textColor`.
     *
     * @return void
     */
    public function testInvalidTextColorIsNotSaved()
    {
        $status = (new \ReflectionClass(Issuestatus::class))->newInstanceWithoutConstructor();
        $status->color = '#1a2b3c';
        $status->textColor = 'black';

        $this->assertFalse($status->beforeValidation());
        $this->assertSame('textColor', $status->getMessages()[0]->getField());
    }
}
