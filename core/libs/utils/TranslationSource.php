<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Gaia\MVC\Models\Translation;

/**
 * Reads a locale's labels from the translations table.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationSource
{
    /**
     * @param  string $locale
     * @return array Dotted path => label, for the rows that are not deleted.
     */
    public function labelsFor($locale)
    {
        $rows = Translation::find([
            'conditions' => 'locale = :locale: AND deleted = :deleted:',
            'bind' => ['locale' => $locale, 'deleted' => 0],
            'columns' => 'path, value',
        ]);

        $labels = [];
        foreach ($rows as $row) {
            $labels[$row->path] = $row->value;
        }

        return $labels;
    }
}
