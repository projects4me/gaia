<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Phalcon;

use Phalcon\Autoload\Loader as AutoloadLoader;

/**
 * Phalcon 4's loader on Phalcon 5: Phalcon\Autoload\Loader with the
 * register* methods Gaia calls, which Phalcon 5 renamed to set*.
 *
 * Loaded only on Phalcon 5, by core/libs/compat/phalcon5.php.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Compat
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Loader extends AutoloadLoader
{
    /**
     * @param array $namespaces namespace => directory (or directories)
     * @param bool  $merge      add to the namespaces already registered
     * @return static
     */
    public function registerNamespaces(array $namespaces, bool $merge = false)
    {
        return $this->setNamespaces($namespaces, $merge);
    }

    /**
     * @param array $classes class name => file
     * @param bool  $merge   add to the classes already registered
     * @return static
     */
    public function registerClasses(array $classes, bool $merge = false)
    {
        return $this->setClasses($classes, $merge);
    }

    /**
     * @param array $directories directories to look up classes in
     * @param bool  $merge       add to the directories already registered
     * @return static
     */
    public function registerDirs(array $directories, bool $merge = false)
    {
        return $this->setDirectories($directories, $merge);
    }
}
