<?php

namespace Gaia\Tests\Controller\Components;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\REST\Controllers\Components\FilethumbComponent;

/**
 * Tests that FilethumbComponent turns the fileThumbnail flag of upload resources into a data URI,
 * in both data (list or single resource) and included.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FilethumbComponentTest extends TestCase
{
    private $thumbs = [];

    protected function setUp(): void
    {
        global $logger;
        if (!isset($logger)) {
            $logger = new \Phalcon\Logger('test', ['main' => new \Phalcon\Logger\Adapter\Noop()]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->thumbs as $f) {
            @unlink($f);
        }
    }

    private function makeThumb($id)
    {
        $dir = APP_PATH . DS . 'filesystem' . DS . 'uploads' . DS . 'thumbnail';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . DS . 'thumb_' . $id . '.jpg';
        file_put_contents($file, 'abc');
        $this->thumbs[] = $file;
    }

    private function upload($id, $thumb = '1')
    {
        return ['type' => 'upload', 'id' => $id, 'attributes' => ['name' => 'a.png', 'fileMime' => 'image/png', 'fileThumbnail' => $thumb]];
    }

    private function run_($finalData)
    {
        $controller = new \stdClass();
        $controller->finalData = $finalData;
        (new FilethumbComponent())->afterRead(new \Phalcon\Events\Event('rest:afterRead', $controller), $controller);
        return $controller->finalData;
    }

    public function testConvertsListInData()
    {
        $this->makeThumb('t-1');
        $out = $this->run_(['data' => [$this->upload('t-1')]]);
        $this->assertSame('data:image/jpeg;base64,' . base64_encode('abc'), $out['data'][0]['attributes']['fileThumbnail']);
    }

    public function testConvertsSingleResourceAndIncluded()
    {
        $this->makeThumb('t-2');
        $this->makeThumb('t-3');
        $out = $this->run_(['data' => $this->upload('t-2'), 'included' => [$this->upload('t-3')]]);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $out['data']['attributes']['fileThumbnail']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $out['included'][0]['attributes']['fileThumbnail']);
    }

    public function testLeavesNonThumbnailsAndOtherTypesAlone()
    {
        $other = ['type' => 'issue', 'id' => 'i', 'attributes' => ['fileThumbnail' => '1']];
        $out = $this->run_(['data' => [$this->upload('t-4', '0'), $other, $this->upload('missing-file')]]);
        $this->assertSame('0', $out['data'][0]['attributes']['fileThumbnail']);
        $this->assertSame('1', $out['data'][1]['attributes']['fileThumbnail']);
        $this->assertSame('1', $out['data'][2]['attributes']['fileThumbnail']);
    }
}
