<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

/**
 * Finds the code a hand-written doc cites in backticks and reports what no
 * longer exists. It checks only citations it can tell apart from prose:
 *  - file and directory paths (`core/libs/security/Acl.php`, `events/`);
 *  - `Class::member`, `Class::method()`, `Namespace\Class` and compound class
 *    names (`HermesPublisher`);
 *  - calls (`authorize()`) and verb-first method names (`getAction`).
 * Paths into the other repositories (hermes/, prometheus/, ares/) are not checked.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CitationChecker
{
    private const SOURCE_DIRECTORIES = ['app', 'core', 'db', 'tools', 'config', 'public'];

    private const OTHER_REPOSITORIES = ['hermes', 'prometheus', 'ares'];

    private const METHOD_VERBS = 'get|is|assert|find|derive|filter|apply|set|check|list|expand|resolve';

    /**
     * @var string repository root
     */
    private $root;

    /**
     * @var string[][]|null short class name => files declaring it
     */
    private $classes;

    /**
     * @var string[]|null file path => contents
     */
    private $sources = [];

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @param string $document path of the doc relative to the root
     * @return string[] one message per citation that does not resolve, without duplicates
     */
    public function missing(string $document): array
    {
        preg_match_all('/`([^`\n]+)`/', file_get_contents($this->root . '/' . $document), $matches);
        $missing = [];
        foreach (array_unique($matches[1]) as $token) {
            $reason = $this->check($token);
            if ($reason !== null) {
                $missing[] = '`' . $token . '`: ' . $reason;
            }
        }

        return $missing;
    }

    /**
     * @param string $token
     * @return string|null why the citation does not resolve, null when it does or is not a code citation
     */
    private function check(string $token): ?string
    {
        if (preg_match('/^[\w.\/-]+(\.(php|js|json|md|sh|sql|yml|yaml)|\/)$/', $token)) {
            return $this->checkPath($token);
        }
        if (preg_match('/^((?:[A-Z]\w*\\\\)*[A-Z]\w*)::(\$?\w+)(\(.*\))?$/', $token, $match)) {
            return $this->checkMember($match[1], $match[2]);
        }
        if (preg_match('/^(?:[A-Z]\w*\\\\)+([A-Z]\w*)$/', $token, $match)) {
            return $this->checkClass($match[1]);
        }
        if (preg_match('/^[A-Z][a-z0-9]+(?:[A-Z][A-Za-z0-9]*)+$/', $token)) {
            return $this->checkClass($token);
        }
        if (preg_match('/^(\w+)\(.*\)$/', $token, $match) || preg_match('/^((?:' . self::METHOD_VERBS . ')[A-Z]\w*)$/', $token, $match)) {
            return $this->defined($match[1]) ? null : 'no method with that name';
        }

        return null;
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function checkPath(string $path): ?string
    {
        if (in_array(explode('/', $path)[0], self::OTHER_REPOSITORIES, true)) {
            return null;
        }

        return file_exists($this->root . '/' . $path) ? null : 'no such file or directory';
    }

    /**
     * @param string $class short or namespaced class name
     * @param string $member method, constant or $property
     * @return string|null
     */
    private function checkMember(string $class, string $member): ?string
    {
        $short = substr(strrchr('\\' . $class, '\\'), 1);
        $error = $this->checkClass($short);
        if ($error !== null) {
            return $error;
        }
        $pattern = $member[0] === '$'
            ? '/\b(public|protected|private|static|var)\b[^;=(]*\\' . $member . '\b/'
            : '/\b(function\s+&?' . $member . '\b|const\s+' . $member . '\b)/';
        foreach ($this->hierarchy($short) as $source) {
            if (preg_match($pattern, $source)) {
                return null;
            }
        }

        return $short . ' has no ' . $member;
    }

    /**
     * @param string $short
     * @return string|null
     */
    private function checkClass(string $short): ?string
    {
        return isset($this->classes()[$short]) ? null : 'no class, interface or trait with that name';
    }

    /**
     * @param string $name
     * @return bool whether a method with that name is declared anywhere in the sources
     */
    private function defined(string $name): bool
    {
        foreach ($this->classes() as $files) {
            foreach ($files as $file) {
                if (preg_match('/function\s+&?' . preg_quote($name, '/') . '\b/', $this->source($file))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $short
     * @return string[] the class source, then its parents' sources
     */
    private function hierarchy(string $short): array
    {
        $sources = [];
        for ($depth = 0; $depth < 10 && isset($this->classes()[$short]); $depth++) {
            $parent = null;
            foreach ($this->classes()[$short] as $file) {
                $source = $this->source($file);
                $sources[] = $source;
                $extends = '/\b(?:class|interface)\s+' . $short . '\s+extends\s+\\\\?(?:[\w\\\\]*\\\\)?(\w+)/';
                if (preg_match($extends, $source, $found)) {
                    $parent = $found[1];
                }
            }
            if ($parent === null) {
                break;
            }
            $short = $parent;
        }

        return $sources;
    }

    /**
     * @return string[][] short class name => files, for every class, interface and trait in the source directories
     */
    private function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }
        $this->classes = [];
        foreach (self::SOURCE_DIRECTORIES as $directory) {
            if (!is_dir($this->root . '/' . $directory)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root . '/' . $directory, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = $this->source($file->getPathname());
                if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/m', $source, $found)) {
                    foreach ($found[1] as $name) {
                        $this->classes[$name][] = $file->getPathname();
                    }
                }
            }
        }

        return $this->classes;
    }

    /**
     * @param string $file
     * @return string
     */
    private function source(string $file): string
    {
        if (!isset($this->sources[$file])) {
            $this->sources[$file] = (string) file_get_contents($file);
        }

        return $this->sources[$file];
    }
}
