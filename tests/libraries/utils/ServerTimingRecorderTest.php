<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\ServerTimingRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Server-Timing recorder.
 */
class ServerTimingRecorderTest extends TestCase
{
    public function testClassifiesCatalogQueriesAsSchema()
    {
        $this->assertSame('schema', ServerTimingRecorder::classify('SELECT * FROM information_schema.columns'));
        $this->assertSame('schema', ServerTimingRecorder::classify('SELECT * FROM pg_class'));
        $this->assertSame('db', ServerTimingRecorder::classify('SELECT "name" FROM project'));
        $this->assertSame('db', ServerTimingRecorder::classify("SELECT 1 FROM t WHERE x = 'upg_x'"));
    }

    public function testHeadersReportBootTotalAndAlwaysSchemaAndDb()
    {
        $recorder = new ServerTimingRecorder(microtime(true));
        $recorder->markBooted();
        $headers = $recorder->headers();

        $this->assertCount(1, $headers);
        $this->assertMatchesRegularExpression(
            '/^Server-Timing: boot;dur=[\d.]+, schema;dur=0\.0, db;dur=0\.0, total;dur=[\d.]+$/',
            $headers[0]
        );
    }

    public function testCountsQueriesAndIgnoresAfterWithoutBefore()
    {
        $recorder = new ServerTimingRecorder(microtime(true));
        $recorder->afterQuery('SELECT 1');
        $recorder->beforeQuery();
        $recorder->afterQuery('SELECT 1');
        $recorder->beforeQuery();
        $recorder->afterQuery('SELECT * FROM information_schema.tables');

        $header = $recorder->headers()[0];
        $this->assertStringContainsString('db;dur=', $header);
        $this->assertStringContainsString('desc="1 queries"', $header);
    }

    public function testSendsTimingAllowOriginOnlyWhenConfigured()
    {
        $this->assertCount(1, (new ServerTimingRecorder(microtime(true)))->headers());
        $headers = (new ServerTimingRecorder(microtime(true), 'https://app.example'))->headers();
        $this->assertSame('Timing-Allow-Origin: https://app.example', $headers[1]);
    }
}
