<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;

/**
 * A model mock that reads an attribute it was never given the way a Phalcon
 * model does: through __get(), which Phalcon answers with an "Access to
 * undefined property" notice. Here that read throws, so a test fails when
 * code reads an optional attribute without checking that it is set.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
#[\AllowDynamicProperties]
abstract class UnsetAttributeModelStub implements ModelInterface
{
    /**
     * The snapshot getSnapshotData() returns.
     *
     * @var array|null
     */
    public $snapshot = null;

    /**
     * @param  string $property
     * @return mixed
     * @throws \LogicException Always: the attribute was never set.
     */
    public function __get($property)
    {
        throw new \LogicException('Access to undefined property ' . static::class . '::' . $property);
    }

    /**
     * @param  string $property
     * @return bool
     */
    public function __isset($property)
    {
        return false;
    }

    /**
     * @return array|null
     */
    public function getSnapshotData()
    {
        return $this->snapshot;
    }
}
