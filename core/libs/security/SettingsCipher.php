<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Encrypts and decrypts secure application settings (e.g. the SMTP password).
 *
 * Uses libsodium secretbox (XSalsa20-Poly1305) with the key from
 * `settings.encryptionKey` (env SETTINGS_ENCRYPTION_KEY): 32 bytes, base64
 * encoded. Stored values look like `sbx1:<base64(nonce . ciphertext)>`; the
 * prefix versions the format so the scheme can change later.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SettingsCipher
{
    /**
     * Prefix of every value this class produces.
     *
     * @var string
     */
    public const PREFIX = 'sbx1:';

    /**
     * Raw 32-byte key, or null when no key is configured.
     *
     * @var string|null
     */
    private $key;

    /**
     * @param  string|null $base64Key Base64 encoded 32-byte key; null or empty means not configured.
     * @throws \InvalidArgumentException When a key is given but is not 32 bytes of valid base64.
     */
    public function __construct($base64Key)
    {
        if ($base64Key === null || $base64Key === '') {
            $this->key = null;
            return;
        }

        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                'SETTINGS_ENCRYPTION_KEY must be ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes, base64 encoded.'
            );
        }

        $this->key = $key;
    }

    /**
     * Build a cipher from the application configuration.
     *
     * @return self
     */
    public static function fromConfig()
    {
        global $settings;

        $key = isset($settings['settings']['encryptionKey']) ? $settings['settings']['encryptionKey'] : null;
        return new self($key);
    }

    /**
     * Whether an encryption key is configured.
     *
     * @return bool
     */
    public function hasKey()
    {
        return $this->key !== null;
    }

    /**
     * Whether the value was produced by this cipher.
     *
     * @param  string|null $value
     * @return bool
     */
    public static function isEncrypted($value)
    {
        return is_string($value) && strpos($value, self::PREFIX) === 0;
    }

    /**
     * Encrypt a plain-text value.
     *
     * @param  string $plainText
     * @return string
     * @throws \RuntimeException When no key is configured.
     */
    public function encrypt($plainText)
    {
        $this->assertKey();

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox((string) $plainText, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $cipherText);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * @param  string $value
     * @return string
     * @throws \RuntimeException When no key is configured, or the value is not ours or was tampered with.
     */
    public function decrypt($value)
    {
        $this->assertKey();

        if (!self::isEncrypted($value)) {
            throw new \RuntimeException('Value is not an encrypted setting.');
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Encrypted setting is malformed.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plainText === false) {
            throw new \RuntimeException('Encrypted setting could not be decrypted with the configured key.');
        }

        return $plainText;
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    private function assertKey()
    {
        if ($this->key === null) {
            throw new \RuntimeException('Settings encryption key is not configured (SETTINGS_ENCRYPTION_KEY).');
        }
    }
}
