<?php

namespace Gaia\Libraries\Meta;

use Gaia\Libraries\Cache\NullCache;

/**
 * Chooses where model metadata is read from: the metadata files, behind the cache when one other than
 * NullCache is configured, reporting cache reads to the request's Server-Timing recorder.
 *
 * @package  Foundation
 * @category metaManager
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelMetadataSourceFactory
{
    /**
     * @param \Phalcon\Di\DiInterface $di
     * @param Manager $fieldParser parses the fields for the file source
     * @return ModelMetadataSource
     */
    public static function create($di, Manager $fieldParser): ModelMetadataSource
    {
        $source = new FileModelMetadataSource($di, $fieldParser);
        if (!$di->has('cache')) {
            return $source;
        }
        $cache = $di->get('cache');
        if ($cache instanceof NullCache) {
            return $source;
        }

        return new CachedModelMetadataSource($source, $cache, function ($seconds, $hit) {
            if (isset($GLOBALS['serverTiming'])) {
                $GLOBALS['serverTiming']->recordMeta($seconds, $hit);
            }
        });
    }
}
