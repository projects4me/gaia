<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Command\GraphExportCommand;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev graph export` and its `--check` drift detection (agaia-4128).
 *
 * The command writes to a scratch output path under the fixture repository,
 * removed after each test.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphExportCommandTest extends TestCase
{
    private const ROOT = __DIR__ . '/fixtures/repo';
    private const OUTPUT = 'scratch/graph.json';

    /**
     * @var resource
     */
    private $stdout;

    /**
     * @var resource
     */
    private $stderr;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        @unlink(self::ROOT . '/' . self::OUTPUT);
        @rmdir(dirname(self::ROOT . '/' . self::OUTPUT));
    }

    /**
     * @param bool $check
     * @return int
     */
    private function export(bool $check): int
    {
        return (new GraphExportCommand($this->stdout, $this->stderr))
            ->run(self::ROOT, self::OUTPUT, $check);
    }

    /**
     * @param resource $stream
     * @return string
     */
    private function read($stream): string
    {
        rewind($stream);

        return stream_get_contents($stream);
    }

    public function testExportWritesTheGraph()
    {
        $this->assertSame(0, $this->export(false));

        $graph = json_decode(file_get_contents(self::ROOT . '/' . self::OUTPUT), true);
        $this->assertContains('model:Widget', array_column($graph['nodes'], 'id'));
        $this->assertStringContainsString('Wrote scratch/graph.json', $this->read($this->stdout));
    }

    public function testCheckPassesRightAfterAnExport()
    {
        $this->export(false);

        $this->assertSame(0, $this->export(true));
    }

    public function testCheckIgnoresCrlfLineEndings()
    {
        $this->export(false);
        $path = self::ROOT . '/' . self::OUTPUT;
        file_put_contents($path, str_replace("\n", "\r\n", file_get_contents($path)));

        $this->assertSame(0, $this->export(true));
    }

    public function testCheckFailsWhenTheCommittedGraphIsStale()
    {
        $this->export(false);
        $path = self::ROOT . '/' . self::OUTPUT;
        file_put_contents($path, str_replace('model:Widget', 'model:Gadget', file_get_contents($path)));

        $this->assertSame(1, $this->export(true));
        $this->assertStringContainsString('scratch/graph.json is stale', $this->read($this->stderr));
    }

    public function testCheckFailsWhenTheGraphIsMissing()
    {
        $this->assertSame(1, $this->export(true));
        $this->assertStringContainsString('scratch/graph.json is missing', $this->read($this->stderr));
    }

    public function testCommittedRepositoryGraphIsUpToDate()
    {
        $command = new GraphExportCommand($this->stdout, $this->stderr);

        $this->assertSame(0, $command->run(dirname(__DIR__, 3), GraphExportCommand::DEFAULT_OUTPUT, true), $this->read($this->stderr));
    }
}
