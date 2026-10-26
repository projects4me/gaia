<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Scaffold\CatalogAppender;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests that appending cases keeps each catalog's own style (agaia-4133).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogAppenderTest extends TestCase
{
    private const CATALOGS = __DIR__ . '/../../../tools/api-tester/apis';

    private const ACL = <<<'JSON'
{
  "name": "x",
  "apis": [
    {
      "id": "one",
      "method": "GET",
      "path": "/api/v1/issue",
      "query": { "limit": 5, "page": 1 },
      "expects": { "status": 403, "shape": "error" },
      "source": "acl-group"
    }
  ]
}

JSON;

    private const CLIENT = <<<'JSON'
{
  "name": "x",
  "apis": [
    {
      "id": "one",
      "method": "GET",
      "path": "/api/v1/issue",
      "expects": {
        "status": 200
      }
    }
  ]
}

JSON;

    private function acl(): array
    {
        return [
            'id' => 'two',
            'method' => 'POST',
            'path' => '/api/v1/issue',
            'body' => ['data' => ['type' => 'issue', 'attributes' => new \stdClass()]],
            'expects' => ['status' => [200, 204], 'shape' => 'error'],
            'source' => 'acl-group',
        ];
    }

    public function testAclStyleKeepsExpectsOnOneLine()
    {
        $text = (new CatalogAppender())->append('acl', self::ACL, [$this->acl()]);

        $this->assertStringStartsWith(substr(self::ACL, 0, -strlen("\n    }\n  ]\n}\n")), $text);
        $this->assertStringContainsString("      \"expects\": { \"status\": [200, 204], \"shape\": \"error\" },\n", $text);
        $this->assertStringContainsString("      \"body\": {\n        \"data\": {\n          \"type\": \"issue\",\n          \"attributes\": {}\n        }\n      },\n", $text);
        $this->assertSame('two', json_decode($text, true)['apis'][1]['id']);
    }

    public function testClientStyleIsIndentedJson()
    {
        $text = (new CatalogAppender())->append('client', self::CLIENT, [$this->acl()]);

        $this->assertStringContainsString("      \"expects\": {\n        \"status\": [\n          200,\n          204\n        ],\n", $text);
        $this->assertSame('two', json_decode($text, true)['apis'][1]['id']);
    }

    public function testDuplicateIdAppendsNothing()
    {
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage('case ids already exist in acl.json: one');

        (new CatalogAppender())->append('acl', self::ACL, [['id' => 'one', 'method' => 'GET', 'path' => '/']]);
    }

    public function testRefusesBackendAndUnknownLayout()
    {
        $appender = new CatalogAppender();
        try {
            $appender->append('backend', self::CLIENT, [$this->acl()]);
            $this->fail('backend.json is generated');
        } catch (ScaffoldException $e) {
            $this->assertStringContainsString('generated', $e->getMessage());
        }
        $this->expectException(ScaffoldException::class);
        $appender->append('client', '{"apis": []}', [$this->acl()]);
    }

    /**
     * @return array[] catalog names
     */
    public function committedCatalogs(): array
    {
        return [['acl'], ['client'], ['mail'], ['live']];
    }

    /**
     * Golden test: cut the last case out of a committed catalog and append it again; the
     * file must come back byte for byte, so the style rules are the files' own.
     *
     * @dataProvider committedCatalogs
     * @param string $catalog
     */
    public function testAppendingTheLastCaseReproducesTheCommittedFile(string $catalog)
    {
        $text = str_replace("\r\n", "\n", file_get_contents(self::CATALOGS . '/' . $catalog . '.json'));
        $last = json_decode(substr($text, strrpos($text, ",\n    {\n") + 2, -strlen("\n  ]\n}\n")));
        $cut = substr($text, 0, strrpos($text, ",\n    {\n")) . "\n  ]\n}\n";

        $this->assertSame($text, (new CatalogAppender())->append($catalog, $cut, [$this->arrays($last)]));
    }

    /**
     * @param mixed $value decoded without the assoc flag
     * @return mixed arrays, with empty objects kept as objects
     */
    private function arrays($value)
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value) === [] ? $value : array_map([$this, 'arrays'], get_object_vars($value));
        }

        return is_array($value) ? array_map([$this, 'arrays'], $value) : $value;
    }
}
