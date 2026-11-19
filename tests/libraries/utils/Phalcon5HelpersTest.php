<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Phalcon\Autoload\Loader;
use Phalcon\Support\Helper\Str\Camelize;
use Phalcon\Support\Helper\Str\Humanize;
use Phalcon\Support\Helper\Str\Lower;
use Phalcon\Support\Helper\Str\Random;
use PHPUnit\Framework\TestCase;

/**
 * The Phalcon 5 classes that replaced Phalcon 4's Loader and Text give the
 * results Gaia's call sites relied on, for the inputs those call sites pass.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Utils
 * @category Test
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Phalcon5HelpersTest extends TestCase
{
    /**
     * RestController and BulkRoutePolicy turn a controller or resource name
     * into a class name.
     */
    public function testCamelizeTurnsControllerNamesIntoClassNames()
    {
        $camelize = new Camelize();

        $this->assertSame('Issue', $camelize('issue'));
        $this->assertSame('Issuestatus', $camelize('issuestatus'));
        $this->assertSame('Projectactivity', $camelize('projectactivity'));
        $this->assertSame('IssueActivity', $camelize('issue_activity'));
        $this->assertSame('Issue', $camelize('Issue'));
    }

    /**
     * ProjectActivitiesComponent shows the old and new value of a field,
     * passed through ucwords().
     */
    public function testHumanizeTurnsAuditValuesIntoWords()
    {
        $humanize = new Humanize();

        $this->assertSame('In Progress', ucwords($humanize('in_progress')));
        $this->assertSame('New', ucwords($humanize('new')));
        $this->assertSame('Sprint 12', ucwords($humanize('sprint-12')));
        $this->assertSame('Release Candidate', ucwords($humanize('Release Candidate')));
        $this->assertSame('', ucwords($humanize('')));
    }

    /**
     * Relationship turns a relationship's relType into a join type.
     */
    public function testLowerTurnsRelTypesIntoJoinTypes()
    {
        $lower = new Lower();

        $this->assertSame('inner', $lower('INNER'));
        $this->assertSame('left', $lower('Left'));
        $this->assertSame('hasmany', $lower('hasMany'));
    }

    public function testRandomGivesAnAlphanumericToken()
    {
        $random = (new Random())(Random::RANDOM_ALNUM, 36);

        $this->assertSame(36, strlen($random));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{36}$/', $random);
    }

    /**
     * autoload.php keys namespaces with escaped separators; set* returns the
     * loader and replaces (not merges) unless asked to.
     */
    public function testLoaderSetsNamespacesClassesAndDirectories()
    {
        $loader = new Loader();

        $this->assertSame($loader, $loader->setNamespaces(["Gaia\\Tests\\Probe" => APP_PATH . '/tests/libraries/utils/']));
        $this->assertSame($loader, $loader->setNamespaces(["Gaia\\Tests\\Other" => APP_PATH . '/tests/']));
        $this->assertSame($loader, $loader->setClasses(['Gaia\\Tests\\Probe\\Thing' => APP_PATH . '/thing.php']));
        $this->assertSame($loader, $loader->setDirectories([APP_PATH . '/core/tasks']));

        $namespaces = array_map(function ($namespace) {
            return rtrim($namespace, '\\');
        }, array_keys($loader->getNamespaces()));
        $this->assertSame(['Gaia\\Tests\\Other'], $namespaces);

        $loader->setNamespaces(["Gaia\\Tests\\Probe" => APP_PATH . '/tests/libraries/utils/'], true);
        $this->assertCount(2, $loader->getNamespaces());

        $this->assertArrayHasKey('Gaia\\Tests\\Probe\\Thing', $loader->getClasses());
        $directories = array_map(function ($directory) {
            return rtrim($directory, '/');
        }, array_values($loader->getDirectories()));
        $this->assertContains(APP_PATH . '/core/tasks', $directories);
    }
}
