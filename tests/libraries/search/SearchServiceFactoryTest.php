<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\SearchServiceFactory;
use PHPUnit\Framework\TestCase;

/**
 * The search settings come from system.search only (agaia-4888): a missing one is an error, never a silent default.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchServiceFactoryTest extends TestCase
{
    /**
     * The system.search the test found, put back after it.
     *
     * @var array|null
     */
    private $search;

    protected function setUp(): void
    {
        global $settings;

        $this->search = isset($settings['system']['search']) ? $settings['system']['search'] : null;
        $settings['system']['search'] = array(
            'fuzzyThreshold' => 0.3,
            'semanticMaxDistance' => 0.5,
            'threshold' => 0.15,
            'fuzzyWeight' => 0.6,
            'semanticNear' => 0.156,
            'semanticFar' => 0.402,
            'allWordsBoundary' => 0.5,
            'partialNumberBoundary' => 0.5,
            'candidateDepth' => 100,
            'numberQueryWords' => 5,
        );
    }

    protected function tearDown(): void
    {
        global $settings;

        if ($this->search === null) {
            unset($settings['system']['search']);
        } else {
            $settings['system']['search'] = $this->search;
        }
    }

    /**
     * @dataProvider settingNames
     */
    public function testAMissingSettingIsAnError($name)
    {
        global $settings;

        unset($settings['system']['search'][$name]);

        $this->expectException(\Gaia\Exception\Exception::class);
        $this->expectExceptionMessage("system.search.{$name}");
        // Every setting is read before the DI, so no service is needed to get there
        SearchServiceFactory::create(null);
    }

    /**
     * @return array
     */
    public static function settingNames()
    {
        $names = array(
            'fuzzyThreshold',
            'semanticMaxDistance',
            'threshold',
            'fuzzyWeight',
            'semanticNear',
            'semanticFar',
            'allWordsBoundary',
            'partialNumberBoundary',
            'candidateDepth',
            'numberQueryWords',
        );

        return array_combine($names, array_map(function ($name) {
            return array($name);
        }, $names));
    }
}
