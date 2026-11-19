<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use PHPUnit\Framework\TestCase;
use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Tests that a read by id answers 404 when no record matched, and passes a found record through.
 *
 * @author   Agent Odysseus
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RestControllerRecordFoundTest extends TestCase
{
    /**
     * Call the protected assertRecordFound on a controller built without its constructor.
     *
     * @param array $dataArray
     * @return void
     */
    private function assertFound(array $dataArray)
    {
        $class = new \ReflectionClass(RestController::class);
        $controller = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('assertRecordFound');
        gaiaTestMakeAccessible($method);
        $method->invoke($controller, $dataArray, 'issue');
    }

    public function testEmptyDataIsNotFound()
    {
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        $this->expectExceptionMessage('Issue not found');
        $this->assertFound(['data' => [], 'meta' => ['count' => 0]]);
    }

    public function testMissingDataIsNotFound()
    {
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        $this->assertFound([]);
    }

    public function testFoundRecordPasses()
    {
        $this->assertFound(['data' => ['type' => 'Issue', 'id' => 'abc', 'attributes' => []]]);
        $this->addToAssertionCount(1);
    }
}
