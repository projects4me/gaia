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
     * A project created without a name gets no short code: the behaviour does
     * not read the unset name, and the model's own validation rejects the
     * missing name.
     *
     * @return void
     */
    public function testCreateWithoutNameLeavesTheShortCodeUnset()
    {
        $model = $this->getMockBuilder(UnsetAttributeModelStub::class)->getMockForAbstractClass();

        $this->shortCodeBehaviorMock->expects($this->never())->method('getProjectsByShortCode');
        $this->shortCodeBehaviorMock->notify('beforeValidationOnCreate', $model);

        $this->assertFalse(property_exists($model, 'shortCode'));
    }

    /**
     * This test verifies that the short code is generated correctly from a single word if there is no short code.
     *
     * @return void
     */
    public function testGenerateShortCodeFromSingleWordIfNoShortCodeExists()
    {
        $name = "Project";
        $expectedShortCode = "proje";

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
        $expectedShortCode = "proje1";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'proje']
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
        $expectedShortCode = "proje6";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'proje1'],
            (object)['shortCode' => 'proje2'],
            (object)['shortCode' => 'proje3'],
            (object)['shortCode' => 'proje4'],
            (object)['shortCode' => 'proje5'],
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
        $expectedShortCode = "pm";

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
        $expectedShortCode = "pm1";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'pm']
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
        $expectedShortCode = "pm5";

        $this->shortCodeBehaviorMock->method('getProjectsByShortCode')->willReturn(
            [
            (object)['shortCode' => 'pm1'],
            (object)['shortCode' => 'pm2'],
            (object)['shortCode' => 'pm3'],
            (object)['shortCode' => 'pm4']
            ]
        );
        $shortCode = $this->shortCodeBehaviorMock->generateShortCode($name);

        $this->assertEquals($expectedShortCode, $shortCode);
    }
}
