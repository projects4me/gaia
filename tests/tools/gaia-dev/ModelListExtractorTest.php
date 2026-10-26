<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\ModelListExtractor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the registered model list extractor (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelListExtractorTest extends TestCase
{
    public function testExtractsTheModelNamesInFileOrder()
    {
        $names = (new ModelListExtractor(__DIR__ . '/fixtures/repo'))->extract();

        $this->assertSame(['Widget', 'Ghost'], $names);
    }

    public function testReadsTheRepositoryModelList()
    {
        $names = (new ModelListExtractor(dirname(__DIR__, 3)))->extract();

        $this->assertContains('Issue', $names);
        $this->assertContains('Project', $names);
    }

    public function testFailsWhenTheSourceIsMissing()
    {
        $this->expectException(RuntimeException::class);

        (new ModelListExtractor(__DIR__ . '/fixtures/missing'))->extract();
    }
}
