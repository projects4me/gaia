<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Gaia\MVC\Models\Setting;

/**
 * SMTP settings: values saved in Settings > Email win, anything unset falls
 * back to the `smtp` config (core/config/smtp.php, SMTP_* env vars).
 *
 * Settings are read on every call, so saved values take effect without a
 * restart.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SmtpSettings
{
    /**
     * Encryption values a setting may hold (tls = STARTTLS).
     *
     * @var array
     */
    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    /**
     * Encryption used when neither settings nor config choose one (today's behaviour).
     *
     * @var string
     */
    public const DEFAULT_ENCRYPTION = 'tls';

    /**
     * Resolve the effective SMTP configuration from saved settings and the
     * `smtp` config.
     *
     * @return array See {@see resolve()}.
     */
    public static function load()
    {
        $config = \Phalcon\Di::getDefault()->get('config')->get('smtp');
        if (is_object($config) && method_exists($config, 'toArray')) {
            $config = $config->toArray();
        }

        // Email is a side effect: if settings can't be read, send with the config
        // rather than fail the user's request (forgot password, user create).
        try {
            $settings = self::readEmailSettings();
        } catch (\Exception $e) {
            global $logger;
            if (isset($logger)) {
                $logger->error('Email settings could not be read; using the smtp config: ' . $e->getMessage());
            }
            $settings = [];
        }

        return self::resolve(is_array($config) ? $config : [], $settings);
    }

    /**
     * Merge saved settings over config. Blank settings count as unset.
     *
     * @param  array $config   The `smtp` config (host, port, username, password, user, auth, ...)
     * @param  array $settings Setting values keyed by name, e.g. `email.smtpHost`
     * @return array host, port, encryption, auth, username, password, fromName, fromEmail
     */
    public static function resolve(array $config, array $settings)
    {
        $value = function ($name) use ($settings) {
            if (!array_key_exists($name, $settings) || $settings[$name] === null) {
                return null;
            }
            $trimmed = trim((string) $settings[$name]);
            return $trimmed === '' ? null : $trimmed;
        };
        $pick = function ($settingValue, $configKey) use ($config) {
            if ($settingValue !== null) {
                return $settingValue;
            }
            return isset($config[$configKey]) ? $config[$configKey] : null;
        };

        $username = $pick($value('email.smtpUsername'), 'username');

        $encryption = $value('email.smtpEncryption');
        if ($encryption !== null) {
            $encryption = strtolower($encryption);
        }
        if (!in_array($encryption, self::ENCRYPTIONS, true)) {
            $encryption = isset($config['encryption']) && in_array($config['encryption'], self::ENCRYPTIONS, true)
                ? $config['encryption']
                : self::DEFAULT_ENCRYPTION;
        }

        $fromEmail = $value('email.fromEmail');

        return [
            'host' => $pick($value('email.smtpHost'), 'host'),
            'port' => (int) $pick($value('email.smtpPort'), 'port'),
            'encryption' => $encryption,
            'auth' => isset($config['auth']) ? (bool) $config['auth'] : true,
            'username' => $username,
            // The password setting is a secure value: readEmailSettings() decrypts it.
            'password' => $pick($value('email.smtpPassword'), 'password'),
            'fromName' => $pick($value('email.fromName'), 'user'),
            // Today the sender address is the SMTP username.
            'fromEmail' => $fromEmail !== null ? $fromEmail : $username,
        ];
    }

    /**
     * Read the `email` settings group, decrypting secure values. A secure
     * value that can't be decrypted is treated as unset (config fallback),
     * so a missing key never breaks sending.
     *
     * @return array Values keyed by setting name
     */
    protected static function readEmailSettings()
    {
        global $logger;

        $values = [];
        $cipher = null;
        $rows = Setting::find([
            'conditions' => 'groupName = :group: AND deleted = false',
            'bind' => ['group' => 'email'],
        ]);

        foreach ($rows as $row) {
            $settingValue = $row->settingValue;
            if (SettingsCipher::isEncrypted($settingValue)) {
                try {
                    $cipher = $cipher ?: SettingsCipher::fromConfig();
                    $settingValue = $cipher->decrypt($settingValue);
                } catch (\RuntimeException $e) {
                    if (isset($logger)) {
                        $logger->warning("Setting {$row->name} could not be decrypted; using the config value");
                    }
                    $settingValue = null;
                }
            }
            $values[(string) $row->name] = $settingValue;
        }

        return $values;
    }
}
