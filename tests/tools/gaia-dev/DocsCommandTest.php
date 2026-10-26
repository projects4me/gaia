<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Command\DocsCommand;
use GaiaDev\Docs\Markdown;
use GaiaDev\Graph\GraphBuilder;
use GaiaDev\Graph\GraphJson;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev docs generate` and its `--check` drift detection (agaia-4132).
 *
 * The graph of the fixture repository is written to a scratch root, which the
 * pages are generated into and which is removed after each test.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DocsCommandTest extends TestCase
{
    /**
     * @var string
     */
    private $root;

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
        $this->root = sys_get_temp_dir() . '/gaia-dev-docs-' . uniqid();
        mkdir($this->root . '/docs/generated', 0775, true);
        file_put_contents(
            $this->root . '/docs/generated/graph.json',
            GraphJson::encode((new GraphBuilder(__DIR__ . '/fixtures/repo'))->build())
        );
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    /**
     * @param bool $check
     * @return int
     */
    private function docs(bool $check): int
    {
        return (new DocsCommand($this->stdout, $this->stderr))->run($this->root, $check);
    }

    /**
     * @return string what was written to stderr
     */
    private function errors(): string
    {
        rewind($this->stderr);

        return stream_get_contents($this->stderr);
    }

    public function testGeneratesTheIndexRoutesMatrixVocabularyAndOneModelPagePerModel(): void
    {
        $this->assertSame(0, $this->docs(false));

        foreach (['README.md', 'routes.md', 'acl-matrix.md', 'assertions.md', 'profiles.md', 'models/Widget.md'] as $page) {
            $this->assertFileExists($this->root . '/docs/generated/' . $page);
        }
        $this->assertStringContainsString('<a id="fields"></a>', file_get_contents($this->root . '/docs/generated/models/Widget.md'));
    }

    public function testEverySectionStaysWithinTheLineLimit(): void
    {
        $this->docs(false);

        foreach (glob($this->root . '/docs/generated/{,models/}*.md', GLOB_BRACE) as $page) {
            $sections = preg_split('/^(?=<a id=)/m', file_get_contents($page));
            array_shift($sections);
            foreach ($sections as $section) {
                $this->assertLessThanOrEqual(Markdown::SECTION_LINES, substr_count(rtrim($section), "\n") + 1, $page);
            }
        }
    }

    public function testLongTablesAreSplitIntoNumberedSectionsThatRepeatTheHeader(): void
    {
        $rows = [];
        for ($i = 1; $i <= 50; $i++) {
            $rows[] = ['row' . $i, 'x'];
        }
        $lines = Markdown::section('Rows', Markdown::table(['Name', 'Value'], $rows));

        $this->assertContains('<a id="rows-1-2"></a>', $lines);
        $this->assertContains('<a id="rows-2-2"></a>', $lines);
        $this->assertSame(2, count(array_keys($lines, '| Name | Value |')));
    }

    public function testCheckPassesRightAfterGenerating(): void
    {
        $this->docs(false);

        $this->assertSame(0, $this->docs(true));
    }

    public function testCheckFailsWhenAPageIsStaleMissingOrNoLongerGenerated(): void
    {
        $this->docs(false);
        file_put_contents($this->root . '/docs/generated/routes.md', "edited\n");
        unlink($this->root . '/docs/generated/profiles.md');
        file_put_contents($this->root . '/docs/generated/models/Gone.md', "old\n");

        $this->assertSame(1, $this->docs(true));
        $errors = $this->errors();
        $this->assertStringContainsString('routes.md is stale', $errors);
        $this->assertStringContainsString('profiles.md is missing', $errors);
        $this->assertStringContainsString('models/Gone.md is no longer generated', $errors);
    }

    public function testGenerateRemovesPagesThatAreNoLongerGenerated(): void
    {
        file_put_contents($this->root . '/docs/generated/old.md', "old\n");

        $this->docs(false);

        $this->assertFileDoesNotExist($this->root . '/docs/generated/old.md');
        $this->assertFileExists($this->root . '/docs/generated/graph.json');
    }

    public function testCheckIgnoresLineEndings(): void
    {
        $this->docs(false);
        $path = $this->root . '/docs/generated/routes.md';
        file_put_contents($path, str_replace("\n", "\r\n", file_get_contents($path)));

        $this->assertSame(0, $this->docs(true));
    }

    public function testFailsWithoutAGraph(): void
    {
        unlink($this->root . '/docs/generated/graph.json');

        $this->assertSame(1, $this->docs(false));
        $this->assertStringContainsString('graph export first', $this->errors());
    }
}
