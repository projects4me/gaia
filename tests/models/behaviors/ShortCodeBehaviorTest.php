<?php

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\shortCodeBehavior;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Resultset\Simple;

/**
 * This class contains unit tests for the ShortCodeBehavior class.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ShortCodeBehaviorTest extends TestCase
{
    /**
     * @var shortCodeBehavior $shortCodeBehaviorMock The mock object for the ShortCodeBehavior class.
     */
    protected $shortCodeBehaviorMock;


    /**
     * This method is called before each test is executed.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->shortCodeBehaviorMock = $this->getMockBuilder(shortCodeBehavior::class)
            ->setMethods(['getProjectsByShortCode'])
            ->getMock();
    }

    /**
     * This test verifies that the short code is generated correctly from a single word if there is no short code.
     *
     * @return void
     */
    public function testGenerateShortCodeFromSingleWordIfNoShortCodeExists()
    {
        $name = "Project";
        $expectedShortCode = "PROJE";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn([]);
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }

    /**
     * This test verifies that the short code is generated correctly from a single word if there is one short code.
     *
     * @return void
     */
    public function testGenerateShortCodeFromSingleWordIfOneShortCodeExists()
    {
        $name = "Project";
        $expectedShortCode = "PROJE1";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'PROJE']
            ]
        );
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }

    /**
     * This test verifies that the short code is generated correctly from a single word if there are multiple short codes.
     *
     * @return void
     */
    public function testGenerateShortCodeFromSingleWordIfMultipleShortCodeExists()
    {
        $name = "Project";
        $expectedShortCode = "PROJE6";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'PROJE1'],
            (object)['shortCode' => 'PROJE2'],
            (object)['shortCode' => 'PROJE3'],
            (object)['shortCode' => 'PROJE4'],
            (object)['shortCode' => 'PROJE5'],
            ]
        );

        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }

    /**
     * This test verifies that the short code is generated correctly from multiple words if there is no short code.
     *
     * @return void
     */
    public function testGenerateShortCodeFromMultipleWordsIfNoShortCodeExists()
    {
        $name = "Project Management";
        $expectedShortCode = "PM";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn([]);
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }


    /**
     * This test verifies that the short code is generated correctly from multiple words if there is one short code.
     *
     * @return void
     */
    public function testGenerateShortCodeFromMultipleWordsIfOneShortCodeExists()
    {
        $name = "Project Management";
        $expectedShortCode = "PM1";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'PM']
            ]
        );
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }

    /**
     * This test verifies that the short code is generated correctly from multiple words if there are multiple short codes.
     *
     * @return void
     */
    public function testGenerateShortCodeFromMultipleWordsIfMultipleShortCodeExists()
    {
        $name = "Project Management";
        $expectedShortCode = "PM5";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'PM1'],
            (object)['shortCode' => 'PM2'],
            (object)['shortCode' => 'PM3'],
            (object)['shortCode' => 'PM4']
            ]
        );
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }
}
