<?php

namespace Gaia\Libraries\Meta;

use Gaia\Libraries\Cache\CacheInterface;

/**
 * Cache-aside in front of another source: reads meta.model.<Model> from the cache and, on a miss,
 * builds from the wrapped source and stores the result.
 *
 * @package  Foundation
 * @category metaManager
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CachedModelMetadataSource implements ModelMetadataSource
{
    public const KEY_PREFIX = 'meta.model.';

    /** @var ModelMetadataSource */
    private $source;

    /** @var CacheInterface */
    private $cache;

    /** @var callable|null called with (float $seconds, bool $hit) after every read */
    private $onRead;

    public function __construct(ModelMetadataSource $source, CacheInterface $cache, ?callable $onRead = null)
    {
        $this->source = $source;
        $this->cache = $cache;
        $this->onRead = $onRead;
    }

    public function get(string $model): array
    {
        $start = microtime(true);
        $key = self::KEY_PREFIX . $model;
        $metadata = $this->cache->get($key);
        $hit = is_array($metadata);
        if (!$hit) {
            $metadata = $this->source->get($model);
            $this->cache->set($key, $metadata);
        }
        if ($this->onRead !== null) {
            ($this->onRead)(microtime(true) - $start, $hit);
        }

        return $metadata;
    }
}
