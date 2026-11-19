<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\ApiTester;

use ApiTester\ResponseRecord;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the response record written by --record-bodies (agaia-4685).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResponseRecordTest extends TestCase
{
    public function testJsonBodyIsParsedKeepingEmptyObjects()
    {
        $record = ResponseRecord::fromResponse([
            'status' => 200,
            'headers' => "HTTP/1.1 200 OK\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n",
            'body' => '{"data":{},"list":[]}',
        ]);

        $this->assertSame(200, $record['status']);
        $this->assertSame('application/json; charset=UTF-8', $record['contentType']);
        $this->assertSame('json', $record['bodyFormat']);
        $this->assertSame('{"data":{},"list":[]}', json_encode($record['body']));
    }

    public function testNonJsonAndEmptyBodiesAreKeptAsText()
    {
        $html = ResponseRecord::fromResponse(['status' => 500, 'headers' => '', 'body' => '<b>Fatal</b>']);
        $empty = ResponseRecord::fromResponse(['status' => 200, 'headers' => '', 'body' => '']);

        $this->assertSame(['text', '<b>Fatal</b>'], [$html['bodyFormat'], $html['body']]);
        $this->assertSame(['text', ''], [$empty['bodyFormat'], $empty['body']]);
        $this->assertNull($empty['contentType']);
    }

    public function testContentTypeComesFromTheLastHeaderBlock()
    {
        $headers = "HTTP/1.1 100 Continue\r\nContent-Type: text/plain\r\n\r\n"
            . "HTTP/1.1 201 Created\r\ncontent-type: application/json\r\n\r\n";

        $this->assertSame('application/json', ResponseRecord::contentType($headers));
    }
}
