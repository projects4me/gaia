<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the registered model names from `core/config/models.php`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelListExtractor
{
    public const SOURCE = 'core/config/models.php';

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
     * @return string[] model names in file order
     */
    public function extract(): array
    {
        $config = SourceFile::readPhpArray($this->root . '/' . self::SOURCE);

        return isset($config['models']) ? array_values($config['models']) : [];
    }
}
