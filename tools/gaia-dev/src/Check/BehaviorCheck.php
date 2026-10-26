<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Extractor\BehaviourExtractor;

/**
 * Checks the model behaviours: every behaviour named in model metadata has a class, every class has a test file
 * and, when asked, every test file passes run alone. Behaviours that predate the test rule are listed in
 * LEGACY_UNTESTED; the list only shrinks: a listed behaviour that gains a test must be removed from it.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviorCheck
{
    /**
     * Behaviours with no test file yet.
     */
    public const LEGACY_UNTESTED = [
        'auditBehavior', 'createdUserBehavior', 'currentUserBehavior', 'dateCreatedBehavior',
        'dateModifiedBehavior', 'encryptPasswordBehavior', 'modelIdentifierBehavior', 'modifiedUserBehavior',
        'settingBehavior', 'softDeleteBehavior',
    ];

    /**
     * @var string
     */
    private $root;

    /**
     * @var callable|null (string $testFile): string|null runs one test file alone, returns its output on failure
     */
    private $runner;

    /**
     * @param string $root repository root
     * @param callable|null $runner replaces the PHPUnit run, null to run PHPUnit; used to keep tests fast
     */
    public function __construct(string $root, ?callable $runner = null)
    {
        $this->root = rtrim($root, '/\\');
        $this->runner = $runner;
    }

    /**
     * @param bool $run whether to run each test file alone
     * @return string[] the problems, empty when the behaviours are consistent
     */
    public function problems(bool $run = true): array
    {
        $problems = [];
        $classes = $this->classes();

        foreach ($this->registered() as $name => $models) {
            if (!isset($classes[$name])) {
                $problems[] = "metadata: {$name} (used by " . implode(', ', $models) . ') has no class in ' . BehaviourExtractor::SOURCE;
            }
        }

        $tests = [];
        foreach ($classes as $name => $file) {
            $test = $this->testFile($name);
            if ($test === null) {
                if (!in_array($name, self::LEGACY_UNTESTED, true)) {
                    $problems[] = "test: {$name} has no tests/**/" . ucfirst($name) . 'Test.php';
                }
                continue;
            }
            if (in_array($name, self::LEGACY_UNTESTED, true)) {
                $problems[] = "legacy list: {$name} has a test now; remove it from BehaviorCheck::LEGACY_UNTESTED";
            }
            $tests[$name] = $test;
        }

        if ($run) {
            foreach ($tests as $name => $test) {
                $output = $this->runner !== null ? ($this->runner)($test) : $this->phpunit($test);
                if ($output !== null) {
                    $problems[] = "test: {$test} does not pass alone: " . $output;
                }
            }
        }

        return $problems;
    }

    /**
     * @return string[] behaviour name => repository relative class file
     */
    private function classes(): array
    {
        $classes = [];
        foreach ((new BehaviourExtractor($this->root))->extract() as $record) {
            $classes[$record['name']] = $record['file'];
        }

        return $classes;
    }

    /**
     * @return array behaviour name => the models whose metadata registers it
     */
    private function registered(): array
    {
        $registered = [];
        foreach (glob($this->root . '/app/metadata/model/*.php') ?: [] as $file) {
            $text = str_replace("\r\n", "\n", file_get_contents($file));
            if (!preg_match('/\'behaviors\' => array\(\n(.*?)\n    \),/s', $text, $block)) {
                continue;
            }
            preg_match_all('/\'(\w+)\'/', $block[1], $names);
            foreach ($names[1] as $name) {
                $registered[$name][] = basename($file, '.php');
            }
        }
        ksort($registered, SORT_STRING);

        return $registered;
    }

    /**
     * @param string $name behaviour name
     * @return string|null repository relative path of its test file
     */
    private function testFile(string $name): ?string
    {
        $wanted = ucfirst($name) . 'Test.php';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/tests', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getFilename() === $wanted) {
                return ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($this->root))), '/');
            }
        }

        return null;
    }

    /**
     * @param string $test repository relative test file
     * @return string|null null when the test passes, else the tail of PHPUnit's output
     */
    private function phpunit(string $test): ?string
    {
        $phpunit = $this->root . '/vendor/bin/phpunit';
        if (!is_file($phpunit)) {
            return 'vendor/bin/phpunit not found; run composer install or pass --skip-tests';
        }
        $output = [];
        $command = 'cd ' . escapeshellarg($this->root . '/tests') . ' && ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($phpunit) . ' -c phpunit.xml ' . escapeshellarg($this->root . '/' . $test) . ' 2>&1';
        exec($command, $output, $code);

        return $code === 0 ? null : trim(implode(' ', array_slice($output, -6)));
    }
}
