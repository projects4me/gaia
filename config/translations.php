<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Translations served to Prometheus (see TranslationController).
 *
 * locales: the supported locales; GET /translations/{locale} answers only these.
 *   Adding a locale is a new entry here (plus its rows in the translations table).
 * cacheDirectory: where the built {locale}.json documents are cached.
 */
$config['translations'] = array(
    'locales' => array(
        'en-us',
    ),
    'cacheDirectory' => APP_PATH . DS . 'filesystem' . DS . 'translations',
);

return $config;
