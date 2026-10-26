<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Cli;
use PHPUnit\Framework\TestCase;

/**
 * Tests the gaia-dev command line parsing and its error paths (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CliTest extends TestCase
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
     * @param string ...$args
     * @return int
     */
    private function cli(string ...$args): int
    {
        return Cli::main(array_merge(['gaia-dev'], $args), self::ROOT, $this->stdout, $this->stderr);
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

    public function testHelpPrintsUsageWithoutExporting()
    {
        $this->assertSame(0, $this->cli('graph', 'export', '--help', '--output=' . self::OUTPUT));

        $this->assertStringContainsString('Usage: gaia-dev', $this->read($this->stdout));
        $this->assertFalse(is_file(self::ROOT . '/' . self::OUTPUT));
    }

    public function testUnknownCommandPrintsUsageAndFails()
    {
        $this->assertSame(1, $this->cli('graph', 'import'));

        $this->assertStringContainsString('Usage: gaia-dev', $this->read($this->stderr));
    }

    public function testUnknownOptionFailsWithoutExporting()
    {
        $this->assertSame(1, $this->cli('graph', 'export', '--chek', '--output=' . self::OUTPUT));

        $this->assertStringContainsString('Unknown option --chek', $this->read($this->stderr));
        $this->assertFalse(is_file(self::ROOT . '/' . self::OUTPUT));
    }

    public function testRootWithoutAValueFails()
    {
        $this->assertSame(1, $this->cli('graph', 'export', '--root', '--output=' . self::OUTPUT));

        $this->assertStringContainsString('--root needs a value', $this->read($this->stderr));
    }

    public function testOutputWithoutAValueFails()
    {
        $this->assertSame(1, $this->cli('graph', 'export', '--output'));

        $this->assertStringContainsString('--output needs a value', $this->read($this->stderr));
    }

    public function testCheckWithAValueFails()
    {
        $this->assertSame(1, $this->cli('graph', 'export', '--check=yes'));

        $this->assertStringContainsString('--check takes no value', $this->read($this->stderr));
    }

    public function testUnreadableSourceFailsWithOneLine()
    {
        $missing = self::ROOT . '/does-not-exist';

        $this->assertSame(1, $this->cli('graph', 'export', '--root=' . $missing, '--output=' . self::OUTPUT));

        $error = $this->read($this->stderr);
        $this->assertStringContainsString($missing, $error);
        $this->assertSame(1, substr_count(trim($error), "\n") + 1);
    }

    public function testExportWritesTheGraph()
    {
        $this->assertSame(0, $this->cli('graph', 'export', '--output=' . self::OUTPUT));

        $this->assertFileExists(self::ROOT . '/' . self::OUTPUT);
    }
}
