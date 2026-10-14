<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\SettingsCipher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for encrypting secure application settings.
 */
class SettingsCipherTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            $this->markTestSkipped('libsodium is not available in this PHP runtime');
        }
    }

    public function testEncryptThenDecryptReturnsThePlainText()
    {
        $cipher = new SettingsCipher($this->newKey());

        $encrypted = $cipher->encrypt('s3cret-pass');

        $this->assertTrue(SettingsCipher::isEncrypted($encrypted));
        $this->assertStringNotContainsString('s3cret-pass', $encrypted);
        $this->assertSame('s3cret-pass', $cipher->decrypt($encrypted));
    }

    public function testEncryptingTheSameValueTwiceGivesDifferentCipherText()
    {
        $cipher = new SettingsCipher($this->newKey());

        $this->assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    public function testDecryptWithAnotherKeyFails()
    {
        $encrypted = (new SettingsCipher($this->newKey()))->encrypt('s3cret-pass');

        $this->expectException(\RuntimeException::class);
        (new SettingsCipher($this->newKey()))->decrypt($encrypted);
    }

    public function testDecryptRejectsTamperedValues()
    {
        $cipher = new SettingsCipher($this->newKey());
        $encrypted = $cipher->encrypt('s3cret-pass');
        $raw = base64_decode(substr($encrypted, strlen(SettingsCipher::PREFIX)));
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt(SettingsCipher::PREFIX . base64_encode($raw));
    }

    public function testDecryptRejectsValuesWithoutThePrefix()
    {
        $this->expectException(\RuntimeException::class);
        (new SettingsCipher($this->newKey()))->decrypt('plain text');
    }

    public function testMissingKeyMeansNoKeyAndEncryptFails()
    {
        $cipher = new SettingsCipher(null);
        $this->assertFalse($cipher->hasKey());
        $this->assertFalse((new SettingsCipher(''))->hasKey());

        $this->expectException(\RuntimeException::class);
        $cipher->encrypt('s3cret-pass');
    }

    public function testKeyOfTheWrongLengthIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingsCipher(base64_encode(random_bytes(16)));
    }

    public function testKeyThatIsNotBase64IsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingsCipher('not base64 !!');
    }

    public function testIsEncryptedOnlyMatchesThePrefix()
    {
        $this->assertTrue(SettingsCipher::isEncrypted(SettingsCipher::PREFIX . 'abc'));
        $this->assertFalse(SettingsCipher::isEncrypted('smtp.example.com'));
        $this->assertFalse(SettingsCipher::isEncrypted(null));
        $this->assertFalse(SettingsCipher::isEncrypted(''));
    }

    private function newKey()
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
