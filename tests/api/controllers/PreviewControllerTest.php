<?php

namespace Gaia\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Downloadtoken;
use Gaia\MVC\Models\Upload;
use Gaia\MVC\REST\Controllers\PreviewController;

/**
 * Tests GET /preview/get/<token>: images only, bound token lookup, upload loaded by uploadId, single use.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PreviewControllerTest extends TestCase
{
    /** @var string[] files and records created by a test */
    private $files = [];
    private $uploads = [];
    private $tokens = [];

    protected function setUp(): void
    {
        global $logger, $currentUser;
        $currentUser = (object) ['id' => '00000000-0000-0000-0000-000000000000', 'name' => 'phpunit'];
        if (!isset($logger)) {
            $logger = new \Phalcon\Logger('test', ['main' => new \Phalcon\Logger\Adapter\Noop()]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tokens as $t) {
            @$t->delete();
        }
        foreach ($this->uploads as $u) {
            @$u->delete();
        }
        foreach ($this->files as $f) {
            @unlink($f);
        }
    }

    private function guid(): string
    {
        $h = bin2hex(random_bytes(16));
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }

    private function makeUpload(string $mime, string $relatedTo = 'idea'): Upload
    {
        $path = tempnam(sys_get_temp_dir(), 'prev');
        file_put_contents($path, 'PNGDATA');
        $this->files[] = $path;

        $upload = new Upload();
        $upload->id = $this->guid();
        $upload->name = 'screen.png';
        $upload->relatedTo = $relatedTo;
        $upload->relatedId = 'preview-test';
        $upload->status = 'active';
        $upload->fileMime = $mime;
        $upload->filePath = $path;
        $upload->fileSize = '7';
        $this->assertTrue($upload->save(), implode("; ", array_map("strval", $upload->getMessages())));
        $this->uploads[] = $upload;
        return $upload;
    }

    private function makeToken(Upload $upload, string $expires = '+10 minutes'): Downloadtoken
    {
        $token = new Downloadtoken();
        $token->id = $this->guid();
        $token->downloadToken = \Phalcon\Text::random(\Phalcon\Text::RANDOM_ALNUM, 36);
        $token->uploadId = $upload->id;
        $token->relatedId = $upload->relatedId;
        $token->relatedTo = $upload->relatedTo;
        $token->expires = gmdate('Y-m-d H:i:s', strtotime($expires));
        $this->assertTrue($token->save(), implode("; ", array_map("strval", $token->getMessages())));
        $this->tokens[] = $token;
        return $token;
    }

    public function testServesIdeaImageInlineAndConsumesToken(): void
    {
        $token = $this->makeToken($this->makeUpload('image/png'));

        $response = (new PreviewController())->getAction($token->downloadToken);

        $this->assertSame('PNGDATA', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertSame('inline', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('no-store', $headers->get('Cache-Control'));
        $this->assertNull(Downloadtoken::findFirst([
            'conditions' => 'downloadToken = :t:',
            'bind' => ['t' => $token->downloadToken],
        ]));
    }

    public function testSvgIsNotFound(): void
    {
        $token = $this->makeToken($this->makeUpload('image/svg+xml'));
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        (new PreviewController())->getAction($token->downloadToken);
    }

    public function testTokenIsSingleUse(): void
    {
        $token = $this->makeToken($this->makeUpload('image/png'));
        (new PreviewController())->getAction($token->downloadToken);
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        (new PreviewController())->getAction($token->downloadToken);
    }

    public function testNonImageIsNotFound(): void
    {
        $token = $this->makeToken($this->makeUpload('text/html'));
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        (new PreviewController())->getAction($token->downloadToken);
    }

    public function testExpiredTokenIsNotFound(): void
    {
        $token = $this->makeToken($this->makeUpload('image/png'), '-1 minute');
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        (new PreviewController())->getAction($token->downloadToken);
    }

    public function testInjectionStringIsNotFound(): void
    {
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);
        (new PreviewController())->getAction("x' OR '1'='1");
    }
}
