<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Hides the value of secure settings in API documents.
 *
 * Secure settings never leave the server: `value` becomes null and `isSet`
 * tells the client whether a value is stored (so the UI can say "saved").
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SecureSettingMasker
{
    /**
     * Mask every secure setting in a JSON:API document (data and included).
     *
     * @param  array $document
     * @return array
     */
    public static function maskDocument(array $document)
    {
        foreach (['data', 'included'] as $section) {
            if (empty($document[$section]) || !is_array($document[$section])) {
                continue;
            }

            if (isset($document[$section]['attributes'])) {
                $document[$section] = self::maskResource($document[$section]);
                continue;
            }

            foreach ($document[$section] as $index => $resource) {
                if (is_array($resource)) {
                    $document[$section][$index] = self::maskResource($resource);
                }
            }
        }

        return $document;
    }

    /**
     * Mask one setting resource; other resource types pass through unchanged.
     *
     * @param  array $resource
     * @return array
     */
    public static function maskResource(array $resource)
    {
        // List responses use the model alias ("Setting"), single-record responses the module ("setting").
        if (!isset($resource['type'], $resource['attributes']) || strtolower((string) $resource['type']) !== 'setting') {
            return $resource;
        }

        $attributes = $resource['attributes'];
        $value = isset($attributes['value']) ? $attributes['value'] : null;

        // `secure` may be missing when the client limits `fields`; ciphertext is masked regardless.
        $isSecure = (isset($attributes['secure']) && (int) $attributes['secure'] === 1)
            || SettingsCipher::isEncrypted($value);
        if (!$isSecure) {
            return $resource;
        }

        $attributes['isSet'] = isset($attributes['value']) && $attributes['value'] !== '';
        $attributes['value'] = null;
        $resource['attributes'] = $attributes;

        return $resource;
    }
}
