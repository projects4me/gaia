<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;

/**
 * Base for the model mocks the behaviour tests build: a ModelInterface that
 * accepts the attributes the tests set on it, which PHP 8.2+ otherwise
 * reports as deprecated dynamic properties on a generated mock.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
#[\AllowDynamicProperties]
abstract class DynamicModelStub implements ModelInterface
{
}
