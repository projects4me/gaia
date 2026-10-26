<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Extracts the controller components in `app/api/v1/controllers/components/`,
 * including the events and notifications modules.
 *
 * A component's key is the name a controller lists in `$uses`: its path under
 * the components folder with each folder capitalised and the `Component`
 * suffix dropped (`events/IssueLiveEventsComponent.php` gives `Events\IssueLiveEvents`).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ComponentExtractor
{
    public const SOURCE = 'app/api/v1/controllers/components';

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array[] per class: key, kind, class, file, extends, implements, hooks (public methods)
     */
    public function extract(): array
    {
        $base = $this->root . '/' . self::SOURCE;
        $records = [];
        foreach ($this->files($base) as $relative) {
            $statements = PhpSource::parse($base . '/' . $relative);
            $namespace = PhpSource::namespaceOf($statements);
            foreach (PhpSource::classes($statements) as $class) {
                $records[] = [
                    'key' => $this->key($relative),
                    'kind' => $this->kind($relative),
                    'class' => ltrim($namespace . '\\' . $class->name->toString(), '\\'),
                    'file' => self::SOURCE . '/' . $relative,
                    'extends' => $class instanceof Class_ ? PhpSource::nameToString($class->extends) : null,
                    'implements' => $this->interfaces($class),
                    'hooks' => PhpSource::publicMethods($class),
                ];
            }
        }

        return $records;
    }

    /**
     * @param string $base
     * @return string[] php files under the base, relative with `/`, sorted
     */
    private function files(string $base): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param string $relative
     * @return string
     */
    private function key(string $relative): string
    {
        $parts = explode('/', preg_replace('/\.php$/', '', $relative));
        $last = array_pop($parts);
        $parts = array_map('ucfirst', $parts);
        $parts[] = preg_replace('/Component$/', '', $last);

        return implode('\\', $parts);
    }

    /**
     * `event` for the events folder, `notification` for the notification modules,
     * `support` for helpers and config, `component` for the rest.
     *
     * @param string $relative
     * @return string
     */
    private function kind(string $relative): string
    {
        $parts = explode('/', $relative);
        if (count($parts) === 1) {
            return 'component';
        }
        if ($parts[0] === 'events') {
            return $parts[1] === 'support' ? 'support' : 'event';
        }
        if ($parts[0] === 'notifications') {
            return $parts[1] === 'modules' ? 'notification' : 'support';
        }

        return 'component';
    }

    /**
     * @param ClassLike $class
     * @return string[]
     */
    private function interfaces(ClassLike $class): array
    {
        if (!$class instanceof Class_) {
            return [];
        }

        return array_map('strval', $class->implements);
    }
}
